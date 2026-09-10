<?php

namespace App\Domain\CMS\Services;

final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'img', 'figure',
        'figcaption', 'span', 'div', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    /** @var list<string> */
    private const ALLOWED_ATTRS = ['href', 'src', 'alt', 'title', 'class', 'width', 'height', 'loading'];

    public function sanitize(string $html): string
    {
        // Strip HTML comments (incl. IE conditional-comment XSS vectors) before parsing.
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $html = '<?xml encoding="UTF-8">'.$html;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $this->stripDisallowedNodes($dom);
        $this->stripDisallowedAttrs($dom);
        $this->enforceSafeLinks($dom);

        return trim($dom->saveHTML());
    }

    private function stripDisallowedNodes(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $nodes = iterator_to_array($xpath->query('//*'));
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                if ($tag === 'script' || $tag === 'style' || $tag === 'iframe' || $tag === 'object') {
                    $node->parentNode?->removeChild($node);
                } else {
                    $parent = $node->parentNode;
                    while ($node->firstChild) {
                        $parent?->insertBefore($node->firstChild, $node);
                    }
                    $parent?->removeChild($node);
                }
            }
        }
    }

    private function stripDisallowedAttrs(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $nodes = iterator_to_array($xpath->query('//*'));
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $attrs = [];
            foreach ($node->attributes as $attr) {
                $attrs[] = $attr->name;
            }
            foreach ($attrs as $name) {
                $lower = strtolower($name);
                if (! in_array($lower, self::ALLOWED_ATTRS, true) || str_starts_with($lower, 'on')) {
                    $node->removeAttribute($name);
                }
            }
        }
    }

    private function enforceSafeLinks(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//a[@href]')) as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $href = $node->getAttribute('href');
            if (preg_match('/^\s*(javascript|data|vbscript):/i', $href)) {
                $node->removeAttribute('href');
            }
        }
        foreach (iterator_to_array($xpath->query('//img[@src]')) as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $src = $node->getAttribute('src');
            if (preg_match('/^\s*(javascript|vbscript|data):/i', $src)) {
                $node->removeAttribute('src');
            }
        }
    }
}
