<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DiscoveryRuntimeFallbackUiTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return ['Persian' => ['fa', '/'], 'Arabic' => ['ar', '/ar'], 'English' => ['en', '/en']];
    }

    #[DataProvider('locales')]
    public function test_enhanced_home_has_a_localised_hidden_runtime_fallback_outside_the_map_canvas(string $locale, string $path): void
    {
        $response = $this->get($path)->assertOk();
        $this->assertTrue($response->viewData('discovery')['vite_ready']);
        $html = $response->getContent();
        $xpath = $this->xpath($html);
        $fallbacks = $xpath->query('//p[@data-discovery-map-fallback]');
        $this->assertSame(1, $fallbacks->length);
        $fallback = $fallbacks->item(0);
        $this->assertTrue($fallback->hasAttribute('hidden'));
        $this->assertSame('status', $fallback->getAttribute('role'));
        $this->assertSame('discovery-map-fallback', $fallback->getAttribute('class'));
        $this->assertSame(__('site.discovery.map_unavailable', [], $locale), $fallback->textContent);
        $this->assertSame(0, $xpath->query('//*[@data-discovery-map]//*[@data-discovery-map-fallback]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-discovery-map]')->length);
        $this->assertStringContainsString('data-endpoint="'.url('/api/v1/public/discovery/clinics').'"', $html);
        $this->assertSame(1, $xpath->query('//form[@data-discovery-form]')->length);
        $this->assertSame(1, $xpath->query('//ul[@data-discovery-list]')->length);
        $this->assertSame(0, $xpath->query('//*[@style or @onclick or @onload]')->length);
    }

    #[DataProvider('locales')]
    public function test_non_enhanced_partial_keeps_visible_server_fallback_without_a_canvas_or_runtime_target(string $locale, string $path): void
    {
        $response = $this->get($path)->assertOk();
        $discovery = $response->viewData('discovery');
        $discovery['vite_ready'] = false;
        $html = view('public.partials.discovery-map', ['locale' => $locale, 'discovery' => $discovery])->render();
        $xpath = $this->xpath($html);
        $fallbacks = $xpath->query('//p[@class="discovery-map-fallback"]');
        $this->assertSame(1, $fallbacks->length);
        $this->assertFalse($fallbacks->item(0)->hasAttribute('hidden'));
        $this->assertSame(__('site.discovery.map_unavailable', [], $locale), $fallbacks->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//*[@data-discovery-map or @data-discovery-map-fallback]')->length);
        $this->assertSame(1, $xpath->query('//ul[@data-discovery-list]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-discovery-form]')->length);
    }

    public function test_shared_referrals_page_has_the_same_single_runtime_fallback_and_no_payload_renders_nothing(): void
    {
        $html = $this->get('/en/referrals')->assertOk()->getContent();
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-discovery-map-fallback]')->length);
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
