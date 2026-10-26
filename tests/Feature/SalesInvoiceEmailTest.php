<?php

namespace Tests\Feature;

use App\Mail\SalesInvoiceMail;
use App\Models\OfficeAlert;
use App\Models\OfficeSetting;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceSend;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

/**
 * IT-SEND-001…007 — "email invoice to client" with send-event tracking,
 * the first slice of the audit's P0 collections line: the office can put the
 * invoice in the client's inbox from the record page (or the list), and every
 * attempt — including the ones the mailer refused — is provable afterwards.
 */
class SalesInvoiceEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'mail.from.address' => 'accounts@misspack.test', 'mail.from.name' => 'MissPack',
            'app.key' => 'base64:' . base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_08_26_010100_create_sales_invoice_items_table',
            '2026_08_26_010200_create_sales_invoice_attachments_table',
            '2026_10_03_050000_create_sales_invoice_reminders_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_06_11_010400_normalize_cashflow_currency_codes',
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

        /* A refused send briefs the desk — silence the chaser e-mail inside
           this fixture so it never shares the throwing transport under test. */
        OfficeSetting::putBriefings(array_merge(OfficeSetting::briefingDefaults(), ['emails' => false]));
    }

    private function office(): User
    {
        $user = User::create(['name' => 'Accounts officer', 'email' => 'accounts@example.test',
            'password' => 'password', 'role' => 'admin', 'access_profile' => 'full', 'department' => 'accounts']);
        $this->actingAs($user);

        return $user;
    }

    private function invoice(array $overrides = []): SalesInvoice
    {
        $invoice = SalesInvoice::create(array_merge([
            'invoice_number' => 'INV-TEST-0001', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'tok-' . bin2hex(random_bytes(4)),
            'invoice_date' => '2026-10-20', 'due_date' => '2026-11-20', 'currency' => 'INR',
            'seller_company_name' => 'MissPack India Pvt Ltd', 'seller_gstin' => '24AATCM8816E1Z5',
            'client_company_name' => 'Acme Consumer Ltd', 'client_email' => 'accounts@acme.test',
            'client_contact_name' => 'Ramesh Shah',
            'billing_city' => 'Ahmedabad', 'billing_pincode' => '380001',
            'subtotal' => 1000, 'taxable_amount' => 1000,
            'cgst_amount' => 90, 'sgst_amount' => 90,
            'total_amount' => 1180, 'balance_amount' => 1180,
        ], $overrides));

        SalesInvoiceItem::create(['sales_invoice_id' => $invoice->id,
            'product_name' => 'Printed carton 12x8x6', 'hsn_sac' => '4819',
            'quantity' => 10, 'unit' => 'PCS', 'rate' => 100, 'amount' => 1000]);

        return $invoice;
    }

    public function test_guests_and_employees_are_held_at_the_wall(): void
    {
        $invoice = $this->invoice();
        $this->post(route('sales-invoices.email', $invoice), ['to' => 'a@b.test'])->assertRedirect(route('login'));

        $employee = User::create(['name' => 'Floor hand', 'email' => 'floor@example.test',
            'password' => 'password', 'role' => 'employee', 'access_profile' => 'full']);
        $this->actingAs($employee);
        $this->post(route('sales-invoices.email', $invoice), ['to' => 'a@b.test'])
            ->assertRedirect(route('my.dashboard'));
    }

    public function test_the_invoice_goes_out_and_the_send_lands_in_the_log(): void
    {
        Mail::fake();
        $user = $this->office();
        $invoice = $this->invoice(); // a draft — its first time leaving the office

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'accounts@acme.test, ops@acme.test',
            'cc' => 'director@acme.test',
            'subject' => 'Invoice INV-TEST-0001 from MissPack India Pvt Ltd',
            'message' => "Dear Ramesh,\n\nPlease find the October invoice.",
            'mark_sent' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(SalesInvoiceMail::class, function ($m) use ($invoice) {
            $html = $m->render();
            return $m->hasTo('accounts@acme.test')
                && $m->hasTo('ops@acme.test')
                && $m->hasCc('director@acme.test')
                && $m->subject === 'Invoice INV-TEST-0001 from MissPack India Pvt Ltd'
                && str_contains($html, 'INV-TEST-0001')
                && str_contains($html, 'Please find the October invoice.')
                /* the link joins because this send flips the portal flag */
                && str_contains($html, route('sales-invoices.public', $invoice->public_token));
        });

        $this->assertDatabaseHas('sales_invoice_sends', [
            'sales_invoice_id' => $invoice->id,
            'status' => 'sent',
            'recipients' => 'accounts@acme.test, ops@acme.test',
            'cc' => 'director@acme.test',
            'sent_by' => $user->id,
        ]);
        $this->assertNull(SalesInvoiceSend::first()->error);

        /* the send *is* the mark-sent move: a draft becomes sent and portal-visible */
        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertTrue((bool) $invoice->show_client_portal);

        /* and the record page now answers "did anyone ever email this?" */
        $this->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertSee("In the client's inbox", false)
            ->assertSee('accounts@acme.test, ops@acme.test');
    }

    public function test_a_resend_of_an_issued_invoice_leaves_the_books_alone(): void
    {
        Mail::fake();
        $this->office();
        $invoice = $this->invoice(['status' => 'issued', 'show_client_portal' => false, 'sent_at' => null]);

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'accounts@acme.test',
            // no mark_sent box -> already issued, just a re-send to a new desk
            'subject' => 'Re-send: invoice INV-TEST-0001',
            'message' => 'Sending again at your request.',
        ])->assertRedirect()->assertSessionHas('success');

        /* nothing on the invoice itself may move */
        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertNull($invoice->sent_at);
        $this->assertFalse((bool) $invoice->show_client_portal);

        /* and since the portal flag stays off, the mail cannot pretend a link works */
        Mail::assertSent(SalesInvoiceMail::class, fn ($m) =>
            ! str_contains($m->render(), 'View the invoice'));
    }

    public function test_the_composer_refuses_an_address_that_is_none(): void
    {
        Mail::fake();
        $this->office();
        $invoice = $this->invoice();

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'not-an-email',
            'subject' => 'Invoice INV-TEST-0001',
            'message' => 'Hello.',
        ])->assertRedirect()->assertSessionHasErrors(['to']);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('sales_invoice_sends', 0);
    }

    public function test_a_cancelled_invoice_is_never_emailed(): void
    {
        Mail::fake();
        $this->office();
        $invoice = $this->invoice(['status' => 'cancelled']);

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'accounts@acme.test',
            'subject' => 'Invoice INV-TEST-0001',
            'message' => 'Please ignore…',
        ])->assertRedirect()->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('sales_invoice_sends', 0);
    }

    public function test_without_a_configured_sender_the_office_is_told_first(): void
    {
        Mail::fake();
        config(['mail.from.address' => null]);
        $this->office();
        $invoice = $this->invoice();

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'accounts@acme.test',
            'subject' => 'Invoice INV-TEST-0001',
            'message' => 'Please find.',
        ])->assertRedirect()->assertSessionHas('error');

        Mail::assertNothingSent();
        /* a configuration gap is not a send attempt — the log keeps mail truth only */
        $this->assertDatabaseCount('sales_invoice_sends', 0);
        $invoice->refresh();
        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->sent_at);
    }

    public function test_a_refused_send_is_logged_and_briefs_the_desk(): void
    {
        $this->office();
        $invoice = $this->invoice();

        /* a transport that always refuses */
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willThrowException(new TransportException('SMTP connection refused'));
        config(['mail.default' => 'smtp']);
        app('mail.manager')->forgetMailers();
        app('mail.manager')->mailer('smtp')->setSymfonyTransport($transport);

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'accounts@acme.test',
            'subject' => 'Invoice INV-TEST-0001',
            'message' => 'Please find.',
            'mark_sent' => '1',
        ])->assertRedirect()->assertSessionHas('error');

        /* the attempt is on the invoice — with the transport's own reason */
        $send = SalesInvoiceSend::first();
        $this->assertNotNull($send);
        $this->assertSame('failed', $send->status);
        $this->assertStringContainsString('SMTP connection refused', (string) $send->error);

        /* the invoice did NOT claim to move out */
        $invoice->refresh();
        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->sent_at);

        /* and the desk hears about it on the bell/centre, not just the page */
        $brief = OfficeAlert::where('event_key', 'invoice.email_failed')->first();
        $this->assertNotNull($brief);
        $this->assertStringContainsString('INV-TEST-0001', $brief->title);
        $this->assertTrue((bool) $brief->requires_ack);
        $this->assertSame('accounts', $brief->team);
    }
}
