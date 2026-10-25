<?php

namespace Tests\Support;

use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Scoped, disposable fixtures for analytics and browser checks; never a live connection. */
class AnalyticsDatabaseFixture
{
    public static function install(): void
    {
        if (! app()->environment('testing')) throw new \LogicException('Analytics fixtures require testing.');
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'cache.default'=>'array', 'session.driver'=>'array']);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_020000_create_clients_table',
            '2026_06_09_030000_create_vendors_table',
            '2026_06_10_010000_create_cashflow_accounts_table',
            '2026_06_10_010100_create_cashflow_categories_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_07_12_010000_create_products_table',
            '2026_07_18_010000_create_projects_table',
            '2026_07_18_010700_add_project_id_to_cashflow_entries_table',
            '2026_07_25_010100_create_vendor_payment_entries_table',
            '2026_07_25_010200_create_vendor_payment_attachments_table',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_08_26_010100_create_sales_invoice_items_table',
            '2026_08_26_010200_create_sales_invoice_attachments_table',
            '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table',
            '2026_10_04_100000_add_vendor_payables_and_contacts',
            '2026_10_04_130000_create_purchase_invoices_table',
            '2026_10_04_130100_create_purchase_invoice_items_table',
            '2026_10_04_130200_add_purchase_invoice_id_to_vendor_payment_entries_table',
            '2026_10_06_060000_create_organisation_tables',
            '2026_10_15_100000_create_cashflow_allocations_table',
            '2026_10_19_120000_add_soft_deletes_to_projects',
            '2026_10_20_160000_link_cashflow_vendor_payments_to_the_ledger',
            '2026_10_02_010000_create_cashflow_attachments_table',
            '2026_10_02_020000_let_cashflow_documents_stand_alone',
            '2026_10_03_020000_promote_employee_on_cashflow_entries_table',
            '2026_10_03_060000_add_conversion_link_to_sales_invoices_table',
            '2026_10_21_130000_add_cash_invoice_conversions',
            '2026_10_07_130000_create_cashflow_recurrence_rules_table',
            '2026_10_07_130100_create_cashflow_recurrence_occurrences_table',
        ] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
    }

    public static function seed(): array
    {
        $user = User::create(['name'=>'Example analyst','email'=>'analyst@example.test','password'=>'password','role'=>'admin','access_profile'=>'full']);
        $client = Client::create(['client_number'=>'ANALYTICS-CLIENT','company_name'=>'Example Client','preferred_currency'=>'INR']);
        $invoice = SalesInvoice::create(['invoice_number'=>'ANALYTICS-TAX-001','invoice_type'=>'tax','status'=>'sent',
            'currency'=>'INR','invoice_date'=>'2026-10-10','due_date'=>'2026-10-11','client_id'=>$client->id,
            'client_company_name'=>'Example Client','total_amount'=>118,'amount_paid'=>18]);
        $invoice->items()->create(['product_name'=>'Example bottle','quantity'=>100,'unit'=>'pcs','unit_price'=>1.18,'line_total'=>118]);
        $foreign = SalesInvoice::create(['invoice_number'=>'ANALYTICS-USD-001','invoice_type'=>'tax','status'=>'sent',
            'currency'=>'USD','invoice_date'=>'2026-10-10','due_date'=>'2026-10-11','total_amount'=>1000]);
        $bill = PurchaseInvoice::create(['public_token'=>str_repeat('p',48),'invoice_number'=>'ANALYTICS-BILL-001','invoice_type'=>'bill','status'=>'received',
            'currency'=>'INR','invoice_date'=>'2026-10-12','due_date'=>'2026-10-11','total_amount'=>300,'vendor_company_name'=>'Example Vendor']);
        $account = CashflowAccount::create(['account_name'=>'Example INR account','currency'=>'INR']);
        $direct = CashflowEntry::create(['entry_date'=>'2026-10-11','particular'=>'Example direct receipt','transaction_type'=>'credit',
            'credit_amount'=>40,'debit_amount'=>0,'currency'=>'INR','account_id'=>$account->id,'accounting_status'=>'booked','sales_invoice_id'=>$invoice->id]);
        $entry = CashflowEntry::create(['entry_date'=>'2026-10-12','particular'=>'Example allocated receipt','transaction_type'=>'credit',
            'credit_amount'=>100,'debit_amount'=>0,'currency'=>'INR','account_id'=>$account->id,'accounting_status'=>'booked']);
        CashflowAllocation::create(['cashflow_entry_id'=>$entry->id,'sales_invoice_id'=>$invoice->id,
            'amount'=>10,'document_amount'=>10,'currency'=>'INR','document_currency'=>'INR']);
        return compact('user','client','invoice','foreign','bill','account','direct','entry');
    }
}
