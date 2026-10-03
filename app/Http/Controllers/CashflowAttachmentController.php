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
use Illuminate\Support\Str;
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
            'partyOptions' => $this->partyOptions(),
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

    /**
     * The accountant's pack: everything the current filters match as one file —
     * the documents themselves, in a folder per type and named with their own
     * date, plus an index of what is inside. This is the answer to "send the
     * accountant October's bills for this party": set the two filters, press
     * the button, mail the file.
     *
     * A ZIP needs the zip extension; where it is missing this still returns the
     * index as a spreadsheet rather than an error, because half the pack is
     * still the half that says what exists.
     */
    public function pack(Request $request)
    {
        $filters = $this->filters($request);
        $documents = $this->query($filters)->get();

        /* One pass decides the name of every file and which ones the server no
           longer holds, so the ZIP and the index agree with each other — even
           when two documents share a title and a date. */
        $disk = Storage::disk('public');
        $names = [];
        $used = [];
        $missing = [];

        foreach ($documents as $document) {
            $names[$document->id] = $this->uniqueName($document->packName(), $used);

            $file = $document->file_path ? $disk->path($document->file_path) : null;
            if (! $file || ! is_file($file)) {
                $missing[$document->id] = true;
            }
        }

        $filename = $this->packFilename($filters);

        $index = $this->packIndexCsv($documents, $names, $missing);

        if (! class_exists(\ZipArchive::class)) {
            return response($index, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'cfpack');
        $zip = new \ZipArchive();

        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'The server could not open a pack file to write into.');
        }

        foreach ($documents as $document) {
            if (isset($missing[$document->id])) {
                continue;
            }

            $zip->addFile($disk->path($document->file_path), $names[$document->id]);
        }

        $zip->addFromString('index.csv', $index);
        $zip->close();

        if ($missing !== []) {
            session()->flash('warning', count($missing).' file(s) in this pack are no longer on the server — they are marked in index.csv.');
        }

        return response()->download($path, $filename.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
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
     * @return array{q: string, party: string, state: string, dateFrom: ?string, dateTo: ?string}
     */
    private function filters(Request $request): array
    {
        $state = (string) $request->query('state', 'all');

        return [
            'q' => trim((string) $request->query('q')),
            'party' => trim((string) $request->query('party')),
            'state' => in_array($state, ['all', 'linked', 'unlinked'], true) ? $state : 'all',
            /* dates or nothing: the same rule every other screen's filters
               follow, because these two travel into a ledger link */
            'dateFrom' => DateRanges::normalise($request->query('date_from')),
            'dateTo' => DateRanges::normalise($request->query('date_to')),
        ];
    }

    private function query(array $filters)
    {
        $query = CashflowAttachment::query()
            ->with(['cashflowEntry.account', 'cashflowEntry.client', 'cashflowEntry.vendor', 'uploader'])
            ->when(($filters['state'] ?? 'all') === 'linked', fn ($q) => $q->linked())
            ->when(($filters['state'] ?? 'all') === 'unlinked', fn ($q) => $q->unlinked())
            /* Whose paperwork it is: the name written on a stand-alone bill, or
               the counterparty of the entry it was filed against — the same two
               the row prints, so the filter can never disagree with the row. */
            ->when(($filters['party'] ?? '') !== '', function ($q) use ($filters) {
                $term = '%'.$filters['party'].'%';

                $q->where(function ($party) use ($term, $filters) {
                    $party->where('party_name', 'like', $term)
                        ->orWhereHas('cashflowEntry', function ($entry) use ($term, $filters) {
                            $entry->where('related_party_name', 'like', $term)
                                ->orWhereHas('client', fn ($client) => $client->where('company_name', 'like', $term))
                                ->orWhereHas('vendor', fn ($vendor) => $vendor->where('vendor_name', 'like', $term));
                        });
                });
            })
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

    /**
     * The parties that appear in the archive, for the filter's picker: the name
     * written on a stand-alone bill, and the counterparty of every entry that
     * has a document. Small list on purpose — this is the set somebody can be
     * asked to send a month to.
     *
     * @return array<int, string>
     */
    private function partyOptions(): array
    {
        $own = CashflowAttachment::query()
            ->whereNotNull('party_name')
            ->where('party_name', '!=', '')
            ->distinct()
            ->orderBy('party_name')
            ->pluck('party_name');

        /* a picker, not a report: the newest parties with paperwork, capped so
           the list stays short however many years the ledger holds */
        $linked = CashflowEntry::query()
            ->whereHas('attachments')
            ->with(['client:id,company_name', 'vendor:id,vendor_name'])
            ->orderByDesc('entry_date')
            ->limit(500)
            ->get(['id', 'client_id', 'vendor_id', 'related_party_name'])
            ->flatMap(fn ($entry) => [
                $entry->client?->company_name,
                $entry->vendor?->vendor_name,
                $entry->related_party_name,
            ]);

        return $own->merge($linked)
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->sort()
            ->values()
            ->take(200)
            ->all();
    }

    /** Two documents can be called the same thing; a ZIP cannot hold both. */
    private function uniqueName(string $name, array &$used): string
    {
        if (! isset($used[$name])) {
            $used[$name] = 1;

            return $name;
        }

        $used[$name]++;
        $info = pathinfo($name);
        $suffix = ' ('.$used[$name].')';

        return ($info['dirname'] !== '.' ? $info['dirname'].'/' : '')
            .$info['filename'].$suffix
            .(isset($info['extension']) ? '.'.$info['extension'] : '');
    }

    /** What the pack is: the period and the party, as a file name. */
    private function packFilename(array $filters): string
    {
        $range = DateRanges::keyOf($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null);
        $period = $range
            ? (DateRanges::LABELS[$range] ?? $range)
            : (($filters['dateFrom'] ?? null) && ($filters['dateTo'] ?? null)
                ? $filters['dateFrom'].' to '.$filters['dateTo']
                : 'all dates');

        $parts = ['cashflow documents', $period];

        if (($filters['party'] ?? '') !== '') {
            $parts[] = $filters['party'];
        }

        return Str::slug(implode(' ', $parts));
    }

    /**
     * The index the accountant reads first: one row per document, in the
     * columns an accountant asks for, as a UTF-8 CSV Excel opens properly.
     */
    private function packIndexCsv($documents, array $names, array $missing = []): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'Date', 'Type', 'Party', 'Amount', 'Currency', 'Entry', 'Bill / reference',
            'File in pack', 'Original name', 'Filed by', 'Filed on', 'Note',
        ]);

        foreach ($documents as $document) {
            $entry = $document->cashflowEntry;

            fputcsv($stream, [
                $document->displayDate()?->format('Y-m-d') ?? '',
                $document->documentTypeLabel(),
                $document->partyLabel(),
                $document->amount !== null ? number_format((float) $document->amount, 2, '.', '') : '',
                $document->currency ?: '',
                $entry?->particular ?? 'Not matched to an entry',
                $entry?->invoice_bill_number ?? '',
                $names[$document->id] ?? $document->packName(),
                $document->original_name ?? '',
                $document->uploader?->name ?? '',
                $document->created_at?->format('Y-m-d H:i') ?? '',
                isset($missing[$document->id]) ? 'File missing on the server' : '',
            ]);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
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
