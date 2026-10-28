<?php

namespace Tests\Support;

use App\Mail\SalesInvoiceMail;
use App\Models\Client;
use App\Models\OfficeSetting;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceSend;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectExceptionRegistry;
use App\Services\ProjectWorkflowExecution;
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Identical, full-schema scenarios run on SQLite and on the disposable strict-MySQL CI service. */
trait ErpReliabilityScenarios
{
    protected function prepareReliabilityScenario(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'hashing.bcrypt.rounds' => 4, 'mail.from.address' => 'office@example.test',
            'mail.from.name' => 'MissPack']);
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        OfficeSetting::putBriefings(array_merge(OfficeSetting::briefingDefaults(), ['emails' => false]));
    }

    private function reliabilityOffice(): User
    {
        $user = User::create(['name' => 'Reliability officer', 'email' => 'reliability@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $this->actingAs($user);
        return $user;
    }

    private function editableShipment(array $overrides = []): Shipment
    {
        $shipment = Shipment::create(array_merge([
            'shipment_number' => 'RELIABILITY-SHIPMENT', 'identity_name' => 'Original shipment',
            'shipment_type' => 'domestic', 'status' => 'planning', 'currency' => 'INR',
            'cost_borne_by' => 'misspack', 'public_token' => 'reliability-shipment-token',
        ], $overrides));
        $shipment->items()->create(['product_name' => 'Existing carton', 'quantity' => 2, 'unit' => 'pcs', 'currency' => 'INR']);
        return $shipment;
    }

    private function shipmentPayload(Shipment $shipment, array $overrides = []): array
    {
        return array_merge([
            'shipment_number' => $shipment->shipment_number,
            'identity_name' => 'Updated shipment', 'shipment_type' => 'domestic', 'status' => 'planning',
            'currency' => 'INR', 'cost_borne_by' => 'misspack',
            'items' => [['product_name' => 'Existing carton', 'quantity' => '2', 'unit' => 'pcs', 'currency' => 'INR']],
        ], $overrides);
    }

    protected function scenario_full_schema_dispatch_rejects_with_a_complete_long_evaluation_on_both_http_paths(): void
    {
        $user = $this->reliabilityOffice();
        $client = Client::create(['client_number' => 'RELIABILITY-CLIENT', 'company_name' => 'Acme']);
        $project = Project::forceCreate(['project_number' => 'RELIABILITY-PROJECT', 'client_id' => $client->id,
            'name' => 'Project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Carton', 'quantity' => 2]);
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'public_token' => 'reliability-invoice-token', 'client_id' => $client->id, 'project_id' => $project->id,
            'currency' => 'INR', 'total_amount' => 100, 'amount_paid' => 100, 'balance_before_dispatch' => true]);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $user->id);
        $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        $shipment = $this->editableShipment(['shipment_number' => str_repeat('S', 160), 'client_id' => $client->id, 'project_id' => $project->id]);
        $shipment->items()->update(['project_product_id' => $product->id]);
        $payload = $this->shipmentPayload($shipment, [
            'client_id' => $client->id, 'project_id' => $project->id, 'status' => 'picked_up',
            'items' => [['project_product_id' => $product->id, 'product_name' => 'Existing carton', 'quantity' => '2', 'unit' => 'pcs', 'currency' => 'INR']],
        ]);

        $this->from('/shipments/'.$shipment->id.'/edit')->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect('/shipments/'.$shipment->id.'/edit')->assertSessionHasErrors('status');
        $error = session('errors')->first('status');
        $this->assertStringContainsString('Packaging List', $error);
        $evaluation = $workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('blocked', $evaluation->result);
        $this->assertGreaterThan(255, mb_strlen($evaluation->summary));
        $this->assertStringContainsString($shipment->shipment_number, $evaluation->summary);
        $this->assertStringContainsString('Mandatory project dispatch documents', $evaluation->summary);
        $this->assertSame('text', Schema::getColumnType('project_gate_evaluations', 'summary'));
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->assertSame('Original shipment', $shipment->fresh()->identity_name);
        $this->assertSame(0, $shipment->histories()->count());
        $this->assertSame(1, $shipment->items()->count());

        $this->from('/shipments/'.$shipment->id)->post(route('shipments.history.store', $shipment), ['status' => 'picked_up'])
            ->assertRedirect('/shipments/'.$shipment->id)->assertSessionHasErrors('status');
        $this->assertStringContainsString('Packaging List', session('errors')->first('status'));
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->assertSame(0, $shipment->histories()->count());
        $this->assertSame(1, $workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->count());
    }

    protected function scenario_full_schema_normal_project_planning_save_succeeds_and_evaluates_readiness(): void
    {
        $user = $this->reliabilityOffice();
        $client = Client::create(['client_number' => 'RELIABILITY-CLIENT', 'company_name' => 'Acme']);
        $project = Project::forceCreate(['project_number' => 'RELIABILITY-PROJECT', 'client_id' => $client->id,
            'name' => 'Project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Carton', 'quantity' => 2]);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $user->id);
        $shipment = $this->editableShipment(['shipment_number' => str_repeat('S', 160), 'client_id' => $client->id, 'project_id' => $project->id]);
        $shipment->items()->update(['project_product_id' => $product->id]);
        $payload = $this->shipmentPayload($shipment, ['client_id' => $client->id, 'project_id' => $project->id,
            'items' => [['project_product_id' => $product->id, 'product_name' => 'Existing carton', 'quantity' => '2', 'unit' => 'pcs', 'currency' => 'INR']]]);
        $this->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect(route('shipments.show', $shipment))->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame('Updated shipment', $shipment->fresh()->identity_name);
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->assertSame(1, $shipment->items()->count());
        $evaluation = $workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('blocked', $evaluation->result);
        $this->assertGreaterThan(255, mb_strlen($evaluation->summary));
    }

    protected function scenario_out_of_range_shipment_values_are_rejected_without_mutating_the_shipment_or_items(): void
    {
        $this->reliabilityOffice();
        $shipment = $this->editableShipment();
        $payload = $this->shipmentPayload($shipment, [
            'shipment_cost' => '1000000000000', 'package_count' => '4294967296',
            'gross_weight' => '1000000000', 'chargeable_weight' => '999999999.9999',
            'items' => [['product_name' => 'Carton', 'quantity' => '1000000000',
                'declared_value' => '1000000000000', 'net_weight' => '1000000000', 'gross_weight' => '1000000000']],
        ]);
        $this->from('/shipments/'.$shipment->id.'/edit')->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect('/shipments/'.$shipment->id.'/edit')
            ->assertSessionHasErrors(['shipment_cost', 'package_count', 'gross_weight', 'chargeable_weight',
                'items.0.quantity', 'items.0.declared_value', 'items.0.net_weight', 'items.0.gross_weight']);
        $this->assertSame('Original shipment', $shipment->fresh()->identity_name);
        $this->assertSame('Existing carton', $shipment->items()->firstOrFail()->product_name);
    }

    protected function scenario_values_with_excess_precision_are_not_silently_rounded(): void
    {
        $this->reliabilityOffice();
        $shipment = $this->editableShipment();
        $payload = $this->shipmentPayload($shipment, [
            'shipment_cost' => '1.001', 'gross_weight' => '2.0001',
            'items' => [['product_name' => 'Carton', 'quantity' => '1.0001', 'declared_value' => '2.001']],
        ]);
        $this->from('/shipments/'.$shipment->id.'/edit')->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect('/shipments/'.$shipment->id.'/edit')
            ->assertSessionHasErrors(['shipment_cost', 'gross_weight', 'items.0.quantity', 'items.0.declared_value']);
        $this->assertSame('Original shipment', $shipment->fresh()->identity_name);
        $this->assertSame('Existing carton', $shipment->items()->firstOrFail()->product_name);
    }

    protected function scenario_database_boundary_values_and_an_empty_template_row_are_accepted(): void
    {
        $this->reliabilityOffice();
        $shipment = $this->editableShipment();
        $payload = $this->shipmentPayload($shipment, [
            // A newly added form template defaults its own currency to INR,
            // even when the shipment header is USD.
            'currency' => 'USD',
            'shipment_cost' => '999999999999.99', 'package_count' => '4294967295',
            'gross_weight' => '999999999.999', 'chargeable_weight' => '0.001',
            'items' => [
                ['product_name' => 'Carton', 'quantity' => '999999999.999', 'declared_value' => '999999999999.99',
                    'net_weight' => '999999999.999', 'gross_weight' => '0.001'],
                ['product_name' => '', 'project_product_id' => '', 'quantity' => '1', 'unit' => 'pcs', 'currency' => 'INR'],
            ],
        ]);
        $this->put(route('shipments.update', $shipment), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('shipments.show', $shipment))->assertSessionHas('success');
        $this->assertSame('999999999999.99', $shipment->fresh()->shipment_cost);
        $this->assertSame(4294967295, (int) $shipment->fresh()->package_count);
        $this->assertSame('999999999.999', $shipment->items()->firstOrFail()->quantity);
        $this->assertSame(1, $shipment->items()->count());
    }

    protected function scenario_long_shipment_title_and_link_are_saved_to_both_notification_channels(): void
    {
        $this->reliabilityOffice();
        $client = Client::create(['client_number' => 'RELIABILITY-CLIENT', 'company_name' => 'Acme']);
        $number = str_repeat('S', 250);
        $shipment = $this->editableShipment(['shipment_number' => $number, 'client_id' => $client->id, 'show_client_portal' => true]);
        // A legal hostname near the DNS length limit makes real absolute routes exceed 255 characters.
        $root = 'http://'.implode('.', array_fill(0, 4, str_repeat('a', 60)));
        app('url')->forceRootUrl($root);
        try {
            foreach ([['office_alerts', 'title'], ['office_alerts', 'action_url'],
                ['client_portal_notifications', 'title'], ['client_portal_notifications', 'action_url']] as [$table, $column]) {
                $this->assertSame('text', Schema::getColumnType($table, $column), $table.'.'.$column.' must hold generated values');
            }
            $this->from('/shipments/'.$shipment->id)->post(route('shipments.history.store', $shipment), [
                'status' => 'delayed', 'remarks' => 'The forwarder confirmed a delay.', 'notify_client' => '1',
            ])->assertRedirect('/shipments/'.$shipment->id)->assertSessionHas('success');

            $this->assertSame('delayed', $shipment->fresh()->status);
            $this->assertSame(1, $shipment->histories()->count());
            $alert = DB::table('office_alerts')->where('event_key', 'shipment.exception')->first();
            $notification = DB::table('client_portal_notifications')->where('related_id', $shipment->id)->first();
            $this->assertNotNull($alert);
            $this->assertNotNull($notification);
            $this->assertSame($number.' is Delayed', $alert->title);
            $this->assertSame('Shipment '.$number.' is now Delayed', $notification->title);
            $this->assertGreaterThan(255, mb_strlen($alert->action_url));
            $this->assertGreaterThan(255, mb_strlen($notification->action_url));
            $this->assertStringStartsWith($root, $alert->action_url);
            $this->assertStringStartsWith($root, $notification->action_url);
            $short = \App\Services\PublicShareLinks::url($shipment);
            $this->assertSame($short, $notification->action_url);
            $this->assertStringContainsString($short, $notification->message);
        } finally {
            app('url')->forceRootUrl(null);
        }
    }

    protected function scenario_project_exception_keeps_the_full_composed_project_and_step_title(): void
    {
        $user = $this->reliabilityOffice();
        $client = Client::create(['client_number' => 'RELIABILITY-CLIENT', 'company_name' => 'Acme']);
        $number = str_repeat('P', 250);
        $project = Project::forceCreate(['project_number' => $number, 'client_id' => $client->id, 'name' => 'Project',
            'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $user->id);
        $step = $workflow->steps()->orderBy('sort_order')->firstOrFail();
        $step->update(['title' => str_repeat('T', 250)]);
        $this->assertSame('text', Schema::getColumnType('project_exceptions', 'title'));

        app(ProjectExceptionRegistry::class)->synchronize($user->id);

        $exception = DB::table('project_exceptions')->where('project_workflow_step_id', $step->id)->first();
        $this->assertNotNull($exception);
        $this->assertSame($number.' · '.str_repeat('T', 250), $exception->title);
    }

    protected function scenario_invoice_email_with_long_normalised_to_and_cc_is_sent_once_and_fully_recorded(): void
    {
        $this->reliabilityOffice();
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-INVOICE', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'reliability-invoice-token', 'currency' => 'INR', 'total_amount' => 100]);
        $addresses = array_map(fn ($i) => 'u'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'@example.test', range(1, 29));
        $raw = implode(',', $addresses);
        $normalised = implode(', ', $addresses);
        $this->assertLessThanOrEqual(500, strlen($raw));
        $this->assertGreaterThan(500, strlen($normalised));
        $this->assertSame('text', Schema::getColumnType('sales_invoice_sends', 'recipients'));
        $this->assertSame('text', Schema::getColumnType('sales_invoice_sends', 'cc'));

        $this->from('/sales-invoices/'.$invoice->id)->post(route('sales-invoices.email', $invoice), [
            'to' => $raw, 'cc' => $raw, 'subject' => 'Your invoice', 'message' => 'Please find your invoice.', 'mark_sent' => '1',
        ])->assertRedirect('/sales-invoices/'.$invoice->id)->assertSessionHas('success');
        Mail::assertSent(SalesInvoiceMail::class, 1);
        $send = $invoice->sends()->firstOrFail();
        $this->assertSame($normalised, $send->recipients);
        $this->assertSame($normalised, $send->cc);
        $this->assertSame('sent', $send->status);
        $this->assertSame(1, $invoice->sends()->count());
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->total_amount);
    }

    protected function scenario_long_mail_transport_error_cannot_overflow_alert_or_response(): void
    {
        $this->reliabilityOffice();
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-INVOICE', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'reliability-invoice-token', 'currency' => 'INR', 'total_amount' => 100]);
        // Replace the fake only with an in-process transport that always throws.
        Mail::swap(Mail::getFacadeRoot()->manager);
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException(new TransportException(str_repeat('E', 70000)));
        config(['mail.default' => 'smtp']);
        app('mail.manager')->forgetMailers();
        app('mail.manager')->mailer('smtp')->setSymfonyTransport($transport);

        $this->from('/sales-invoices/'.$invoice->id)->post(route('sales-invoices.email', $invoice), [
            'to' => 'a@example.test', 'subject' => 'Your invoice', 'message' => 'Please find your invoice.', 'mark_sent' => '1',
        ])->assertRedirect('/sales-invoices/'.$invoice->id)->assertSessionHas('error');
        $this->assertLessThan(1000, mb_strlen(session('error')));
        $send = $invoice->sends()->firstOrFail();
        $this->assertSame('failed', $send->status);
        $this->assertSame(500, mb_strlen($send->error));
        $this->assertLessThan(1000, mb_strlen(DB::table('office_alerts')->where('event_key', 'invoice.email_failed')->value('body')));
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    protected function scenario_invoice_email_is_not_sent_when_its_initial_audit_write_fails(): void
    {
        $this->reliabilityOffice();
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-INVOICE', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'reliability-invoice-token', 'currency' => 'INR', 'total_amount' => 100]);
        Event::listen('eloquent.creating: '.SalesInvoiceSend::class, fn () => throw new \RuntimeException('Simulated audit insert failure'));

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'a@example.test', 'subject' => 'Your invoice', 'message' => 'Please find your invoice.', 'mark_sent' => '1',
        ])->assertStatus(500);
        Mail::assertNothingSent();
        $this->assertSame(0, $invoice->sends()->count());
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    protected function scenario_invoice_email_result_write_failure_leaves_an_unconfirmed_attempt_not_a_false_success(): void
    {
        $this->reliabilityOffice();
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-INVOICE', 'invoice_type' => 'tax', 'status' => 'draft',
            'public_token' => 'reliability-invoice-token', 'currency' => 'INR', 'total_amount' => 100]);
        Event::listen('eloquent.updating: '.SalesInvoiceSend::class, fn () => throw new \RuntimeException('Simulated audit update failure'));

        $this->post(route('sales-invoices.email', $invoice), [
            'to' => 'a@example.test', 'subject' => 'Your invoice', 'message' => 'Please find your invoice.', 'mark_sent' => '1',
        ])->assertStatus(500);
        Mail::assertSent(SalesInvoiceMail::class, 1);
        $send = $invoice->sends()->firstOrFail();
        $this->assertSame('pending', $send->status);
        $this->assertSame('a@example.test', $send->recipients);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->get(route('sales-invoices.show', $invoice))->assertOk()
            ->assertSee('Invoice email history')->assertSee('Unconfirmed')->assertSee('Check delivery before retrying')
            ->assertDontSee("In the client's inbox");
    }

    protected function scenario_missing_shipment_lines_are_preserved_but_explicitly_cleared_lines_are_removed(): void
    {
        $this->reliabilityOffice();
        $shipment = $this->editableShipment();
        $originalLine = $shipment->items()->firstOrFail();
        $payload = $this->shipmentPayload($shipment);
        unset($payload['items']);

        $this->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect(route('shipments.show', $shipment))->assertSessionHas('success');
        $this->assertSame($originalLine->id, $shipment->items()->first()?->id, 'Omitted lines must not be deleted or recreated.');
        $this->assertSame('Existing carton', $shipment->items()->firstOrFail()->product_name);
        $this->get(route('shipments.edit', $shipment))->assertOk()->assertSee('name="items_present"', false);

        // This is what the editable form submits when the operator removes all rows.
        $payload['items_present'] = '1';
        $this->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect(route('shipments.show', $shipment))->assertSessionHas('success');
        $this->assertSame(0, $shipment->items()->count());
    }

    protected function scenario_dispatch_with_omitted_lines_evaluates_the_stored_lines(): void
    {
        $user = $this->reliabilityOffice();
        $client = Client::create(['client_number' => 'RELIABILITY-CLIENT', 'company_name' => 'Acme']);
        $project = Project::forceCreate(['project_number' => 'RELIABILITY-PROJECT', 'client_id' => $client->id,
            'name' => 'Project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Carton', 'quantity' => 2]);
        $invoice = SalesInvoice::create(['invoice_number' => 'RELIABILITY-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'public_token' => 'reliability-invoice-token', 'client_id' => $client->id, 'project_id' => $project->id,
            'currency' => 'INR', 'total_amount' => 100, 'amount_paid' => 100, 'balance_before_dispatch' => true]);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $user->id);
        $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        $shipment = $this->editableShipment(['shipment_number' => str_repeat('S', 160), 'client_id' => $client->id, 'project_id' => $project->id]);
        $shipment->items()->update(['project_product_id' => $product->id]);
        $payload = $this->shipmentPayload($shipment, ['project_id' => $project->id, 'client_id' => $client->id, 'status' => 'picked_up']);
        unset($payload['items']);

        $this->from('/shipments/'.$shipment->id.'/edit')->put(route('shipments.update', $shipment), $payload)
            ->assertRedirect('/shipments/'.$shipment->id.'/edit')->assertSessionHasErrors('status');
        $evaluation = $workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('Existing carton', data_get($evaluation->details, 'lines.0.product_name'));
        $this->assertSame($product->id, data_get($evaluation->details, 'lines.0.project_product_id'));
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->assertSame(1, $shipment->items()->count());
    }

    protected function scenario_malformed_or_incomplete_shipment_lines_cannot_silently_delete_existing_items(): void
    {
        $this->reliabilityOffice();
        $shipment = $this->editableShipment();
        foreach ([
            ['not-an-array', 'items'],
            [null, 'items'],
            [[['product_name' => 'Carton', 'quantity' => 2], 'not-a-row'], 'items.1'],
            [[['sku' => 'PARTIAL-SKU', 'quantity' => 5]], 'items.0.product_name'],
        ] as [$badItems, $field]) {
            $this->from('/shipments/'.$shipment->id.'/edit')
                ->put(route('shipments.update', $shipment), $this->shipmentPayload($shipment, ['items' => $badItems]))
                ->assertRedirect('/shipments/'.$shipment->id.'/edit')->assertSessionHasErrors($field);
            $this->assertSame('Original shipment', $shipment->fresh()->identity_name);
            $this->assertSame('Existing carton', $shipment->items()->firstOrFail()->product_name);
        }
    }
}
