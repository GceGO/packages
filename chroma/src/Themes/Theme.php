<?php

declare(strict_types=1);

namespace Gcego\Chroma\Themes;

use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Exceptions\InvalidTokenException;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tokens\TokenCollection;

/**
 * The styling side of a theme: non-color token overrides (light and dark) and preset overrides.
 *
 *     new Theme(
 *         name: 'ocean',
 *         tokens: ['radius' => '1rem'],
 *         presets: [new Slot('surface.card', ['rounded-3xl'])],
 *     );
 *
 * Its colors are the gcego/swatches theme of the same name. Tokens left out keep
 * the GCEGO defaults. A light-only override does not leak into dark mode. Preset
 * overrides are Slots named after the preset.
 */
final readonly class Theme
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public TokenCollection $tokens;

    public TokenCollection $dark;

    /**
     * @var list<Slot>
     */
    public array $presets;

    /**
     * @param  array<string, string>  $tokens  Light (and default) token overrides.
     * @param  array<string, string>  $dark  Dark-mode token overrides.
     * @param  list<Slot>  $presets  Preset overrides, each Slot named after a preset id.
     *
     * @throws InvalidThemeException|InvalidTokenException
     */
    public function __construct(
        public string $name,
        array $tokens = [],
        array $dark = [],
        array $presets = [],
        public ?string $label = null,
    ) {
        if (preg_match(self::NamePattern, $name) !== 1 || strlen($name) > 50) {
            throw InvalidThemeException::invalidName($name);
        }

        foreach ($presets as $preset) {
            if (! $preset instanceof Slot) {
                throw InvalidSlotException::invalidItem(get_debug_type($preset));
            }
        }

        $this->tokens = new TokenCollection($tokens);
        $this->dark = new TokenCollection($dark);
        $this->presets = array_values($presets);
    }

    /**
     * @return list<Slot>
     */
    public function presetOverrides(string $preset): array
    {
        return array_values(array_filter($this->presets, static fn (Slot $slot): bool => $slot->name === $preset));
    }

    /**
     * Custom-property overrides for this theme; empty when it overrides no tokens.
     *
     * Values are validated by TokenType grammars, so the output is safe inside <style>.
     */
    public function css(): string
    {
        $css = '';
        $selector = sprintf(':root[data-gcego-theme="%s"]', $this->name);

        if (! $this->tokens->isEmpty()) {
            $css .= $selector.':not(.dark){'.$this->tokens->declarations().'}';
        }

        if (! $this->dark->isEmpty()) {
            $css .= ':root.dark[data-gcego-theme="'.$this->name.'"]{'.$this->dark->declarations().'}';
        }

        return $css;
    }

    /**
     * @param  array<mixed>  $config  ['tokens' => [...], 'dark' => [...], 'presets' => ['surface.card' => [...]], 'label' => ...]
     *
     * @throws InvalidThemeException|InvalidTokenException
     */
    public static function fromConfig(string $name, array $config): self
    {
        $presets = [];

        foreach (is_array($config['presets'] ?? null) ? $config['presets'] : [] as $preset => $classes) {
            if (! is_string($preset) || ! (is_string($classes) || is_array($classes))) {
                throw InvalidThemeException::invalidConfig('themes.'.$name.'.presets', 'must map preset ids to classes.');
            }

            /** @var list<string> $list */
            $list = is_string($classes) ? [$classes] : array_values($classes);
            $presets[] = new Slot($preset, $list);
        }

        $tokens = $config['tokens'] ?? [];
        $dark = $config['dark'] ?? [];
        $label = $config['label'] ?? null;

        if (! is_array($tokens) || ! is_array($dark) || ($label !== null && ! is_string($label))) {
            throw InvalidThemeException::invalidConfig('themes.'.$name, '"tokens" and "dark" must be arrays and "label" a string.');
        }

        /** @var array<string, string> $tokens */
        /** @var array<string, string> $dark */
        return new self($name, $tokens, $dark, $presets, $label);
    }
}
