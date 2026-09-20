<?php

namespace App\Http\Controllers;

use App\Models\CashflowAccount;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\CashflowMasterOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashflowSettingController extends Controller
{
    public function index(Request $request): View
    {
        $groupOptions = CashflowMasterOption::groupOptions();
        $activeTab = $request->query('tab', 'accounts');

        // Backward compatibility for old URL: /cashflows/settings?tab=masters&group=currency
        if ($activeTab === 'masters') {
            $activeTab = $request->query('group', 'currency');
        }

        $masterGroup = array_key_exists($activeTab, $groupOptions) ? $activeTab : 'currency';

        return view('cashflows.settings', [
            'activeTab' => $activeTab,
            'masterGroup' => $masterGroup,
            'accounts' => CashflowAccount::query()->withCount('entries')->orderBy('account_type')->orderBy('account_name')->get(),
            'categories' => CashflowCategory::query()->withCount('entries')->orderBy('type')->orderBy('name')->get(),
            'masters' => CashflowMasterOption::query()->where('group', $masterGroup)->orderBy('sort_order')->orderBy('label')->get(),
            'groupOptions' => $groupOptions,
            'accountTypeOptions' => $this->masterOptions('account_type', CashflowAccount::typeOptions(), false),
            'categoryTypeOptions' => $this->masterOptions('category_type', CashflowCategory::typeOptions(), false),
            'currencyOptions' => $this->masterOptions('currency', CashflowEntry::currencyOptions(), false),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $this->accountData($request);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['current_balance'] = $data['opening_balance'];
        $data['is_active'] = $request->boolean('is_active', true);

        CashflowAccount::create($data);

        return redirect()->route('cashflows.settings.index', ['tab' => 'accounts'])->with('success', 'Cashflow account created successfully.');
    }

    public function updateAccount(Request $request, CashflowAccount $account): RedirectResponse
    {
        $data = $this->accountData($request);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        $account->update($data);
        $this->recalculateAccountLedger($account->id);

        return redirect()->route('cashflows.settings.index', ['tab' => 'accounts'])->with('success', 'Cashflow account updated successfully.');
    }

    public function destroyAccount(CashflowAccount $account): RedirectResponse
    {
        if ($account->entries()->exists()) {
            return back()->withErrors('This account has cashflow entries. Make it inactive instead of deleting it.');
        }

        $account->delete();

        return redirect()->route('cashflows.settings.index', ['tab' => 'accounts'])->with('success', 'Cashflow account deleted successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->categoryData($request);
        $data['is_active'] = $request->boolean('is_active', true);

        CashflowCategory::create($data);

        return redirect()->route('cashflows.settings.index', ['tab' => 'categories'])->with('success', 'Cashflow category created successfully.');
    }

    public function updateCategory(Request $request, CashflowCategory $category): RedirectResponse
    {
        $data = $this->categoryData($request, $category);
        $data['is_active'] = $request->boolean('is_active');

        $category->update($data);

        return redirect()->route('cashflows.settings.index', ['tab' => 'categories'])->with('success', 'Cashflow category updated successfully.');
    }

    public function destroyCategory(CashflowCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('cashflows.settings.index', ['tab' => 'categories'])->with('success', 'Cashflow category deleted successfully.');
    }

    public function storeMaster(Request $request): RedirectResponse
    {
        $data = $this->masterData($request);
        $data['key'] = $this->normalizeMasterKey($data['group'], $data['key']);
        $data['is_active'] = $request->boolean('is_active', true);

        CashflowMasterOption::create($data);

        return redirect()->route('cashflows.settings.index', ['tab' => $data['group']])->with('success', 'Master option created successfully.');
    }

    public function updateMaster(Request $request, CashflowMasterOption $master): RedirectResponse
    {
        $data = $this->masterData($request, $master);
        $data['key'] = $this->normalizeMasterKey($data['group'], $data['key']);
        $data['is_active'] = $request->boolean('is_active');

        $oldGroup = $master->group;
        $master->update($data);

        return redirect()->route('cashflows.settings.index', ['tab' => $data['group'] ?: $oldGroup])->with('success', 'Master option updated successfully.');
    }

    public function destroyMaster(CashflowMasterOption $master): RedirectResponse
    {
        $group = $master->group;
        $master->delete();

        return redirect()->route('cashflows.settings.index', ['tab' => $group])->with('success', 'Master option deleted successfully.');
    }


    private function normalizeMasterKey(string $group, string $key): string
    {
        $key = trim($key);

        if ($group === 'currency') {
            // Currency codes are normally stored in uppercase, e.g. INR, USD, RMB.
            return strtoupper(preg_replace('/\s+/', '', $key));
        }

        return str($key)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    private function accountData(Request $request): array
    {
        return $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in($this->masterKeys('account_type', array_keys(CashflowAccount::typeOptions())))],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in($this->masterKeys('currency', array_keys(CashflowEntry::currencyOptions())))],
            'opening_balance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function categoryData(Request $request, ?CashflowCategory $category = null): array
    {
        $id = $category?->id ?? 'NULL';

        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cashflow_categories,name,'.$id.',id,type,'.$request->input('type')],
            'type' => ['required', Rule::in($this->masterKeys('category_type', array_keys(CashflowCategory::typeOptions())))],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function masterData(Request $request, ?CashflowMasterOption $master = null): array
    {
        $id = $master?->id ?? 'NULL';

        return $request->validate([
            'group' => ['required', Rule::in(array_keys(CashflowMasterOption::groupOptions()))],
            'key' => ['required', 'string', 'max:80', 'unique:cashflow_master_options,key,'.$id.',id,group,'.$request->input('group')],
            'label' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
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

    private function masterKeys(string $group, array $fallback): array
    {
        return array_keys($this->masterOptions($group, array_combine($fallback, $fallback), true));
    }

    private function recalculateAccountLedger(int $accountId): void
    {
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
