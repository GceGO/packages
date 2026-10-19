<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Providers;

use Gcego\Luminaries\Contracts\IconProvider;
use Gcego\Luminaries\Exceptions\DuplicateRegistrationException;
use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Support\ProviderMetadata;

/**
 * Icons registered in PHP as SVG path data (24×24, 1.5 stroke outline).
 *
 * This keeps the pre-Luminaries API working: Icons::register('school', 'M…')
 * from gcego/tables lands here. Path data is restricted to SVG path commands
 * and numbers, so it cannot carry markup. Prefer a custom SVG collection or a
 * semantic alias for new icons.
 */
final class InlinePathProvider implements IconProvider
{
    public const string Name = 'inline';

    public const string PathPattern = '/^[MmLlHhVvCcSsQqTtAaZz0-9 .,\-]{1,4000}$/';

    /**
     * Shapes the framework needs that the installed collections lack, available as "inline:{name}".
     */
    public const array BuiltIn = [
        'circle' => ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $paths = self::BuiltIn;

    /**
     * @param  string|list<string>  $paths
     */
    public function register(string $icon, string|array $paths, bool $override = false): void
    {
        $paths = array_values((array) $paths);

        if (preg_match(IconName::IconPattern, $icon) !== 1 || $paths === []) {
            throw InvalidConfigurationException::invalid('inline', sprintf('"%s" is not a valid icon name (lowercase letters, digits and dashes).', mb_substr($icon, 0, 64)));
        }

        foreach ($paths as $path) {
            if (! is_string($path) || preg_match(self::PathPattern, $path) !== 1) {
                throw InvalidConfigurationException::invalid('inline', sprintf('icon "%s" must use SVG path data only.', $icon));
            }
        }

        if (isset($this->paths[$icon]) && ! $override) {
            throw DuplicateRegistrationException::icon($icon);
        }

        $this->paths[$icon] = $paths;
    }

    public function name(): string
    {
        return self::Name;
    }

    public function has(string $icon): bool
    {
        return isset($this->paths[$icon]);
    }

    public function contents(string $icon): string
    {
        if (! isset($this->paths[$icon])) {
            throw new IconNotFoundException(IconName::canonical(self::Name, $icon), self::Name, null, 'No inline icon with this name was registered.');
        }

        $markup = '';

        foreach ($this->paths[$icon] as $path) {
            $markup .= sprintf('<path stroke-linecap="round" stroke-linejoin="round" d="%s"/>', $path);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'.$markup.'</svg>';
    }

    public function nativeName(string $icon): ?string
    {
        return null;
    }

    public function icons(): array
    {
        $icons = array_keys($this->paths);
        sort($icons);

        return $icons;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(self::Name, 'Inline path icons', 'inline', 'outline', true, 'registered in PHP');
    }
}
