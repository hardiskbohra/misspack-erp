<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\EmployeePayslip;
use App\Services\DocumentUpload;
use App\Services\EmployeeAccess;
use App\Services\EmployeeProfile;
use App\Services\PayslipDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An employee's own workspace: what I earned, my papers, my details.
 *
 * One rule shapes this whole controller: **the person is the session, never the
 * URL**. There is no `{user}` anywhere in these routes, so there is no id to
 * change and no record to reach for. The two places that do take an id — a
 * payslip and a document — are checked against the signed-in user by
 * `App\Services\EmployeeAccess` before a single byte of the file is read, because
 * the failure here is not a wrong number on a screen: it is the person at the
 * next desk reading somebody's salary.
 *
 * Nothing is duplicated from the office's side of the application: the figures,
 * the checklist and the payslip list all come from the same
 * `App\Services\EmployeeProfile` the admin's page for this person uses.
 */
class EmployeeWorkspaceController extends Controller
{
    public function __construct(
        private readonly EmployeeProfile $profile,
        private readonly EmployeeAccess $access,
        private readonly PayslipDocument $document,
    ) {
    }

    /** The overview: this year's pay, the last payslip, and what is missing. */
    public function dashboard(Request $request): View
    {
        $year = (int) $request->query('year', date('Y'));
        $overview = $this->profile->overview($this->user(), $year);

        return view('employees.dashboard', array_merge($this->shared(), $overview, [
            'months' => $this->profile->monthlyPay($this->user(), $year),
            'year' => $year,
        ]));
    }

    public function profile(): View
    {
        $profile = $this->profile->profile($this->user());

        return view('employees.profile', array_merge($this->shared(), [
            'record' => $profile,
            'editable' => $this->access->ownEditableFields(),
        ]));
    }

    /**
     * The parts of the record that are the employee's own to keep current.
     *
     * Address, mobile number, date of birth and who to call in an emergency are
     * things only they know; designation, pay and the salary bank account are
     * the office's record, and the form is built from `ownEditableFields()` so
     * the two can never drift apart.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $this->user()->update($data);

        return back()->with('success', 'Your details are saved. The office can see them straight away.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Your password is changed.');
    }

    /**
     * What was credited, month by month, and every entry behind it.
     *
     * Reading the cashflow rows is deliberate: the money the employee sees on
     * their bank statement is a row in the ledger, and if the two ever disagree
     * the argument is settled by opening the entry — which is why each month
     * links to the rows the office filed.
     */
    public function salary(Request $request): View
    {
        $year = (int) $request->query('year', date('Y'));
        $entries = $this->profile->salaryEntries($this->user(), $year.'-01-01', $year.'-12-31');
        $slips = $this->profile->issuedPayslips($this->user());
        $preview = $slips->firstWhere('id', (int) $request->query('preview')) ?? $slips->first();

        return view('employees.salary', array_merge($this->shared(), [
            'year' => $year,
            'years' => $this->salaryYears(),
            'total' => $this->profile->yearTotal($this->user(), $year),
            'months' => $this->profile->monthlyPay($this->user(), $year),
            'entries' => $entries,
            /* The slips belong on this page, not on one of their own: a payslip
               is a month of this salary, and a second page listing the same
               months made the reader decide which one to believe. */
            'payslips' => $slips,
            /* The slip opens on the page as well as in its own tab: the newest
               one by default, and whichever row was clicked (?preview=<id>) when
               that is one of their own. */
            'previewSlip' => $preview,
            'previewSlipDoc' => $preview ? $this->document->build($preview, 'app') : null,
        ]));
    }

    /**
     * The old payslips page, kept as a door rather than a room.
     *
     * The slips are on the salary page now; a bookmark, a link in an old email
     * or an employee's muscle memory still lands somewhere that makes sense.
     */
    public function payslips(): RedirectResponse
    {
        /* The query says why the reader is here; the fragment is what actually
           takes them to the slips, which sit below the ledger months. */
        return redirect()->to(route('my.salary', ['focus' => 'payslips']).'#payslips');
    }

    /** The employee's own payslip, as paper — the same document the office prints. */
    public function payslipPdf(EmployeePayslip $payslip): Response|RedirectResponse
    {
        $denied = $this->guardPayslip($payslip);

        return $denied ?? $this->document->download($payslip);
    }

    /**
     * A payslip file, served only to the person it belongs to.
     *
     * A draft is invisible to them until the office issues it: the figures move
     * while a month is being closed, and showing a draft teaches an employee a
     * number that will change.
     */
    public function payslipFile(EmployeePayslip $payslip): BinaryFileResponse|RedirectResponse
    {
        $denied = $this->guardPayslip($payslip);

        return $denied ?? $this->serve($payslip->file_path, $payslip->fileName());
    }

    /**
     * Who may read a payslip: its own person, and only once it is issued.
     *
     * Both doors into a slip — the file the office attached and the slip this
     * application renders — ask this one question, so a draft that is invisible
     * as a file cannot be read as a document instead.
     */
    private function guardPayslip(EmployeePayslip $payslip): ?RedirectResponse
    {
        if (! $this->access->canView($this->user(), $payslip->user)) {
            return $this->access->deny($this->user(), $payslip->user);
        }

        if ($this->user()->isEmployee() && ! $payslip->isIssued()) {
            return back()->with('error', 'That payslip has not been issued yet.');
        }

        return null;
    }

    public function documents(): View
    {
        return view('employees.documents', array_merge($this->shared(), [
            'checklist' => $this->profile->documentChecklist($this->user()),
            'uploadTypes' => EmployeeDocument::documentTypeOptions(),
        ]));
    }

    /** An employee adds a paper to their own file. */
    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(EmployeeDocument::TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date'],
            'documents' => ['required', 'array', 'max:10'],
            'documents.*' => DocumentUpload::rules(),
        ]);

        $count = 0;

        foreach ((array) $request->file('documents', []) as $file) {
            DocumentUpload::assertAllowed($file, 'documents');

            EmployeeDocument::create(array_merge(DocumentUpload::store($file, 'employee-documents/'.$this->user()->id), [
                'user_id' => $this->user()->id,
                'document_type' => $data['document_type'],
                'title' => $data['title'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'uploaded_by' => $this->user()->id,
            ]));

            $count++;
        }

        return back()->with('success', $count === 1
            ? 'Document uploaded. The office will check it.'
            : $count.' documents uploaded. The office will check them.');
    }

    /**
     * An employee may remove a paper they added themselves and which nobody has
     * verified yet. Once the office has marked it seen — or the office uploaded
     * it — removing it is their call, not the employee's.
     */
    public function destroyDocument(EmployeeDocument $document): RedirectResponse
    {
        if (! $this->access->canView($this->user(), $document->user)) {
            return $this->access->deny($this->user(), $document->user);
        }

        if ($this->user()->isEmployee()) {
            if (! $document->uploadedByEmployee()) {
                return back()->with('error', 'The office filed that document. Ask them to remove it.');
            }

            if ($document->isVerified()) {
                return back()->with('error', 'That document has been verified by the office and can no longer be removed.');
            }
        }

        $this->deleteFile($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /** A document file, served only to its owner (or the office). */
    public function documentFile(EmployeeDocument $document): BinaryFileResponse|RedirectResponse
    {
        if (! $this->access->canView($this->user(), $document->user)) {
            return $this->access->deny($this->user(), $document->user);
        }

        return $this->serve($document->file_path, $document->fileName());
    }

    /* ------------------------------------------------------------------ */

    protected function user()
    {
        return Auth::user();
    }

    /** The view data every page of the workspace shares. */
    private function shared(): array
    {
        $user = $this->user();

        return [
            'me' => $user,
            'employeeProfile' => $this->profile,
        ];
    }

    /** Which years this employee has any pay in, newest first. */
    private function salaryYears(): array
    {
        $years = $this->profile->salaryEntries($this->user())
            ->map(fn ($entry) => $entry->entry_date ? (int) date('Y', strtotime((string) $entry->entry_date)) : null)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $current = (int) date('Y');

        if (! in_array($current, $years, true)) {
            array_unshift($years, $current);
        }

        return $years;
    }

    /** Send a stored file, by its real name, or say why it is not there. */
    private function serve(?string $path, string $name): BinaryFileResponse|RedirectResponse
    {
        return DocumentUpload::download($path, $name)
            ?? back()->with('error', 'That file is no longer on the server. Ask the office to upload it again.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
