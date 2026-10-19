<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Rendering;

use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\LuminariesConfig;
use Gcego\Luminaries\ResolvedIcon;
use Gcego\Luminaries\Security\AttributeGuard;
use Gcego\Luminaries\Support\ProviderMetadata;

/**
 * Turns trusted SVG markup and IconOptions into the final element: size and
 * tone classes, accessibility attributes, stroke width (stroke-drawn
 * providers only) and guarded extra attributes.
 */
final readonly class IconRenderer
{
    public function __construct(private LuminariesConfig $config) {}

    public function render(string $contents, ResolvedIcon $icon, ProviderMetadata $provider, IconOptions $options): string
    {
        $attributes = [
            ...AttributeGuard::filter($options->attributes),
            'class' => $this->classes($options),
            'data-gcego-icon' => $icon->canonical(),
            ...$this->accessibility($options),
        ];

        if ($options->strokeWidth !== null && $provider->strokeWidth) {
            $attributes['stroke-width'] = self::strokeWidth($options->strokeWidth);
        }

        if ($attributes['class'] === '') {
            unset($attributes['class']);
        }

        return SvgRootTag::merge($contents, $attributes);
    }

    /**
     * A neutral dashed square standing in for a missing icon (placeholder mode).
     */
    public function placeholder(string $name, IconOptions $options): string
    {
        $attributes = [
            'class' => $this->classes($options),
            'data-gcego-icon-missing' => $name,
            ...$this->accessibility($options),
        ];

        return SvgRootTag::merge(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3.75" y="3.75" width="16.5" height="16.5" rx="3" stroke-dasharray="3 3"/></svg>',
            $attributes,
        );
    }

    /**
     * Screen-reader text kept when a missing icon renders nothing.
     */
    public function label(IconOptions $options): string
    {
        return $options->isDecorative() ? '' : '<span class="sr-only">'.e((string) $options->label).'</span>';
    }

    public function classes(IconOptions $options): string
    {
        $classes = [$this->config->class];
        $size = $options->size ?? $this->config->defaultSize;

        if ($size !== null && $size !== IconOptions::NoSize) {
            $classes[] = $this->config->sizes[$size] ?? throw InvalidConfigurationException::invalid('sizes', sprintf('unknown size "%s"; available: %s.', mb_substr($size, 0, 32), implode(', ', array_keys($this->config->sizes))));
        }

        if ($options->tone !== null) {
            $classes[] = $this->config->tones[$options->tone] ?? throw InvalidConfigurationException::invalid('tones', sprintf('unknown tone "%s"; available: %s.', mb_substr($options->tone, 0, 32), implode(', ', array_keys($this->config->tones))));
        }

        $classes[] = $options->class;
        $tokens = preg_split('/\s+/', implode(' ', $classes), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_values(array_unique($tokens)));
    }

    /**
     * @return array<string, string>
     */
    private function accessibility(IconOptions $options): array
    {
        if ($options->isDecorative()) {
            return ['aria-hidden' => 'true', 'focusable' => 'false'];
        }

        return ['role' => 'img', 'aria-label' => trim((string) $options->label), 'focusable' => 'false'];
    }

    private static function strokeWidth(float $width): string
    {
        if ($width <= 0 || $width > 4) {
            throw InvalidConfigurationException::invalid('stroke_width', 'must be greater than 0 and at most 4.');
        }

        return rtrim(rtrim(number_format($width, 2, '.', ''), '0'), '.');
    }
}
