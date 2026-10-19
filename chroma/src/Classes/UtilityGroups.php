<?php

declare(strict_types=1);

namespace Gcego\Chroma\Classes;

use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\Token;

/**
 * The documented conflict table used by ClassMerger.
 *
 * Maps a Tailwind utility (without variants) to the CSS concern it sets, and
 * lists the narrower concerns a shorthand overrides (p-4 overrides px/py/pt/…;
 * rounded-lg overrides rounded-t/…). Utilities not covered here have no group
 * and are never removed by merging, which is the safe failure mode: an
 * unknown utility can at worst coexist with another, never be dropped wrongly.
 *
 * This is deliberately not a reimplementation of Tailwind's own ordering;
 * see docs/tailwind.md for the exact coverage.
 */
final readonly class UtilityGroups
{
    private const array Exact = [
        'block' => 'display',
        'inline-block' => 'display',
        'inline' => 'display',
        'flex' => 'display',
        'inline-flex' => 'display',
        'grid' => 'display',
        'inline-grid' => 'display',
        'contents' => 'display',
        'table' => 'display',
        'inline-table' => 'display',
        'table-row' => 'display',
        'table-cell' => 'display',
        'flow-root' => 'display',
        'list-item' => 'display',
        'hidden' => 'display',
        'static' => 'position',
        'fixed' => 'position',
        'absolute' => 'position',
        'relative' => 'position',
        'sticky' => 'position',
        'visible' => 'visibility',
        'invisible' => 'visibility',
        'collapse' => 'visibility',
        'italic' => 'font-style',
        'not-italic' => 'font-style',
        'underline' => 'text-decoration-line',
        'overline' => 'text-decoration-line',
        'line-through' => 'text-decoration-line',
        'no-underline' => 'text-decoration-line',
        'uppercase' => 'text-transform',
        'lowercase' => 'text-transform',
        'capitalize' => 'text-transform',
        'normal-case' => 'text-transform',
        'antialiased' => 'font-smoothing',
        'subpixel-antialiased' => 'font-smoothing',
        'sr-only' => 'screen-reader',
        'not-sr-only' => 'screen-reader',
        'truncate' => 'text-overflow',
        'text-ellipsis' => 'text-overflow',
        'text-clip' => 'text-overflow',
        'grow' => 'flex-grow',
        'shrink' => 'flex-shrink',
        'isolate' => 'isolation',
        'isolation-auto' => 'isolation',
        'container' => 'container',
    ];

    private const array Sides = ['p' => 'padding', 'm' => 'margin'];

    private const array SideRefinements = [
        '' => ['x', 'y', 't', 'r', 'b', 'l', 's', 'e'],
        'x' => ['l', 'r', 's', 'e'],
        'y' => ['t', 'b'],
    ];

    private const array FontSizes = ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', '8xl', '9xl'];

    private const array FontWeights = ['thin', 'extralight', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black'];

    private const array ShadowSizes = ['2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', 'none', 'inner'];

    private const array RadiusSides = ['t', 'r', 'b', 'l', 's', 'e', 'tl', 'tr', 'br', 'bl', 'ss', 'se', 'es', 'ee'];

    private const array RadiusRefinements = [
        '' => ['t', 'r', 'b', 'l', 's', 'e', 'tl', 'tr', 'br', 'bl', 'ss', 'se', 'es', 'ee'],
        't' => ['tl', 'tr'],
        'r' => ['tr', 'br'],
        'b' => ['br', 'bl'],
        'l' => ['tl', 'bl'],
        's' => ['ss', 'es'],
        'e' => ['se', 'ee'],
    ];

    private const array BorderSides = ['x', 'y', 't', 'r', 'b', 'l', 's', 'e'];

    private const array Simple = [
        'z' => 'z-index',
        'order' => 'order',
        'opacity' => 'opacity',
        'basis' => 'flex-basis',
        'grow' => 'flex-grow',
        'shrink' => 'flex-shrink',
        'grid-cols' => 'grid-template-columns',
        'grid-rows' => 'grid-template-rows',
        'col' => 'grid-column',
        'col-span' => 'grid-column',
        'row' => 'grid-row',
        'row-span' => 'grid-row',
        'auto-cols' => 'grid-auto-columns',
        'auto-rows' => 'grid-auto-rows',
        'grid-flow' => 'grid-auto-flow',
        'justify-items' => 'justify-items',
        'justify-self' => 'justify-self',
        'justify' => 'justify-content',
        'items' => 'align-items',
        'self' => 'align-self',
        'content' => 'align-content',
        'place-content' => 'place-content',
        'place-items' => 'place-items',
        'place-self' => 'place-self',
        'leading' => 'line-height',
        'tracking' => 'letter-spacing',
        'cursor' => 'cursor',
        'whitespace' => 'white-space',
        'break' => 'word-break',
        'object' => 'object-fit',
        'aspect' => 'aspect-ratio',
        'fill' => 'fill',
        'stroke' => 'stroke',
        'pointer-events' => 'pointer-events',
        'select' => 'user-select',
        'duration' => 'transition-duration',
        'ease' => 'transition-timing-function',
        'delay' => 'transition-delay',
        'decoration' => 'text-decoration-color',
        'list' => 'list-style-type',
        'align' => 'vertical-align',
        'line-clamp' => 'line-clamp',
        'indent' => 'text-indent',
        'scale' => 'scale',
        'rotate' => 'rotate',
        'animate' => 'animation',
        'ring-offset' => 'ring-offset-width',
        'outline-offset' => 'outline-offset',
        'columns' => 'columns',
        'size' => 'size',
        'w' => 'width',
        'min-w' => 'min-width',
        'max-w' => 'max-width',
        'h' => 'height',
        'min-h' => 'min-height',
        'max-h' => 'max-height',
        'top' => 'inset-top',
        'right' => 'inset-right',
        'bottom' => 'inset-bottom',
        'left' => 'inset-left',
        'start' => 'inset-start',
        'end' => 'inset-end',
        'gap-x' => 'gap-x',
        'gap-y' => 'gap-y',
        'space-x' => 'space-x',
        'space-y' => 'space-y',
        'divide-x' => 'divide-x',
        'divide-y' => 'divide-y',
        'overflow-x' => 'overflow-x',
        'overflow-y' => 'overflow-y',
    ];

    private const array SimpleRefinements = [
        'size' => ['width', 'height'],
        'gap' => ['gap-x', 'gap-y'],
        'overflow' => ['overflow-x', 'overflow-y'],
        'inset' => ['inset-x', 'inset-y', 'inset-top', 'inset-right', 'inset-bottom', 'inset-left', 'inset-start', 'inset-end'],
        'inset-x' => ['inset-left', 'inset-right', 'inset-start', 'inset-end'],
        'inset-y' => ['inset-top', 'inset-bottom'],
        'flex' => ['flex-grow', 'flex-shrink', 'flex-basis'],
    ];

    /**
     * @var list<string>
     */
    private array $colorTokens;

    /**
     * @var list<string>
     */
    private array $prefixedColorTokens;

    /**
     * Named GCEGO color tokens (gcego/swatches): `bg-surface` and `bg-gcego-surface` are
     * colors, `shadow-gcego-card` is not.
     *
     * @param  list<string>|null  $colorTokens  Canonical names (bg-<name>); defaults to the Swatches catalog.
     * @param  list<string>|null  $prefixedColorTokens  gcego-* names (bg-gcego-<name>); defaults to SemanticTokens::GcegoPrefixed.
     */
    public function __construct(?array $colorTokens = null, ?array $prefixedColorTokens = null)
    {
        $this->colorTokens = $colorTokens ?? array_values(array_map(static fn (Token $token): string => $token->name, array_filter(SemanticTokens::all(), static fn (Token $token): bool => $token->hasCanonicalUtility())));
        $this->prefixedColorTokens = $prefixedColorTokens ?? SemanticTokens::GcegoPrefixed;
    }

    /**
     * @return array{0: string, 1: list<string>}|null [group, groups it overrides]
     */
    public function classify(string $utility): ?array
    {
        $utility = ltrim($utility, '-');

        if (isset(self::Exact[$utility])) {
            return [self::Exact[$utility], []];
        }

        if (str_starts_with($utility, '[') && str_contains($utility, ':')) {
            return ['arbitrary:'.substr($utility, 1, (int) strpos($utility, ':') - 1), []];
        }

        return $this->spacing($utility)
            ?? $this->radius($utility)
            ?? $this->border($utility)
            ?? $this->family($utility)
            ?? $this->simple($utility);
    }

    /**
     * @return array{0: string, 1: list<string>}|null
     */
    private function spacing(string $utility): ?array
    {
        if (preg_match('/^(p|m)([xytrblse]?)-(.+)$/', $utility, $match) !== 1) {
            return null;
        }

        $property = self::Sides[$match[1]];
        $refines = array_map(static fn (string $side): string => $property.'-'.$side, self::SideRefinements[$match[2]] ?? []);

        return [$match[2] === '' ? $property : $property.'-'.$match[2], $refines];
    }

    /**
     * @return array{0: string, 1: list<string>}|null
     */
    private function radius(string $utility): ?array
    {
        if ($utility !== 'rounded' && ! str_starts_with($utility, 'rounded-')) {
            return null;
        }

        $parts = explode('-', $utility, 3);
        $side = isset($parts[1]) && in_array($parts[1], self::RadiusSides, true) ? $parts[1] : '';
        $refines = array_map(static fn (string $narrower): string => 'radius-'.$narrower, self::RadiusRefinements[$side] ?? []);

        return [$side === '' ? 'radius' : 'radius-'.$side, $refines];
    }

    /**
     * @return array{0: string, 1: list<string>}|null
     */
    private function border(string $utility): ?array
    {
        if ($utility !== 'border' && ! str_starts_with($utility, 'border-')) {
            return null;
        }

        $rest = substr($utility, 7);
        $side = '';

        if ($rest !== '') {
            $segments = explode('-', $rest, 2);

            if (in_array($segments[0], self::BorderSides, true)) {
                $side = $segments[0];
                $rest = $segments[1] ?? '';
            }
        }

        $rest = (string) $rest;
        $suffix = $side === '' ? '' : '-'.$side;
        $widthRefines = $side === '' ? array_map(static fn (string $narrower): string => 'border-width-'.$narrower, self::BorderSides) : [];

        return match (true) {
            $rest === '' || preg_match('/^(\d+|\[[\d.]+(px|rem|em)\]|\[length:.+\])$/', $rest) === 1 => ['border-width'.$suffix, $widthRefines],
            in_array($rest, ['solid', 'dashed', 'dotted', 'double', 'hidden', 'none'], true) => ['border-style', []],
            in_array($rest, ['collapse', 'separate'], true) => ['border-collapse', []],
            $this->isColor($rest) => ['border-color'.$suffix, $side === '' ? array_map(static fn (string $narrower): string => 'border-color-'.$narrower, self::BorderSides) : []],
            default => null,
        };
    }

    /**
     * Families whose values mix several CSS properties (text-sm vs text-slate-500).
     *
     * @return array{0: string, 1: list<string>}|null
     */
    private function family(string $utility): ?array
    {
        if (preg_match('/^(text|bg|font|shadow|ring|outline|flex|overflow|transition|gap|inset)(?:-(.+))?$/', $utility, $match) !== 1) {
            return null;
        }

        $value = $match[2] ?? '';

        return match ($match[1]) {
            'text' => match (true) {
                in_array(explode('/', $value)[0], self::FontSizes, true) || preg_match('/^\[(length:|[\d.]+(px|rem|em))/', $value) === 1 => ['font-size', ['line-height']],
                in_array($value, ['left', 'center', 'right', 'justify', 'start', 'end'], true) => ['text-align', []],
                in_array($value, ['wrap', 'nowrap', 'balance', 'pretty'], true) => ['text-wrap', []],
                $this->isColor($value) => ['text-color', []],
                default => null,
            },
            'bg' => match (true) {
                in_array($value, ['fixed', 'local', 'scroll'], true) => ['background-attachment', []],
                in_array($value, ['auto', 'cover', 'contain'], true) => ['background-size', []],
                in_array($value, ['center', 'top', 'bottom', 'left', 'right', 'left-top', 'left-bottom', 'right-top', 'right-bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right'], true) => ['background-position', []],
                str_starts_with($value, 'repeat') || $value === 'no-repeat' => ['background-repeat', []],
                $value === 'none' || str_starts_with($value, 'linear-') || str_starts_with($value, 'radial') || str_starts_with($value, 'conic') || str_starts_with($value, 'gradient-') => ['background-image', []],
                $this->isColor($value) => ['background-color', []],
                default => null,
            },
            'font' => in_array($value, self::FontWeights, true) || preg_match('/^\[\d+\]$/', $value) === 1 ? ['font-weight', []] : ($value === '' ? null : ['font-family', []]),
            'shadow' => $value === '' || in_array($value, self::ShadowSizes, true) || ! $this->isColor($value) ? ['box-shadow', []] : ['box-shadow-color', []],
            'ring' => match (true) {
                $value === '' || preg_match('/^(\d+|\[[\d.]+px\])$/', $value) === 1 => ['ring-width', []],
                $value === 'inset' => ['ring-inset', []],
                str_starts_with($value, 'offset-') => [preg_match('/^offset-(\d+|\[[\d.]+px\])$/', $value) === 1 ? 'ring-offset-width' : 'ring-offset-color', []],
                default => ['ring-color', []],
            },
            'outline' => match (true) {
                str_starts_with($value, 'offset-') => ['outline-offset', []],
                $value === '' || preg_match('/^(\d+|\[[\d.]+px\])$/', $value) === 1 => ['outline-width', []],
                in_array($value, ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double'], true) => ['outline-style', []],
                default => ['outline-color', []],
            },
            'flex' => match (true) {
                in_array($value, ['row', 'row-reverse', 'col', 'col-reverse'], true) => ['flex-direction', []],
                in_array($value, ['wrap', 'wrap-reverse', 'nowrap'], true) => ['flex-wrap', []],
                $value === '' => null,
                default => ['flex', self::SimpleRefinements['flex']],
            },
            // Single-axis utilities (gap-x-4, overflow-y-auto) are simple groups of their own.
            'overflow' => $value === '' || preg_match('/^[xy]-/', $value) === 1 ? null : ['overflow', self::SimpleRefinements['overflow']],
            'transition' => ['transition-property', []],
            'gap' => $value === '' || preg_match('/^[xy]-/', $value) === 1 ? null : ['gap', self::SimpleRefinements['gap']],
            default => match (true) {
                $value === '' => null,
                str_starts_with($value, 'x-') => ['inset-x', self::SimpleRefinements['inset-x']],
                str_starts_with($value, 'y-') => ['inset-y', self::SimpleRefinements['inset-y']],
                default => ['inset', self::SimpleRefinements['inset']],
            },
        };
    }

    /**
     * @return array{0: string, 1: list<string>}|null
     */
    private function simple(string $utility): ?array
    {
        $best = null;

        foreach (self::Simple as $prefix => $group) {
            if (str_starts_with($utility, $prefix.'-') && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        if ($best === null) {
            return null;
        }

        $group = self::Simple[$best];

        return [$group, self::SimpleRefinements[$best] ?? []];
    }

    private function isColor(string $value): bool
    {
        $base = explode('/', $value, 2)[0];

        return in_array($base, ['inherit', 'current', 'transparent', 'black', 'white'], true)
            || preg_match('/^[a-z]+-\d{2,3}$/', $base) === 1
            || preg_match('/^\[(#|rgb|hsl|oklch|oklab|color:|var\(--color)/', $base) === 1
            || in_array($base, $this->colorTokens, true)
            || (str_starts_with($base, 'gcego-') && in_array(substr($base, 6), $this->prefixedColorTokens, true));
    }
}
