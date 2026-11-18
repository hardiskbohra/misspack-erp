<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Per-document presentation choices; these never filter the underlying ledger. */
final class StatementPrintFields
{
    private const TRANSACTION_FIELDS = ['date', 'particulars', 'project_names', 'reference', 'debit', 'credit', 'balance'];

    private const SECTION_FIELDS = ['summary', 'bank_details', 'other_currencies', 'notes'];

    private const HEADER_FIELDS = ['logo', 'issuer_details', 'party_details'];

    /** @return array<string, bool> */
    public static function fromRequest(Request $request): array
    {
        $fields = array_fill_keys([...self::TRANSACTION_FIELDS, ...self::SECTION_FIELDS, ...self::HEADER_FIELDS], true);
        if ($request->query('fields_configured') !== '1') {
            return $fields;
        }

        // The marker distinguishes "nothing checked" from a legacy URL whose
        // missing options mean the complete, unchanged statement. Unknown and
        // nested values are ignored rather than ever becoming document markup.
        $chosen = $request->query('print_fields', []);
        $chosen = is_array($chosen) ? $chosen : [];
        $fields = array_fill_keys(array_keys($fields), false);
        foreach ($chosen as $key) {
            if (is_string($key) && array_key_exists($key, $fields)) {
                $fields[$key] = true;
            }
        }

        return $fields;
    }

    /** Preserve an explicit selection, including an intentionally empty one. */
    public static function query(Request $request, array $fields): array
    {
        if ($request->query('fields_configured') !== '1') {
            return [];
        }

        return ['fields_configured' => '1', 'print_fields' => array_keys(array_filter($fields))];
    }

    /** The two party types use the same figures, with different debit/credit meanings. */
    public static function labels(string $partyType): array
    {
        $vendor = $partyType === 'vendor';
        $columns = [
            'date' => 'Date',
            'particulars' => 'Description',
            'project_names' => 'Project names',
            'reference' => 'Reference',
            'debit' => $vendor ? 'Debit (paid)' : 'Debit (invoice)',
            'credit' => $vendor ? 'Credit (billed)' : 'Credit (received)',
            'balance' => 'Running balance',
        ];
        if (! $vendor) {
            unset($columns['project_names']);
        }

        return [
            'columns' => $columns,
            'sections' => [
                'summary' => 'Account summary',
                'bank_details' => 'Bank details',
                'other_currencies' => 'Other-currency notice',
                'notes' => 'Statement notes',
            ],
            'header' => [
                'logo' => 'Company logo',
                'issuer_details' => 'Issuer details',
                'party_details' => 'Client/vendor details',
            ],
        ];
    }

    /** Fields whose omission means the transaction detail is not complete. */
    public static function hiddenTransactions(array $fields, string $partyType): bool
    {
        foreach (self::TRANSACTION_FIELDS as $field) {
            if ($field === 'project_names' && $partyType !== 'vendor') {
                continue;
            }
            if (! ($fields[$field] ?? true)) {
                return true;
            }
        }

        return false;
    }
}
