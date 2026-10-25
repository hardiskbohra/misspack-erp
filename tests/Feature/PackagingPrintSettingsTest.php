<?php

namespace Tests\Feature;

use App\Http\Controllers\PackagingListController;
use App\Models\PackagingList;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackagingPrintSettingsTest extends TestCase
{
    private function record(): PackagingList
    {
        $list = new class extends PackagingList
        {
            public array $saved = [];

            public function update(array $attributes = [], array $options = [])
            {
                $this->saved = $attributes;
                return true;
            }
        };
        $list->id = 13;
        return $list;
    }

    public function test_saving_only_changes_print_settings_and_returns_to_the_print_page(): void
    {
        $list = $this->record();
        $request = Request::create('/packaging-lists/13/print-settings', 'PATCH', [
            'orientation' => 'landscape', 'header_text' => null, 'footer_text' => null,
            'show_page_numbers' => '1', 'status' => 'dispatched', 'print_columns' => ['cbm'],
        ]);
        $response = (new PackagingListController)->updatePrintSettings($request, $list);
        $this->assertSame(['print_settings' => [
            'orientation' => 'landscape', 'header_text' => '', 'footer_text' => '', 'show_page_numbers' => true,
        ]], $list->saved);
        $this->assertSame(route('packaging-lists.print', $list), $response->getTargetUrl());
    }

    public function test_print_view_outputs_saved_orientation_and_safe_running_text(): void
    {
        foreach (['portrait', 'landscape'] as $orientation) {
            $list = new PackagingList(['packing_number' => 'MP/PL/26-27/013', 'status' => 'draft', 'transport_mode' => 'air',
                'total_cartons' => 0, 'print_settings' => ['orientation' => $orientation,
                    'header_text' => '</style><script>unsafe()</script>', 'footer_text' => 'Shipment copy', 'show_page_numbers' => true]]);
            $list->id = 13;
            $list->setRelation('cartons', new \Illuminate\Support\Collection);
            $list->setRelation('shipment', null);
            $list->setRelation('project', null);
            $html = view('packaging_lists.print', ['list' => $list, 'sections' => collect(), 'organisation' => null, 'onLetterhead' => false, 'errors' => new \Illuminate\Support\ViewErrorBag])->render();
            $this->assertStringContainsString('data-orientation="'.$orientation.'"', $html);
            $this->assertStringContainsString('size: A4 '.$orientation, $html);
            $this->assertStringContainsString('counter(page) " of " counter(pages)', $html);
            $this->assertStringNotContainsString('<script>unsafe()</script>', $html);
            $this->assertStringContainsString(e('</style><script>unsafe()</script>'), $html);
            $this->assertGreaterThan(strpos($html, 'packaging-list-print-settings.css'), strpos($html, 'id="plPageStyle"'));
        }
    }

    public function test_invalid_settings_never_mutate_the_list(): void
    {
        $valid = ['orientation' => 'portrait', 'header_text' => '', 'footer_text' => '', 'show_page_numbers' => '0'];
        foreach ([
            ['orientation' => 'a3'], ['orientation' => 'landscape; color:red'],
            ['header_text' => str_repeat('x', 161)], ['footer_text' => str_repeat('x', 241)],
            ['show_page_numbers' => 'yes'], ['header_text' => ['invalid']],
        ] as $invalid) {
            $list = $this->record();
            try {
                (new PackagingListController)->updatePrintSettings(Request::create('/', 'PATCH', array_replace($valid, $invalid)), $list);
                $this->fail('Invalid print settings must be rejected.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey(array_key_first($invalid), $error->errors());
                $this->assertSame([], $list->saved);
            }
        }
    }
}
