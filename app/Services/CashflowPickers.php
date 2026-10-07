<?php

namespace App\Services;

use App\Models\CashflowAccount;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\CashflowMasterOption;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The lists every cashflow form is filled from.
 *
 * Both halves of the module read these: the ledger's own form and quick entry,
 * and a recurring rule's form. They are one class rather than two sets of
 * private helpers because a picker list is a fact about the business — *which*
 * accounts exist, *which* people may be paid — and two copies of "the employees
 * we can pay" drift the day somebody adds a rule to one of them. The rule form
 * asking for a different set of accounts than the entry form is exactly the kind
 * of difference nobody notices until a payment lands in the wrong place.
 *
 * Every method is defensive about tables that may not be migrated (the module is
 * installable on its own) and returns an empty collection rather than throwing:
 * a missing table is an empty dropdown, not a broken page.
 */
class CashflowPickers
{
    /**
     * Everything the entry and rule forms read, in one array.
     *
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        return [
            'accounts' => $this->accounts(),
            'categories' => $this->categories(),
            'clients' => $this->clients(),
            'vendors' => $this->vendors(),
            'officeServices' => $this->officeServices(),
            'employees' => $this->employees(),
            'accountTypeOptions' => $this->accountTypeOptions(),
            'categoryTypeOptions' => $this->categoryTypeOptions(),
            'transactionTypeOptions' => CashflowEntry::transactionTypeOptions(),
            'accountingStatusOptions' => $this->masterOptions('accounting_status', CashflowEntry::accountingStatusOptions()),
            'paymentModeOptions' => $this->masterOptions('payment_mode', CashflowEntry::paymentModeOptions()),
            'relatedPartyOptions' => $this->masterOptions('related_party_type', CashflowEntry::relatedPartyOptions()),
            'currencyOptions' => $this->masterOptions('currency', CashflowEntry::currencyOptions()),
        ];
    }

    public function accounts()
    {
        if (! class_exists(CashflowAccount::class) || ! Schema::hasTable('cashflow_accounts')) {
            return collect();
        }

        return CashflowAccount::where('is_active', true)->orderBy('account_type')->orderBy('account_name')->get();
    }

    public function categories()
    {
        if (! class_exists(CashflowCategory::class) || ! Schema::hasTable('cashflow_categories')) {
            return collect();
        }

        return CashflowCategory::where('is_active', true)->orderBy('type')->orderBy('name')->get();
    }

    public function accountTypeOptions(): array
    {
        return $this->masterOptions('account_type', class_exists(CashflowAccount::class) ? CashflowAccount::typeOptions() : []);
    }

    public function categoryTypeOptions(): array
    {
        return $this->masterOptions('category_type', class_exists(CashflowCategory::class) ? CashflowCategory::typeOptions() : []);
    }

    public function clients()
    {
        if (! $this->clientModelAvailable()) {
            return collect();
        }

        return \App\Models\Client::query()->orderBy('company_name')->get();
    }

    public function vendors()
    {
        if (! $this->vendorModelAvailable()) {
            return collect();
        }

        return \App\Models\Vendor::query()->orderBy('vendor_name')->get();
    }

    public function officeServices()
    {
        if (! class_exists(\App\Models\OfficeService::class) || ! Schema::hasTable('office_services')) {
            return collect();
        }

        return \App\Models\OfficeService::query()->orderBy('name')->get();
    }

    /**
     * The people an entry can be filed against. The app keeps one user table, so
     * this is the user list; department and designation come along for the
     * report's grouping and for the label on the row.
     */
    public function employees()
    {
        if (! class_exists(User::class) || ! Schema::hasTable('users')) {
            return collect();
        }

        /* Employees first, the office underneath — the ledger's Employee field
           is about somebody being paid, and the order is the model's answer
           (`User::employeePicker`), not a page's. */
        return User::employeePicker();
    }

    public function masterOptions(string $group, array $fallback = [], bool $activeOnly = true): array
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

    public function masterKeys(string $group, array $fallback): array
    {
        return array_keys($this->masterOptions($group, array_combine($fallback, $fallback), true));
    }

    public function clientModelAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }

    public function vendorModelAvailable(): bool
    {
        return class_exists(\App\Models\Vendor::class) && Schema::hasTable('vendors');
    }
}
