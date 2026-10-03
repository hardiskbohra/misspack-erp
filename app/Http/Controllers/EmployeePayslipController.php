<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayslip;
use App\Models\User;
use App\Services\DocumentUpload;
use App\Services\EmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The office's side of a payslip: record a month, attach the paper, issue it.
 *
 * A payslip is written here and read in two places — the office's record page
 * for the person and their own workspace — so the figures are stored once and
 * the row is the record. The file is optional on purpose: an office that pays by
 * bank transfer has the figures and no PDF, and a page that only listed files
 * would show them nothing.
 *
 * Drafts are visible to the office and invisible to the employee until they are
 * issued, because the numbers move while a month is being closed and teaching
 * somebody a figure that then changes is worse than showing them nothing.
 */
class EmployeePayslipController extends Controller
{
    public function __construct(private readonly EmployeeProfile $profile)
    {
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $entry = $this->entryFor($user, $data['period']);

        $payslip = EmployeePayslip::create(array_merge(
            $this->figures($data),
            [
                'user_id' => $user->id,
                'period' => $data['period'],
                'paid_on' => $data['paid_on'] ?? null,
                'status' => $data['status'],
                'cashflow_entry_id' => $entry?->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ],
            $this->fileColumns($request, null, $user)
        ));

        return back()->with('success', 'Payslip for '.$payslip->periodLabel().' saved.');
    }

    public function update(Request $request, User $user, EmployeePayslip $payslip): RedirectResponse
    {
        $this->assertOwned($user, $payslip);

        $data = $this->validated($request, $user, $payslip);
        $entry = $this->entryFor($user, $data['period']);

        $columns = array_merge(
            $this->figures($data),
            [
                'period' => $data['period'],
                'status' => $data['status'],
                'cashflow_entry_id' => $entry?->id,
            ],
            $this->fileColumns($request, $payslip, $user)
        );

        /* A row that is only being issued — the one-button case — does not post
           the notes or the payment date, and the presence of a key is the
           question, not its emptiness: `?? null` here would erase the office's
           note every time somebody issued a draft from the list. */
        foreach (['paid_on', 'notes'] as $optional) {
            if (array_key_exists($optional, $data)) {
                $columns[$optional] = $data[$optional];
            }
        }

        $payslip->update($columns);

        return back()->with('success', 'Payslip for '.$payslip->periodLabel().' updated.');
    }

    /** Removing a payslip removes the paper too — the row is the record. */
    public function destroy(User $user, EmployeePayslip $payslip): RedirectResponse
    {
        $this->assertOwned($user, $payslip);

        if ($payslip->file_path) {
            Storage::disk('public')->delete($payslip->file_path);
        }

        $label = $payslip->periodLabel();
        $payslip->delete();

        return back()->with('success', 'The payslip for '.$label.' was removed.');
    }

    /** The office can open any payslip, including a draft. */
    public function file(User $user, EmployeePayslip $payslip): BinaryFileResponse|RedirectResponse
    {
        $this->assertOwned($user, $payslip);

        return DocumentUpload::download($payslip->file_path, $payslip->fileName())
            ?? back()->with('error', 'That payslip has no file attached to it.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * The figures may be left empty: the office often records the net amount it
     * actually transferred and nothing else. What is refused is a gross and a
     * deduction that contradict the net, because a payslip nobody can add up is
     * a payslip the employee will query.
     */
    private function figures(array $data): array
    {
        $gross = round((float) ($data['gross_amount'] ?? 0), 2);
        $deductions = round((float) ($data['deductions'] ?? 0), 2);

        /* Blank and zero are different answers. The field is empty in the
           ordinary case — the office typed a gross and deductions and expects
           the net — so an empty string counts as "not given" and falls back to
           the subtraction, rather than becoming a net of zero. */
        $given = $data['net_amount'] ?? null;
        $given = ($given === null || $given === '') ? null : $given;

        $net = $given !== null
            ? round((float) $given, 2)
            : round($gross - $deductions, 2);

        if ($gross > 0 && $given === null && $deductions > $gross) {
            throw ValidationException::withMessages([
                'deductions' => 'Deductions cannot be more than the gross amount.',
            ]);
        }

        return [
            'gross_amount' => $gross,
            'deductions' => $deductions,
            'net_amount' => $net,
            'currency' => 'INR',
        ];
    }

    private function validated(Request $request, User $user, ?EmployeePayslip $payslip = null): array
    {
        return $request->validate([
            'period' => [
                'required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                /* One slip per person per month: a correction edits the row
                   rather than becoming a second answer to "what was March". */
                Rule::unique('employee_payslips', 'period')
                    ->where(fn ($query) => $query->where('user_id', $user->id))
                    ->ignore($payslip?->id),
            ],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(EmployeePayslip::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:'.DocumentUpload::MAX_KB, 'mimes:'.implode(',', DocumentUpload::ALLOWED_EXTENSIONS)],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);
    }

    /** The salary credit this month's slip belongs to, when the ledger has one. */
    private function entryFor(User $user, string $period)
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return null;
        }

        return $this->profile->salaryEntries(
            $user,
            $period.'-01',
            date('Y-m-t', strtotime($period.'-01'))
        )->first();
    }

    /** @return array<string, mixed> */
    private function fileColumns(Request $request, ?EmployeePayslip $payslip, User $user): array
    {
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            DocumentUpload::assertAllowed($file, 'attachment');

            /* The folder is the person's id, so two payslips can never land on
               one path. `$payslip` is null on create, which is why the id is
               read from the route as well — and read *before* the concat, not
               after it: `.` binds tighter than `??`, and the first version of
               this line quietly filed every new payslip in the root folder. */
            $ownerId = $payslip?->user_id ?? $request->route('user')?->id ?? $user->id;

            return DocumentUpload::replace($file, 'employee-payslips/'.$ownerId, $payslip?->file_path);
        }

        if ($request->boolean('remove_attachment') && $payslip?->file_path) {
            Storage::disk('public')->delete($payslip->file_path);

            return ['file_path' => null, 'original_name' => null, 'mime_type' => null, 'file_size' => null, 'extension' => null];
        }

        return [];
    }

    /** A payslip from the URL has to be this person's. */
    private function assertOwned(User $user, EmployeePayslip $payslip): void
    {
        abort_unless((int) $payslip->user_id === (int) $user->id, 404);
    }
}
