<?php

declare(strict_types=1);

namespace Gcego\Testing\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;

/**
 * Parses rendered markup for assertions and accessibility checks.
 *
 * Fragments are wrapped in a document; HTML5 elements parse without
 * warnings. Selectors are either XPath (starting with "/" or "(") or a
 * small CSS subset: tag, #id, .class, [attr], [attr="value"], combined
 * ("button.primary[type=submit]") and joined by spaces for descendants.
 */
final class Html
{
    private function __construct(public readonly DOMDocument $document, public readonly DOMXPath $xpath) {}

    public static function parse(string $html): self
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $markup = stripos($html, '<html') === false ? '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>' : $html;

        if (stripos($markup, 'charset') === false) {
            $markup = '<?xml encoding="utf-8">'.$markup;
        }

        $document->loadHTML($markup, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new self($document, new DOMXPath($document));
    }

    /**
     * @return list<DOMElement>
     */
    public function select(string $selector): array
    {
        $nodes = $this->xpath->query(self::toXPath($selector));
        $elements = [];

        if ($nodes === false) {
            throw new InvalidArgumentException(sprintf('The selector "%s" is not valid.', $selector));
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    public function byId(string $id): ?DOMElement
    {
        foreach ($this->select('//*[@id]') as $element) {
            if ($element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    /**
     * The visible text: whitespace collapsed, aria-hidden subtrees and
     * script/style/template content left out.
     */
    public static function text(DOMNode $node): string
    {
        if ($node instanceof DOMElement && (in_array(strtolower($node->tagName), ['script', 'style', 'template'], true) || $node->getAttribute('aria-hidden') === 'true')) {
            return '';
        }

        if (! $node->hasChildNodes()) {
            return $node->nodeType === XML_TEXT_NODE ? trim((string) preg_replace('/\s+/u', ' ', (string) $node->textContent)) : '';
        }

        $parts = [];

        foreach ($node->childNodes as $child) {
            $text = self::text($child);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    public function bodyText(): string
    {
        $body = $this->document->getElementsByTagName('body')->item(0);

        return $body === null ? '' : self::text($body);
    }

    /**
     * An element's opening tag, for failure messages.
     */
    public static function snippet(DOMElement $element): string
    {
        $attributes = '';

        foreach ($element->attributes as $attribute) {
            $attributes .= ' '.$attribute->nodeName.'="'.mb_strimwidth($attribute->nodeValue ?? '', 0, 60, '…').'"';
        }

        return '<'.$element->tagName.$attributes.'>';
    }

    public static function toXPath(string $selector): string
    {
        $selector = trim($selector);

        if ($selector === '') {
            throw new InvalidArgumentException('The selector is empty.');
        }

        if ($selector[0] === '/' || $selector[0] === '(') {
            return $selector;
        }

        $steps = [];

        foreach (preg_split('/\s+(?![^\[]*\])/', $selector) ?: [] as $compound) {
            if (! preg_match('/^([a-zA-Z][a-zA-Z0-9-]*|\*)?((?:[#.][A-Za-z0-9_:-]+|\[[^\]]+\])*)$/', $compound, $match)) {
                throw new InvalidArgumentException(sprintf('The selector "%s" is not supported; use XPath.', $selector));
            }

            $conditions = [];
            preg_match_all('/#([A-Za-z0-9_:-]+)|\.([A-Za-z0-9_:-]+)|\[([A-Za-z0-9_:-]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\]]*)))?\]/', $match[2], $parts, PREG_SET_ORDER);

            foreach ($parts as $part) {
                $attribute = $part[3] ?? '';
                $conditions[] = match (true) {
                    ($part[1] ?? '') !== '' => '@id='.self::literal($part[1]),
                    ($part[2] ?? '') !== '' => 'contains(concat(" ", normalize-space(@class), " "), '.self::literal(' '.$part[2].' ').')',
                    count($part) > 4 => '@'.$attribute.'='.self::literal(implode('', array_slice($part, 4))),
                    default => '@'.$attribute,
                };
            }

            $steps[] = ($match[1] !== '' ? strtolower($match[1]) : '*').implode('', array_map(static fn (string $condition): string => '['.$condition.']', $conditions));
        }

        return '//'.implode('//', $steps);
    }

    private static function literal(string $value): string
    {
        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat("'.str_replace('"', '", \'"\', "', $value).'")';
    }
}
