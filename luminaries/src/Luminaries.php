<?php

declare(strict_types=1);

namespace Gcego\Luminaries;

use BackedEnum;
use Gcego\Luminaries\Caching\IconManifest;
use Gcego\Luminaries\Contracts\IconProvider;
use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\Exceptions\InvalidIconNameException;
use Gcego\Luminaries\Exceptions\UnsafeSvgException;
use Gcego\Luminaries\Providers\BladeIconSetProvider;
use Gcego\Luminaries\Providers\CustomSvgProvider;
use Gcego\Luminaries\Providers\InlinePathProvider;
use Gcego\Luminaries\Registry\IconRegistry;
use Gcego\Luminaries\Rendering\IconOptions;
use Gcego\Luminaries\Rendering\IconRenderer;
use Gcego\Luminaries\Security\AttributeGuard;
use Illuminate\Support\HtmlString;
use Psr\Log\LoggerInterface;

/**
 * The Luminaries API: resolve, check, render and discover icons.
 *
 * Resolution order:
 * - "provider:icon" goes to that provider.
 * - "group.name" follows semantic aliases (chains allowed, cycles refused).
 * - A bare name is looked up in inline icons, then in the default provider.
 *
 * Only boot-time configuration lives here (providers, aliases, inline
 * icons) plus bounded memoization of resolution results that depend on
 * nothing but that configuration and the installed files, so the singleton
 * is safe in long-running workers.
 */
final class Luminaries
{
    private const int MemoLimit = 4096;

    /**
     * @var array<string, ResolvedIcon|IconNotFoundException|UnsafeSvgException>
     */
    private array $resolved = [];

    private int $memoVersion = -1;

    /**
     * @var array<string, true>
     */
    private array $logged = [];

    /**
     * @param  (\Closure(): ?LoggerInterface)|null  $logger
     */
    public function __construct(
        private readonly IconRegistry $registry,
        private readonly IconRenderer $renderer,
        private readonly LuminariesConfig $config,
        private readonly IconManifest $manifest,
        private readonly InlinePathProvider $inline,
        private readonly ?\Closure $logger = null,
    ) {}

    /**
     * Whether an identifier resolves to an icon that can be rendered.
     */
    public function exists(string|BackedEnum $name): bool
    {
        return $this->tryResolve($name) !== null;
    }

    /**
     * @throws InvalidIconNameException when the identifier is malformed
     * @throws IconNotFoundException when it does not resolve
     * @throws UnsafeSvgException when it resolves to a custom file refused by the trusted SVG policy
     */
    public function resolve(string|BackedEnum $name): ResolvedIcon
    {
        $name = IconName::of($name);
        $result = $this->memo($name);

        if ($result instanceof ResolvedIcon) {
            return $result;
        }

        throw $result instanceof IconNotFoundException ? $this->withSuggestions($result) : $result;
    }

    public function tryResolve(string|BackedEnum $name): ?ResolvedIcon
    {
        if ($name instanceof BackedEnum && ! is_string($name->value)) {
            return null;
        }

        $name = IconName::of($name);

        if (! IconName::isValid($name)) {
            return null;
        }

        $result = $this->memo($name);

        return $result instanceof ResolvedIcon ? $result : null;
    }

    /**
     * Render an icon. Missing or refused icons follow config('luminaries.missing.behavior').
     *
     * @param  array<string, scalar|null>  $attributes
     */
    public function render(
        string|BackedEnum $name,
        ?string $size = null,
        ?string $tone = null,
        string $class = '',
        ?string $label = null,
        bool $decorative = false,
        ?float $strokeWidth = null,
        array $attributes = [],
    ): HtmlString {
        return new HtmlString($this->html($name, new IconOptions($size, $tone, $class, $label, $decorative, $strokeWidth, $attributes)));
    }

    /**
     * Render with prepared options (used by the Blade components).
     */
    public function html(string|BackedEnum $name, IconOptions $options): string
    {
        if ($name instanceof BackedEnum) {
            $name = is_string($name->value) ? $name->value : $name::class.'::'.$name->name;   // invalid: handled as missing
        }

        AttributeGuard::filter($options->attributes);

        try {
            $icon = $this->resolve($name);
            $provider = $this->registry->provider($icon->provider);

            if ($provider === null) {
                throw new IconNotFoundException($name, $icon->provider, null, 'The provider was removed after resolution.');
            }

            return $this->renderer->render($provider->contents($icon->icon), $icon, $provider->metadata(), $options);
        } catch (IconNotFoundException|InvalidIconNameException|UnsafeSvgException $exception) {
            return $this->missing($name, $exception, $options);
        }
    }

    /**
     * A decorative icon, or an empty string when the name is null, malformed or unknown.
     *
     * For data-driven icons (table cells, badges) where a missing icon must never break the page.
     */
    public function svg(string|BackedEnum|null $name, string $class = ''): HtmlString
    {
        if ($name instanceof BackedEnum) {
            $name = is_string($name->value) ? $name->value : null;
        }

        if ($name === null || $this->tryResolve($name) === null) {
            return new HtmlString('');
        }

        return new HtmlString($this->html($name, new IconOptions(size: IconOptions::NoSize, class: $class)));
    }

    public function hasProvider(string $name): bool
    {
        return $this->registry->hasProvider($name);
    }

    public function provider(string $name): ?IconProvider
    {
        return $this->registry->provider($name);
    }

    /**
     * @return array<string, IconProvider>
     */
    public function providers(): array
    {
        return $this->registry->providers();
    }

    public function registerProvider(IconProvider $provider): void
    {
        $this->registry->addProvider($provider);
    }

    /**
     * Map a semantic alias at runtime (boot time), e.g. from a service provider.
     */
    public function alias(string $alias, string $target, bool $override = false): void
    {
        $this->registry->alias($alias, $target, $override);
    }

    /**
     * @return array<string, string> Alias => target, sorted.
     */
    public function aliases(): array
    {
        return $this->registry->aliases();
    }

    public function aliasOrigin(string $alias): ?string
    {
        return $this->registry->origin($alias);
    }

    /**
     * @return array{chain: list<string>, target: ?string}
     */
    public function aliasChain(string $alias): array
    {
        return $this->registry->chain($alias);
    }

    /**
     * Register an inline path icon (24×24 outline), reachable as "inline:{name}" and as a bare name.
     *
     * @param  string|list<string>  $paths
     */
    public function registerPath(string $name, string|array $paths, bool $override = false): void
    {
        $this->inline->register($name, $paths, $override);
        $this->resolved = [];
    }

    /**
     * Every icon of a provider (cached manifest first, provider discovery otherwise).
     *
     * @return list<string>
     */
    public function icons(string $provider): array
    {
        $instance = $this->registry->provider($provider);

        if ($instance === null) {
            return [];
        }

        return ($instance instanceof BladeIconSetProvider ? $this->manifest->icons($provider) : null) ?? $instance->icons();
    }

    /**
     * Search icons and aliases by name.
     *
     * @return list<array{name: string, kind: string, provider: ?string, target: ?string}>
     */
    public function search(string $query, ?string $provider = null, int $limit = 100, bool $aliases = true): array
    {
        $query = strtolower(trim($query));
        $results = [];

        if ($aliases && ($provider === null || $provider === 'alias')) {
            foreach ($this->registry->aliases() as $alias => $target) {
                if ($query === '' || str_contains($alias, $query)) {
                    $results[] = ['name' => $alias, 'kind' => 'alias', 'provider' => null, 'target' => $target, 'rank' => self::rank($alias, $query)];
                }
            }
        }

        foreach ($this->registry->providers() as $name => $instance) {
            if ($provider !== null && $provider !== $name) {
                continue;
            }

            foreach ($this->icons($name) as $icon) {
                if ($query === '' || str_contains($icon, $query)) {
                    $results[] = ['name' => IconName::canonical($name, $icon), 'kind' => 'icon', 'provider' => $name, 'target' => null, 'rank' => self::rank($icon, $query)];
                }
            }
        }

        usort($results, static fn (array $a, array $b): int => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']]);

        return array_map(
            static fn (array $result): array => ['name' => $result['name'], 'kind' => $result['kind'], 'provider' => $result['provider'], 'target' => $result['target']],
            array_slice($results, 0, max(1, $limit)),
        );
    }

    /**
     * @return array<string, string>
     */
    public function sizes(): array
    {
        return $this->config->sizes;
    }

    /**
     * @return array<string, string>
     */
    public function tones(): array
    {
        return $this->config->tones;
    }

    public function config(): LuminariesConfig
    {
        return $this->config;
    }

    public function manifest(): IconManifest
    {
        return $this->manifest;
    }

    public function renderer(): IconRenderer
    {
        return $this->renderer;
    }

    /**
     * Forget memoized resolutions (after changing files on disk in a long-running process).
     */
    public function flush(): void
    {
        $this->resolved = [];
        $this->logged = [];
    }

    private function memo(string $name): ResolvedIcon|IconNotFoundException|UnsafeSvgException
    {
        if ($this->memoVersion !== $this->registry->version()) {
            $this->resolved = [];
            $this->memoVersion = $this->registry->version();
        }

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $parsed = IconName::parse($name);

        if (count($this->resolved) >= self::MemoLimit) {
            $this->resolved = [];
        }

        try {
            return $this->resolved[$name] = $this->lookup($parsed);
        } catch (IconNotFoundException|UnsafeSvgException $exception) {
            return $this->resolved[$name] = $exception;
        } catch (InvalidConfigurationException $exception) {
            return $this->resolved[$name] = new IconNotFoundException($name, 'semantic', null, trim((string) preg_replace('/^GCEGO Luminaries\s*/', '', $exception->getMessage())));
        }
    }

    /**
     * @throws IconNotFoundException|UnsafeSvgException|InvalidConfigurationException
     */
    private function lookup(IconName $name): ResolvedIcon
    {
        if ($name->isAlias()) {
            ['chain' => $chain, 'target' => $target] = $this->registry->chain($name->value);

            if ($target === null) {
                $undefined = $chain[count($chain) - 1];

                throw new IconNotFoundException($name->value, 'semantic', null, sprintf('The alias "%s" is not defined. Add it to config("luminaries.aliases") or enable the preset that provides it.', $undefined), $chain, [], ['aliases', $undefined]);
            }

            $final = IconName::parse($target);

            try {
                $resolved = $this->direct($final);
            } catch (IconNotFoundException $exception) {
                throw new IconNotFoundException($name->value, 'semantic', $target, $exception->issue, $chain, [], $exception->hint);
            }

            return new ResolvedIcon($name->value, $resolved->provider, $resolved->icon, $chain);
        }

        return $this->direct($name);
    }

    /**
     * @throws IconNotFoundException|UnsafeSvgException
     */
    private function direct(IconName $name): ResolvedIcon
    {
        if ($name->isBare()) {
            if ($this->inline->has($name->icon) && $this->registry->hasProvider(InlinePathProvider::Name)) {
                return new ResolvedIcon($name->value, InlinePathProvider::Name, $name->icon);
            }

            $default = $this->config->defaultProvider;

            if ($this->providerHas($default, $name->icon)) {
                return new ResolvedIcon($name->value, $default, $name->icon);
            }

            throw new IconNotFoundException($name->value, $default, null, sprintf('The bare name is neither an inline icon nor available in the default provider "%s".', $default), [], [], ['icons', $default, $name->icon]);
        }

        $provider = (string) $name->provider;

        if (! $this->registry->hasProvider($provider)) {
            throw new IconNotFoundException($name->value, $provider, null, sprintf('No provider named "%s" is registered or enabled.', $provider), [], [], ['providers', $provider]);
        }

        if ($this->providerHas($provider, $name->icon)) {
            return new ResolvedIcon($name->value, $provider, $name->icon);
        }

        $instance = $this->registry->provider($provider);

        if ($instance instanceof CustomSvgProvider) {
            $violations = $instance->violations($name->icon);

            if ($violations !== []) {
                throw UnsafeSvgException::file($name->value, ($instance->metadata()->path ?? '').'/'.$name->icon.'.svg', $violations);
            }

            throw new IconNotFoundException($name->value, $provider, null, $instance->problem() ?? 'The file does not exist in the collection directory.', [], [], ['icons', $provider, $name->icon]);
        }

        throw new IconNotFoundException($name->value, $provider, null, 'The icon is not available in the installed collection.', [], [], ['icons', $provider, $name->icon]);
    }

    private function providerHas(string $provider, string $icon): bool
    {
        $instance = $this->registry->provider($provider);

        if ($instance === null) {
            return false;
        }

        if ($instance instanceof BladeIconSetProvider) {
            $listed = $this->manifest->has($provider, $icon);

            if ($listed !== null) {
                return $listed;
            }
        }

        return $instance->has($icon);
    }

    private function missing(string $name, IconNotFoundException|InvalidIconNameException|UnsafeSvgException $exception, IconOptions $options): string
    {
        $behavior = $this->config->missingBehavior;

        if ($behavior === 'exception') {
            throw $exception;
        }

        if ($this->config->logMissing && ! isset($this->logged[$name]) && count($this->logged) < self::MemoLimit) {
            $this->logged[$name] = true;
            $logger = $this->logger === null ? null : ($this->logger)();
            $logger?->warning('GCEGO Luminaries: icon could not be rendered.', ['icon' => mb_substr($name, 0, 120), 'behavior' => $behavior, 'reason' => $exception->getMessage()]);
        }

        if ($behavior === 'placeholder') {
            return $this->renderer->placeholder(IconName::isValid($name) ? $name : 'invalid', $options);
        }

        $fallback = $this->config->fallback;

        if ($behavior === 'fallback' && $fallback !== null && $fallback !== $name && ($icon = $this->tryResolve($fallback)) !== null) {
            $provider = $this->registry->provider($icon->provider);

            if ($provider !== null) {
                return $this->renderer->render($provider->contents($icon->icon), $icon, $provider->metadata(), $options);
            }
        }

        return $this->renderer->label($options);
    }

    /**
     * Add similar names derived from registered metadata; only while debugging (exception mode), and only when thrown.
     */
    private function withSuggestions(IconNotFoundException $exception): IconNotFoundException
    {
        $hint = $exception->hint;

        if ($hint === null || $this->config->missingBehavior !== 'exception') {
            return $exception;
        }

        $suggestions = match ($hint[0]) {
            'aliases' => $this->suggest(array_keys($this->registry->aliases()), $hint[1]),
            'providers' => $this->suggest(array_keys($this->registry->providers()), $hint[1]),
            default => array_map(static fn (string $name): string => IconName::canonical($hint[1], $name), $this->suggest($this->icons($hint[1]), $hint[2] ?? '')),
        };

        return $suggestions === [] ? $exception : $exception->withSuggestions($suggestions);
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function suggest(array $candidates, string $needle): array
    {
        if ($this->config->missingBehavior !== 'exception' || $needle === '') {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $distance = levenshtein($needle, $candidate);

            if ($distance <= max(2, intdiv(strlen($needle), 3)) || (strlen($needle) >= 4 && str_contains($candidate, $needle)) || (strlen($candidate) >= 4 && str_contains($needle, $candidate))) {
                $scored[$candidate] = $distance;
            }
        }

        asort($scored);

        return array_slice(array_map(strval(...), array_keys($scored)), 0, 5);
    }

    private static function rank(string $name, string $query): int
    {
        $base = str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;

        return match (true) {
            $query === '' => 3,
            $base === $query || $name === $query => 0,
            str_starts_with($base, $query) => 1,
            default => 2,
        };
    }
}
