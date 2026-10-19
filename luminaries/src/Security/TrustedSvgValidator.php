<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Decides whether an SVG document may join a trusted icon collection.
 *
 * This is a validator, not a sanitizer: it never rewrites markup, it lists
 * violations and the file is refused when there is any. It parses the
 * document with libxml (no network, no entity expansion) and applies an
 * element allowlist and attribute rules suited to icons: no scripts, no
 * event handlers, no foreignObject, no embedded or external resources, no
 * style sheets, no animation. It is meant for files that developers commit
 * to the application; it is not a substitute for a maintained sanitizer
 * when SVGs come from end users (Luminaries never accepts uploads).
 */
final class TrustedSvgValidator
{
    public const int MaxBytes = 65536;

    /**
     * Elements that may appear in a trusted icon (lowercase local names).
     */
    private const array Elements = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect',
        'defs', 'symbol', 'use', 'title', 'desc', 'clippath', 'mask',
        'lineargradient', 'radialgradient', 'stop',
    ];

    /**
     * Attributes whose value may only reference a fragment in the same document.
     */
    private const array References = ['href', 'xlink:href'];

    /**
     * @return list<string> Human-readable violations; empty when the SVG is acceptable.
     */
    public function validate(string $contents): array
    {
        if (strlen($contents) > self::MaxBytes) {
            return [sprintf('the file is larger than %d bytes', self::MaxBytes)];
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) === 1) {
            return ['DOCTYPE and ENTITY declarations are not allowed'];
        }

        if (preg_match('/<\?(?!xml\s)/i', $contents) === 1) {
            return ['processing instructions are not allowed'];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement) {
            return ['the file is not well-formed XML'];
        }

        $violations = [];

        if (strtolower($root->localName ?? '') !== 'svg') {
            $violations[] = 'the root element must be <svg>';
        } elseif (! $root->hasAttribute('viewBox')) {
            $violations[] = 'the root <svg> element must declare a viewBox';
        }

        $this->inspect($root, $violations);

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $violations
     */
    private function inspect(DOMNode $node, array &$violations): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $violations[] = 'processing instructions are not allowed';
            }

            if ($child->nodeType === XML_CDATA_SECTION_NODE) {
                $violations[] = 'CDATA sections are not allowed';
            }
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $name = strtolower($node->localName ?? '');

        if (! in_array($name, self::Elements, true)) {
            $violations[] = sprintf('the <%s> element is not allowed', $node->localName);
        }

        foreach ($node->attributes ?? [] as $attribute) {
            $attributeName = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;
            $normalized = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

            if (str_starts_with($attributeName, 'on')) {
                $violations[] = sprintf('the event handler attribute "%s" is not allowed', $attribute->nodeName);

                continue;
            }

            if ($attributeName === 'style') {
                $violations[] = 'inline style attributes are not allowed; use presentation attributes';

                continue;
            }

            if (in_array($attributeName, self::References, true) && ! str_starts_with($value, '#')) {
                $violations[] = sprintf('the %s attribute may only reference a fragment (#id) in the same file', $attribute->nodeName);

                continue;
            }

            if (str_contains($normalized, 'javascript:') || str_contains($normalized, 'data:') || str_contains($normalized, 'vbscript:')) {
                $violations[] = sprintf('the attribute "%s" contains a script or data URL', $attribute->nodeName);

                continue;
            }

            if (preg_match('/url\((?!\s*[\'"]?#)/i', $value) === 1) {
                $violations[] = sprintf('the attribute "%s" references an external resource', $attribute->nodeName);
            }
        }

        foreach ($node->childNodes as $child) {
            $this->inspect($child, $violations);
        }
    }
}
