<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Diagnostics;

use Gcego\Luminaries\Exceptions\LuminariesException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Providers\BladeIconSetProvider;
use Gcego\Luminaries\Providers\CustomSvgProvider;

/**
 * Validates the icon configuration: providers, the default provider and
 * fallback, every semantic alias, custom collection directories and files,
 * preset overrides and the icon manifest. Used by gcego:icons:check.
 */
final readonly class IconInspector
{
    public function __construct(private Luminaries $luminaries) {}

    /**
     * @return list<Diagnostic>
     */
    public function inspect(): array
    {
        return [
            ...$this->providers(),
            ...$this->aliases(),
            ...$this->collections(),
            ...$this->cache(),
        ];
    }

    /**
     * @return list<Diagnostic>
     */
    private function providers(): array
    {
        $diagnostics = [];
        $config = $this->luminaries->config();

        foreach ($this->luminaries->providers() as $name => $provider) {
            if ($provider instanceof BladeIconSetProvider && ! $provider->available()) {
                $diagnostics[] = new Diagnostic(Diagnostic::Error, 'providers', sprintf('The Blade Icons set "%s" is not registered; install its package or disable the provider.', $provider->set()), $name);
            }
        }

        if (! $this->luminaries->hasProvider($config->defaultProvider)) {
            $diagnostics[] = new Diagnostic(Diagnostic::Error, 'providers', sprintf('The default provider "%s" is not registered or enabled; bare icon names cannot resolve.', $config->defaultProvider), 'default_provider');
        }

        if ($config->fallback !== null && ! $this->luminaries->exists($config->fallback)) {
            $diagnostics[] = new Diagnostic(Diagnostic::Error, 'missing', sprintf('The fallback icon "%s" does not resolve.', $config->fallback), 'missing.fallback');
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function aliases(): array
    {
        $diagnostics = [];
        $config = $this->luminaries->config();
        $presets = [];

        foreach ($config->presets as $preset) {
            $presets += $config::Presets[$preset];
        }

        foreach ($this->luminaries->aliases() as $alias => $target) {
            try {
                $resolved = $this->luminaries->resolve($alias);

                if ($resolved->provider === $config->defaultProvider && ! str_contains($target, ':') && ! IconName::parse($target)->isAlias()) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Info, 'aliases', sprintf('Maps to the bare name "%s"; prefer the canonical "%s".', $target, $resolved->canonical()), $alias);
                }
            } catch (LuminariesException $exception) {
                $diagnostics[] = new Diagnostic(Diagnostic::Error, 'aliases', sprintf('Mapping "%s" does not resolve: %s', $target, self::reason($exception)), $alias);
            }

            if ($this->luminaries->aliasOrigin($alias) === 'config' && isset($presets[$alias])) {
                $diagnostics[] = $presets[$alias] === $target
                    ? new Diagnostic(Diagnostic::Warning, 'aliases', sprintf('Duplicate: the configured alias repeats the preset mapping "%s"; remove it.', $target), $alias)
                    : new Diagnostic(Diagnostic::Info, 'aliases', sprintf('Overrides the preset mapping "%s" with "%s".', $presets[$alias], $target), $alias);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function collections(): array
    {
        $diagnostics = [];

        foreach ($this->luminaries->providers() as $name => $provider) {
            if (! $provider instanceof CustomSvgProvider) {
                continue;
            }

            $problem = $provider->problem();

            if ($problem !== null) {
                $diagnostics[] = new Diagnostic(Diagnostic::Error, 'collections', $problem, $name);

                continue;
            }

            $path = (string) $provider->metadata()->path;

            foreach (glob(rtrim($path, '/').'/*') ?: [] as $file) {
                $base = basename($file);

                if (is_dir($file)) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Warning, 'collections', sprintf('Subdirectory "%s" is ignored; collections are flat.', $base), $name);

                    continue;
                }

                if (! str_ends_with($base, '.svg')) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Warning, 'collections', sprintf('"%s" is not an .svg file and is ignored.', $base), $name);

                    continue;
                }

                $icon = basename($base, '.svg');

                if (preg_match(IconName::IconPattern, $icon) !== 1) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Error, 'collections', sprintf('"%s" has an invalid icon name; use lowercase letters, digits and dashes.', $base), $name);

                    continue;
                }

                $violations = $provider->violations($icon);

                if ($violations !== []) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Error, 'collections', sprintf('Rejected by the trusted SVG policy: %s.', implode('; ', $violations)), IconName::canonical($name, $icon));
                } elseif (! $provider->has($icon)) {
                    $diagnostics[] = new Diagnostic(Diagnostic::Error, 'collections', 'The file cannot be read or resolves outside the collection directory.', IconName::canonical($name, $icon));
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function cache(): array
    {
        $manifest = $this->luminaries->manifest();

        if ($manifest->exists() && ! $manifest->isFresh()) {
            return [new Diagnostic(Diagnostic::Warning, 'cache', 'The icon manifest is stale (configuration changed) and is ignored; run php artisan gcego:icons:cache.', $manifest->path())];
        }

        return [];
    }

    private static function reason(LuminariesException $exception): string
    {
        $message = (string) preg_replace('/^GCEGO Luminaries\s*/', '', $exception->getMessage());

        if (preg_match('/Possible issue:\s*(.+?)(?:\n\n|$)/s', $message, $match) === 1) {
            return trim($match[1]);
        }

        return trim(strtok($message, "\n") ?: $message);
    }
}
