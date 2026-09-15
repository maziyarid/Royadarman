<?php

namespace Tests\Feature;

use Tests\TestCase;

final class TranslationParityTest extends TestCase
{
    /** @return list<string> */
    private const FILES = ['panel', 'network', 'ui', 'site', 'request', 'panel_case', 'auth_ui'];

    public function test_fa_ar_en_translation_files_have_matching_keys(): void
    {
        foreach (self::FILES as $file) {
            $en = $this->flatten(require lang_path('en/'.$file.'.php'));
            foreach (['fa', 'ar'] as $locale) {
                $other = $this->flatten(require lang_path($locale.'/'.$file.'.php'));
                $this->assertSame(
                    array_keys($en),
                    array_keys($other),
                    $file.'.php key mismatch for '.$locale,
                );
            }
        }
    }

    /** @param array<string, mixed> $items */
    private function flatten(array $items, string $prefix = ''): array
    {
        $out = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $out += $this->flatten($value, $path);
            } else {
                $out[$path] = $value;
            }
        }

        return $out;
    }
}
