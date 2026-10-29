<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Tests\Support\ErpReliabilityScenarios;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Full migration chain, then real HTTP/service journeys; SQLite remains length-permissive. */
class FullSchemaReliabilityTest extends TestCase
{
    use ErpReliabilityScenarios;
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        $this->prepareReliabilityScenario();
    }

    public function test_full_schema_dispatch_rejects_with_a_complete_long_evaluation_on_both_http_paths(): void
    {
        $this->scenario_full_schema_dispatch_rejects_with_a_complete_long_evaluation_on_both_http_paths();
    }

    public function test_full_schema_normal_project_planning_save_succeeds_and_evaluates_readiness(): void
    {
        $this->scenario_full_schema_normal_project_planning_save_succeeds_and_evaluates_readiness();
    }

    public function test_out_of_range_shipment_values_are_rejected_without_mutating_the_shipment_or_items(): void
    {
        $this->scenario_out_of_range_shipment_values_are_rejected_without_mutating_the_shipment_or_items();
    }

    public function test_values_with_excess_precision_are_not_silently_rounded(): void
    {
        $this->scenario_values_with_excess_precision_are_not_silently_rounded();
    }

    public function test_database_boundary_values_and_an_empty_template_row_are_accepted(): void
    {
        $this->scenario_database_boundary_values_and_an_empty_template_row_are_accepted();
    }

    public function test_long_shipment_title_and_link_are_saved_to_both_notification_channels(): void
    {
        $this->scenario_long_shipment_title_and_link_are_saved_to_both_notification_channels();
    }

    public function test_project_exception_keeps_the_full_composed_project_and_step_title(): void
    {
        $this->scenario_project_exception_keeps_the_full_composed_project_and_step_title();
    }

    public function test_invoice_email_with_long_normalised_to_and_cc_is_sent_once_and_fully_recorded(): void
    {
        $this->scenario_invoice_email_with_long_normalised_to_and_cc_is_sent_once_and_fully_recorded();
    }

    public function test_long_mail_transport_error_cannot_overflow_alert_or_response(): void
    {
        $this->scenario_long_mail_transport_error_cannot_overflow_alert_or_response();
    }

    public function test_invoice_email_is_not_sent_when_its_initial_audit_write_fails(): void
    {
        $this->scenario_invoice_email_is_not_sent_when_its_initial_audit_write_fails();
    }

    public function test_invoice_email_result_write_failure_leaves_an_unconfirmed_attempt_not_a_false_success(): void
    {
        $this->scenario_invoice_email_result_write_failure_leaves_an_unconfirmed_attempt_not_a_false_success();
    }

    public function test_malformed_or_incomplete_shipment_lines_cannot_silently_delete_existing_items(): void
    {
        $this->scenario_malformed_or_incomplete_shipment_lines_cannot_silently_delete_existing_items();
    }
    public function test_missing_shipment_lines_are_preserved_but_explicitly_cleared_lines_are_removed(): void
    {
        $this->scenario_missing_shipment_lines_are_preserved_but_explicitly_cleared_lines_are_removed();
    }

    public function test_dispatch_with_omitted_lines_evaluates_the_stored_lines(): void
    {
        $this->scenario_dispatch_with_omitted_lines_evaluates_the_stored_lines();
    }

}
