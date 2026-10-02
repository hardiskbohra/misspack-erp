<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAttachment;
use App\Models\CashflowEntry;
use App\Models\CashflowMasterOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
 *
 * A document does not have to belong to an entry. A bill booked to a project, a
 * document that arrives before the payment does, an invoice settled outside
 * this ledger: those are filed on their own, keep their own party, date and
 * amount, and can be matched to an entry later — or never.
 */
class CashflowAttachmentController extends Controller
{
    /** The allowlist the other modules use as well — one line to change. */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip',
    ];

    /**
     * The archive: every document filed, filtered by the month it was booked
     * in, whether it is matched to an entry — or by a search across the file
     * name, the party and the entry it belongs to.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('cashflows.documents', [
            'documents' => $this->query($filters)->paginate(50)->withQueryString(),
            'documentTypeOptions' => CashflowAttachment::documentTypeOptions(),
            'currencyOptions' => $this->currencyOptions(),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($filters['dateFrom'], $filters['dateTo']),
            'chipCounts' => $this->chipCounts($filters),
            'missingCount' => $this->missingCount($filters),
            /* the same number the "Not matched" chip carries, so the card and
               the chip can never disagree about how much is still unclaimed */
            'unlinkedCount' => $this->chipCounts($filters)['unlinked'],
            'totalSize' => (int) $this->query($filters)->sum('file_size'),
            ...$filters,
        ]);
    }

    /** Files against an entry: the documents card on the entry's page. */
    public function store(Request $request, CashflowEntry $cashflow): RedirectResponse
    {
        return $this->file($request, $cashflow);
    }

    /** Files on their own: a bill that has no entry, and may never need one. */
    public function storeStandalone(Request $request): RedirectResponse
    {
        return $this->file($request, null);
    }

    /**
     * Matches a document that was filed on its own to an entry. The document's
     * own party name is kept if it has one — that is what is written on the
     * bill — and filled from the entry when it has none, so the archive still
     * says whose bill it was if the entry is ever deleted.
     */
    public function link(Request $request, CashflowEntry $cashflow): RedirectResponse
    {
        $data = $request->validate([
            'attachment_id' => ['required', Rule::exists('cashflow_attachments', 'id')],
        ]);

        /* Only a document that is still unclaimed can be matched: a second
           entry must not be able to pull a bill away from the first. */
        $attachment = CashflowAttachment::unlinked()->findOrFail($data['attachment_id']);

        $attachment->cashflow_entry_id = $cashflow->id;
        if (blank($attachment->party_name)) {
            $attachment->party_name = $cashflow->client?->company_name
                ?? $cashflow->vendor?->vendor_name
                ?? $cashflow->related_party_name;
        }
        $attachment->save();

        return back()->with('success', 'Document matched to this entry.');
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
     * One upload path for both entry points: the same allowlist, the same size
     * ceiling, the same metadata, whether or not an entry is attached to it.
     */
    private function file(Request $request, ?CashflowEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(CashflowAttachment::documentTypeOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::in($this->currencyKeys())],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        $count = 0;

        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);

            CashflowAttachment::create([
                'cashflow_entry_id' => $entry?->id,
                'document_type' => $data['document_type'],
                // one bill can arrive as several files; the title describes
                // the bill, so every file of the batch carries it
                'title' => $data['title'] ?? null,
                'party_name' => $data['party_name'] ?? null,
                'document_date' => $data['document_date'] ?? null,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'file_path' => $file->store('cashflow-attachments/'.($entry?->id ?? 'standalone'), 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => strtolower((string) $file->getClientOriginalExtension()),
                'uploaded_by' => Auth::id(),
            ]);

            $count++;
        }

        $what = $entry ? 'this entry' : 'the archive';

        return back()->with('success', $count === 1
            ? 'Document filed against '.$what.'.'
            : $count.' documents filed against '.$what.'.');
    }

    /**
     * @return array{q: string, state: string, dateFrom: ?string, dateTo: ?string}
     */
    private function filters(Request $request): array
    {
        $state = (string) $request->query('state', 'all');

        return [
            'q' => trim((string) $request->query('q')),
            'state' => in_array($state, ['all', 'linked', 'unlinked'], true) ? $state : 'all',
            'dateFrom' => $request->query('date_from'),
            'dateTo' => $request->query('date_to'),
        ];
    }

    private function query(array $filters)
    {
        $query = CashflowAttachment::query()
            ->with(['cashflowEntry.account', 'cashflowEntry.client', 'cashflowEntry.vendor', 'uploader'])
            ->when(($filters['state'] ?? 'all') === 'linked', fn ($q) => $q->linked())
            ->when(($filters['state'] ?? 'all') === 'unlinked', fn ($q) => $q->unlinked())
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';

                $q->where(function ($nested) use ($term, $filters) {
                    $nested->where('original_name', 'like', $term)
                        ->orWhere('title', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        // the entry's own search scope: particular, bill number,
                        // bank reference, party name, expense head, notes
                        ->orWhereHas('cashflowEntry', fn ($entry) => $entry->search($filters['q']));
                });
            });

        /* The period is applied last, as one parenthesised condition: it is the
           only filter that looks at three fields, and an or-group left loose
           would swallow every filter before it. */
        $this->applyPeriod($query, $filters['dateFrom'] ?? null, $filters['dateTo'] ?? null);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Which month a document belongs to: its own date when it has one — that is
     * the date written on the bill — otherwise the entry's date, otherwise the
     * day it was filed. The same rule the row prints, so the period chips and
     * the rows can never disagree about a document's month.
     */
    private function applyPeriod($query, ?string $from, ?string $to): void
    {
        if (! $from && ! $to) {
            return;
        }

        $query->where(function ($outer) use ($from, $to) {
            $outer->where(function ($own) use ($from, $to) {
                if ($from) {
                    $own->whereDate('document_date', '>=', $from);
                }
                if ($to) {
                    $own->whereDate('document_date', '<=', $to);
                }
            })->orWhere(function ($viaEntry) use ($from, $to) {
                $viaEntry->whereNull('document_date')
                    ->whereHas('cashflowEntry', function ($entry) use ($from, $to) {
                        if ($from) {
                            $entry->whereDate('entry_date', '>=', $from);
                        }
                        if ($to) {
                            $entry->whereDate('entry_date', '<=', $to);
                        }
                    });
            })->orWhere(function ($filed) use ($from, $to) {
                $filed->whereNull('document_date')
                    ->whereDoesntHave('cashflowEntry')
                    ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
            });
        });
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
        $base = array_merge($filters, [
            'state' => 'all',
            'dateFrom' => null,
            'dateTo' => null,
        ]);

        $count = fn (array $overrides) => $this->query(array_merge($base, $overrides))->count();

        $counts = [
            'all' => $count([]),
            'linked' => $count(['state' => 'linked']),
            'unlinked' => $count(['state' => 'unlinked']),
        ];

        foreach (DateRanges::presets() as $key => $range) {
            $counts[$key] = $count(['dateFrom' => $range['from'], 'dateTo' => $range['to']]);
        }

        return $counts;
    }

    /**
     * Entries in the same period that still have no document — handed to the
     * ledger's "Missing documents" chip, so the archive and the ledger can
     * never disagree about what is outstanding. A document filed on its own is
     * not attached to an entry, so it does not answer this question.
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

    /* the ledger's own currency list, so a document cannot be written in a
       currency the cashflow module does not know */
    private function currencyOptions(): array
    {
        return $this->masterOptions('currency', CashflowEntry::currencyOptions());
    }

    private function currencyKeys(): array
    {
        return array_keys($this->currencyOptions()) ?: ['INR'];
    }

    private function masterOptions(string $group, array $fallback = [], bool $activeOnly = true): array
    {
        if (! Schema::hasTable('cashflow_master_options')) {
            return $fallback;
        }

        $query = CashflowMasterOption::query()->where('group', $group);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $options = $query->orderBy('sort_order')->orderBy('label')->pluck('label', 'key')->toArray();

        return $options ?: $fallback;
    }
}
