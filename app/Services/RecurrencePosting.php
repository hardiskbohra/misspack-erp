<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\CashflowRecurrenceOccurrence;
use App\Models\CashflowRecurrenceRule;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The one ledger row an approved occurrence becomes.
 *
 * Same shape as the module's other two mirrors (`VendorPaymentCashflowSync`,
 * `ShipmentCostCashflowSync`): the thing that owns the decision is the source of
 * truth, and the cashflow row is written to match it. The difference here is
 * direction — a vendor payment *is* money that left an account, while an
 * occurrence is only an ask until the office answers. So this class is called
 * from exactly one place (`RecurrencePlan::approve()`), inside the same
 * transaction as the decision, and it never updates: a posted entry is the
 * ledger's to edit afterwards, like any other row.
 *
 * Two rules are enforced rather than hoped for:
 *
 *   - **one entry per occurrence** — a row that already carries
 *     `cashflow_entry_id` returns that entry instead of writing a second one,
 *     so a double-click, a retried request or a re-run of the sweep cannot post
 *     a salary twice;
 *   - **no account, no entry** — the plan refuses to post rather than writing a
 *     row that no ledger can hold. (The form requires an account; this is the
 *     floor under the form.)
 *
 * The entry's own facts come from the rule — the amount as it stands on the day
 * of the decision, the particular, the party, the mode — and the running balance
 * is recomputed through `CashflowLedger`, which is the only thing in the
 * application that writes a balance column. The row lands `pending`, which is
 * where every new cashflow entry starts and where the bank reconciliation picks
 * it up.
 */
class RecurrencePosting
{
    /** Whether the cashflow ledger is installed and migrated. */
    public function available(): bool
    {
        return class_exists(CashflowEntry::class)
            && class_exists(CashflowRecurrenceRule::class)
            && Schema::hasTable('cashflow_entries');
    }

    public function post(CashflowRecurrenceOccurrence $occurrence, User $decidedBy): ?CashflowEntry
    {
        if (! $this->available()) {
            return null;
        }

        /* Already posted: the same answer, not a second payment. */
        if ($occurrence->cashflow_entry_id) {
            return CashflowEntry::find($occurrence->cashflow_entry_id);
        }

        $rule = $occurrence->rule ?: CashflowRecurrenceRule::find($occurrence->cashflow_recurrence_rule_id);

        if (! $rule) {
            throw new RuntimeException('An occurrence without its rule cannot post anything.');
        }

        if (! $rule->account_id) {
            throw new RuntimeException('This rule has no account to pay from, so it cannot post an entry.');
        }

        $amount = (float) $rule->amount;

        $data = [
            'entry_date' => $occurrence->effective_date?->toDateString() ?: now()->toDateString(),
            'particular' => $rule->particular ?: $rule->title,
            'transaction_type' => $rule->transaction_type === 'credit' ? 'credit' : 'debit',
            'credit_amount' => $rule->transaction_type === 'credit' ? $amount : 0,
            'debit_amount' => $rule->transaction_type === 'credit' ? 0 : $amount,
            'currency' => $rule->currency ?: 'INR',
            'account_id' => $rule->account_id,
            'category_id' => $rule->category_id,
            'payment_mode' => CashflowEntry::normalisePaymentMode($rule->payment_mode),
            'expense_head' => $rule->expense_head,
            'related_party_type' => $rule->related_party_type ?: 'other',
            'related_party_name' => $rule->related_party_name,
            'client_id' => $rule->client_id,
            'vendor_id' => $rule->vendor_id,
            'employee_id' => $rule->employee_id,
            'office_service_id' => $rule->office_service_id,
            'accounting_status' => 'pending',
            'notes' => $this->notes($rule, $occurrence),
            'created_by' => $decidedBy->id,
        ];

        /* The link decides the label, exactly as it does in the module's own
           writer: a rule paying a vendor is a vendor entry whatever the select
           was left on. */
        $entry = CashflowEntry::create(CashflowEntry::alignPartyType($data));

        CashflowLedger::recalculateAccount($entry->account_id);

        return $entry;
    }

    /**
     * What the ledger row says about where it came from.
     *
     * A cashflow entry that appeared overnight has to explain itself in the
     * narration — the office will read this row in six months beside a bank
     * statement, with no idea which rule wrote it.
     */
    private function notes(CashflowRecurrenceRule $rule, CashflowRecurrenceOccurrence $occurrence): string
    {
        $origin = sprintf(
            'Posted by the recurring rule “%s” — occurrence #%d, effective %s.',
            $rule->title,
            (int) $occurrence->sequence,
            $occurrence->effective_date?->format('d M Y') ?: '—',
        );

        return trim(($rule->notes ? $rule->notes."\n\n" : '').$origin);
    }
}
