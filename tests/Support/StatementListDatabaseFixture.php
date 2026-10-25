<?php

namespace Tests\Support;

use App\Models\{CashflowAccount, CashflowEntry, Client, SalesInvoice, User, Vendor, VendorPaymentEntry};
use Carbon\Carbon;

/** Fictional statement fixtures, forced to a disposable in-memory testing database. */
class StatementListDatabaseFixture
{
    public static function install(): array
    {
        AnalyticsDatabaseFixture::install();
        (require database_path('migrations/2026_10_03_010000_create_party_statement_shares_table.php'))->up();
        Carbon::setTestNow('2026-10-25 12:00:00');
        $user = User::create(['name'=>'Statement analyst','email'=>'statements@example.test','password'=>'fixture',
            'role'=>'admin','access_profile'=>'custom','permissions'=>['finance.view']]);
        $client = Client::create(['client_number'=>'STMT-CLIENT','company_name'=>'Historical client','preferred_currency'=>'USD']);
        foreach ([['USD','2025-01-10',200], ['INR','2026-10-10',100]] as $i => $row) {
            SalesInvoice::create(['invoice_number'=>'STMT-'.$i,'invoice_type'=>'tax','status'=>'sent',
                'currency'=>$row[0],'invoice_date'=>$row[1],'total_amount'=>$row[2],'client_id'=>$client->id]);
        }
        $vendor = Vendor::create(['vendor_number'=>'STMT-VENDOR','vendor_name'=>'Historical vendor','preferred_currency'=>'RMB']);
        VendorPaymentEntry::create(['vendor_id'=>$vendor->id,'transaction_date'=>'2024-02-12','transaction_type'=>'credit',
            'foreign_currency'=>'RMB','foreign_amount'=>70,'particular'=>'Historical bill','status'=>'booked']);
        $account = CashflowAccount::create(['account_name'=>'GBP account','currency'=>'GBP']);
        CashflowEntry::create(['account_id'=>$account->id,'client_id'=>$client->id,'entry_date'=>'2023-03-01',
            'currency'=>'GBP','transaction_type'=>'credit','credit_amount'=>30,'debit_amount'=>0,
            'particular'=>'Historical GBP receipt','accounting_status'=>'booked']);
        return compact('user','client','vendor');
    }
}
