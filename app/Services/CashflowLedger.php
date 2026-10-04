<?php

namespace App\Services;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use Illuminate\Support\Facades\Schema;

/**
 * Running-balance maintenance for cashflow accounts.
 *
 * Kept in one place so every module that writes cashflow entries (the
 * cashflow module itself, vendor payments, project payments, ...) refreshes
 * the ledger the same way instead of re-implementing the calculation.
 */
class CashflowLedger
{
    /**
     * Whether the cashflow module is installed and migrated.
     */
    public static function available(): bool
    {
        return class_exists(CashflowEntry::class)
            && class_exists(CashflowAccount::class)
            && Schema::hasTable('cashflow_entries');
    }

    /**
     * Recompute the stored running balance of every entry on an account and
     * refresh the account's current balance.
     */
    public static function recalculateAccount(?int $accountId): void
    {
        if (! $accountId || ! self::available()) {
            return;
        }

        $account = CashflowAccount::find($accountId);
        if (! $account) {
            return;
        }

        $runningBalance = (float) $account->opening_balance;

        CashflowEntry::query()
            ->where('account_id', $accountId)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['id', 'credit_amount', 'debit_amount'])
            ->each(function (CashflowEntry $entry) use (&$runningBalance) {
                $runningBalance += (float) $entry->credit_amount - (float) $entry->debit_amount;

                CashflowEntry::whereKey($entry->id)->update([
                    'balance' => round($runningBalance, 2),
                ]);
            });

        $account->update([
            'current_balance' => round($runningBalance, 2),
        ]);
    }
}
