<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DiscoveryManualCopyUiTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return ['Persian' => ['fa', '/'], 'Arabic' => ['ar', '/ar'], 'English' => ['en', '/en']];
    }

    #[DataProvider('locales')]
    public function test_enhanced_home_has_an_accessible_localised_hidden_manual_destination_field(string $locale, string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $wrappers = $xpath->query('//*[@data-discovery-copy-fallback]');
        $this->assertSame(1, $wrappers->length);
        $this->assertTrue($wrappers->item(0)->hasAttribute('hidden'));
        $this->assertSame(2, $xpath->query('//*[@data-discovery-geo or @data-discovery-geo-clear]')->length);
        $this->assertSame(2, $xpath->query('//*[@hidden and (@data-discovery-geo or @data-discovery-geo-clear)]')->length);
        $fields = $xpath->query('//*[@data-discovery-copy-fallback]/textarea[@data-discovery-copy-value]');
        $this->assertSame(1, $fields->length);
        $field = $fields->item(0);
        $this->assertTrue($field->hasAttribute('readonly'));
        $this->assertFalse($field->hasAttribute('name'));
        $this->assertFalse($field->hasAttribute('disabled'));
        $this->assertSame('ltr', $field->getAttribute('dir'));
        $this->assertSame('', $field->textContent);
        $label = $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->item(0);
        $this->assertNotNull($label);
        $this->assertSame(__('site.discovery.destination_manual_copy_label', [], $locale), $label->textContent);
        $help = $xpath->query('//*[@id="'.$field->getAttribute('aria-describedby').'"]')->item(0);
        $this->assertNotNull($help);
        $this->assertSame(__('site.discovery.destination_manual_copy_help', [], $locale), $help->textContent);
        $copy = json_decode($xpath->query('//script[@data-discovery-copy]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        foreach (['destination_copy_failed', 'destination_manual_copy_label', 'destination_manual_copy_help'] as $key) {
            $this->assertSame(__('site.discovery.'.$key, [], $locale), $copy[$key]);
            $this->assertStringNotContainsString('site.discovery.', $copy[$key]);
        }
        $this->assertSame(0, $xpath->query('//*[@style or @onclick or @oninput or @onload]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-discovery-form]')->length);
        $this->assertSame(1, $xpath->query('//ul[@data-discovery-list]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-discovery-map-fallback]')->length);
        $this->assertStringContainsString('href="/assets/discovery-copy.css?v=20261003-geo"', $html);
    }

    #[DataProvider('locales')]
    public function test_non_enhanced_server_partial_omits_manual_copy_controls_and_stylesheet(string $locale, string $path): void
    {
        $discovery = $this->get($path)->assertOk()->viewData('discovery');
        $discovery['vite_ready'] = false;
        $html = view('public.partials.discovery-map', ['locale' => $locale, 'discovery' => $discovery])->render();
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//*[@data-discovery-copy-fallback or @data-discovery-copy-value]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-discovery-geo or @data-discovery-geo-clear]')->length);
        $this->assertStringNotContainsString('/assets/discovery-copy.css', $html);
        $this->assertSame(1, $xpath->query('//form[@data-discovery-form]')->length);
        $this->assertSame(1, $xpath->query('//ul[@data-discovery-list]')->length);
    }

    public function test_referrals_reuses_the_manual_field_and_css_mirror_and_empty_projection_stays_empty(): void
    {
        $html = $this->get('/en/referrals')->assertOk()->getContent();
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-discovery-copy-fallback]')->length);
        $this->assertFileExists(base_path('public/assets/discovery-copy.css'));
        $this->assertSame(file_get_contents(base_path('public/assets/discovery-copy.css')), file_get_contents(base_path('../deployment/webroot/assets/discovery-copy.css')));
        $css = file_get_contents(base_path('public/assets/discovery-copy.css'));
        $this->assertStringContainsString('[data-discovery-root] [data-discovery-geo][hidden],[data-discovery-root] [data-discovery-geo-clear][hidden]{display:none!important}', $css);
        $this->assertSame('', trim(view('public.partials.discovery-map', ['locale' => 'en', 'discovery' => null])->render()));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
