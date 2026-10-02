<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAttachment;
use App\Models\CashflowEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The bills behind the ledger.
 *
 * Every entry can carry the paperwork that explains it: the purchase bill, the
 * bank advice, the receipt, the GST document. The month-end question "where is
 * that bill" is answered by the entry it belongs to, and the archive lists
 * everything that has been filed with the month, the type and the party it
 * came from.
 */
class CashflowAttachmentController extends Controller
{
    /** The allowlist the other modules use as well — one line to change. */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip',
    ];

    /**
     * The archive: every document filed against an entry, filtered by the
     * month it was booked in, its type, or a search across the file name and
     * the entry it belongs to.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('cashflows.documents', [
            'documents' => $this->query($filters)->paginate(50)->withQueryString(),
            'documentTypeOptions' => CashflowAttachment::documentTypeOptions(),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($filters['dateFrom'], $filters['dateTo']),
            'chipCounts' => $this->chipCounts($filters),
            'missingCount' => $this->missingCount($filters),
            'totalSize' => (int) $this->query($filters)->sum('file_size'),
            ...$filters,
        ]);
    }

    public function store(Request $request, CashflowEntry $cashflow): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(CashflowAttachment::documentTypeOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        $count = 0;

        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);

            CashflowAttachment::create([
                'cashflow_entry_id' => $cashflow->id,
                'document_type' => $data['document_type'],
                // one title covers the batch; a single file is the common case
                'title' => $count === 0 ? ($data['title'] ?? null) : null,
                'file_path' => $file->store('cashflow-attachments/'.$cashflow->id, 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => strtolower((string) $file->getClientOriginalExtension()),
                'uploaded_by' => Auth::id(),
            ]);

            $count++;
        }

        return back()->with('success', $count === 1
            ? 'Document attached to this entry.'
            : $count.' documents attached to this entry.');
    }

    public function destroy(CashflowAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('success', 'Document removed.');
    }

    /**
     * @return array{q: string, documentType: string, dateFrom: ?string, dateTo: ?string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q')),
            'documentType' => $request->query('document_type', 'all'),
            'dateFrom' => $request->query('date_from'),
            'dateTo' => $request->query('date_to'),
        ];
    }

    private function query(array $filters)
    {
        return CashflowAttachment::query()
            ->with(['cashflowEntry.account', 'cashflowEntry.client', 'cashflowEntry.vendor', 'uploader'])
            ->when(($filters['documentType'] ?? 'all') !== 'all',
                fn ($q) => $q->where('document_type', $filters['documentType']))
            ->when($filters['dateFrom'] ?? null,
                fn ($q) => $q->whereHas('cashflowEntry', fn ($entry) => $entry->whereDate('entry_date', '>=', $filters['dateFrom'])))
            ->when($filters['dateTo'] ?? null,
                fn ($q) => $q->whereHas('cashflowEntry', fn ($entry) => $entry->whereDate('entry_date', '<=', $filters['dateTo'])))
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';

                $q->where(function ($nested) use ($term, $filters) {
                    $nested->where('original_name', 'like', $term)
                        ->orWhere('title', 'like', $term)
                        // the entry's own search scope: particular, bill number,
                        // bank reference, party name, expense head, notes
                        ->orWhereHas('cashflowEntry', fn ($entry) => $entry->search($filters['q']));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * One count per chip, each measured with the chip's own filter applied and
     * everything else left alone — the number on a chip is the number of rows
     * clicking it would show.
     *
     * @return array<string, int>
     */
    private function chipCounts(array $filters): array
    {
        $base = array_merge($filters, ['documentType' => 'all', 'dateFrom' => null, 'dateTo' => null]);

        $count = fn (array $overrides) => $this->query(array_merge($base, $overrides))->count();

        $counts = ['all' => $count([])];

        foreach (array_keys(CashflowAttachment::documentTypeOptions()) as $type) {
            $counts[$type] = $count(['documentType' => $type]);
        }

        foreach (DateRanges::presets() as $key => $range) {
            $counts[$key] = $count(['dateFrom' => $range['from'], 'dateTo' => $range['to']]);
        }

        return $counts;
    }

    /**
     * Entries in the same period that still have no document — handed to the
     * ledger's "Missing documents" chip, so the archive and the ledger can
     * never disagree about what is outstanding.
     */
    private function missingCount(array $filters): int
    {
        return CashflowEntry::query()
            ->whereDoesntHave('attachments')
            ->when($filters['dateFrom'] ?? null, fn ($q) => $q->whereDate('entry_date', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] ?? null, fn ($q) => $q->whereDate('entry_date', '<=', $filters['dateTo']))
            ->count();
    }

    private function validateAllowedFile($file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Only JPG, JPEG, PNG, WEBP, GIF, HEIC, HEIF, PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, TXT and ZIP files are allowed.',
            ]);
        }
    }
}
