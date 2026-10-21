<?php

namespace Tests\Unit;

use App\Models\PackagingList;
use App\Support\PrintCss;
use PHPUnit\Framework\TestCase;

class PackagingPrintSettingsTest extends TestCase
{
    public function test_existing_lists_default_to_portrait_and_the_original_footer(): void
    {
        $settings = (new PackagingList)->printSettings();
        $this->assertSame('portrait', $settings['orientation']);
        $this->assertSame('', $settings['header_text']);
        $this->assertFalse($settings['show_page_numbers']);
        $this->assertStringContainsString('computer-generated packaging list', $settings['footer_text']);
    }

    public function test_settings_are_list_specific_cast_and_preserve_intentionally_blank_text(): void
    {
        $list = new PackagingList(['print_settings' => [
            'orientation' => 'landscape', 'header_text' => 'Shipment copy',
            'footer_text' => '', 'show_page_numbers' => true,
        ]]);
        $copy = new PackagingList;
        $copy->setRawAttributes($list->getAttributes());
        $this->assertSame($list->printSettings(), $copy->printSettings());
        $this->assertSame('', $copy->printSettings()['footer_text']);
        $this->assertSame('landscape', $copy->printSettings()['orientation']);
        $this->assertTrue($copy->printSettings()['show_page_numbers']);
        $this->assertSame('portrait', (new PackagingList)->printSettings()['orientation']);
        $copy->print_settings = ['orientation' => 'landscape; margin:0; } body {display:none}'];
        $this->assertSame('portrait', $copy->printSettings()['orientation']);
    }

    public function test_css_text_cannot_close_a_style_element_or_inject_rules(): void
    {
        $text = "\"; } </style><script>alert(1)</script>\nગુજરાત ✓";
        $encoded = PrintCss::text($text);
        $this->assertMatchesRegularExpression('/^"(?:\\\\[0-9a-f]+ )*"$/', $encoded);
        $this->assertStringNotContainsString('</style>', $encoded);
        $decoded = preg_replace_callback('/\\\\([0-9a-f]+) /', fn ($match) => mb_chr(hexdec($match[1]), 'UTF-8'), substr($encoded, 1, -1));
        $this->assertSame($text, $decoded);
        $this->assertSame('""', PrintCss::text(''));
    }
}
