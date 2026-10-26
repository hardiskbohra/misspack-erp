<?php

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

/**
 * TEMPORARY CI PROBE — not part of the suite's contract. Replays the refused
 * email send with the exception handler off so CI surfaces the raw exception
 * class and message; removed again as soon as the root cause is found.
 */
class ProbeEmail500Test extends TestCase
{
    public function test_probe_refused_send_without_exception_handling(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'mail.from.address' => 'accounts@misspack.test', 'mail.from.name' => 'MissPack',
            'app.key' => 'base64:' . base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_07_18_010700_add_project_id_to_cashflow_entries_table',
            '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table',
            '2026_10_15_100000_create_cashflow_allocations_table',
            '2026_10_26_020000_create_sales_invoice_sends_table',
            '2026_10_06_020000_create_office_alerts_table',
            '2026_10_06_030000_add_shared_ack_to_office_alerts',
            '2026_10_06_040000_add_snooze_and_email_to_office_alerts',
            '2026_10_06_050000_create_office_settings_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }

        $user = User::create(['name' => 'Accounts officer', 'email' => 'a@x.test',
            'password' => 'password', 'role' => 'admin', 'access_profile' => 'full', 'department' => 'accounts']);
        $this->actingAs($user);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-PROBE-1', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'tok-probe1', 'invoice_date' => '2026-10-20', 'due_date' => '2026-11-20',
            'currency' => 'INR', 'client_company_name' => 'Acme Ltd',
            'subtotal' => 1000, 'taxable_amount' => 1000, 'cgst_amount' => 90, 'sgst_amount' => 90,
            'total_amount' => 1180, 'balance_amount' => 1180,
        ]);

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willThrowException(new TransportException('SMTP connection refused'));
        config(['mail.default' => 'smtp']);
        app('mail.manager')->forgetMailers();
        app('mail.manager')->mailer('smtp')->setSymfonyTransport($transport);

        $this->withoutExceptionHandling();
        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'a@acme.test', 'subject' => 's', 'message' => 'm', 'mark_sent' => '1',
        ]);

        $this->assertTrue(true);
    }
}
