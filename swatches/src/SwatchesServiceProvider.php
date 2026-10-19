<?php

declare(strict_types=1);

namespace Gcego\Swatches;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Swatches\Branding\BrandCompiler;
use Gcego\Swatches\Branding\NullBrandResolver;
use Gcego\Swatches\Caching\CompiledCssCache;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Console\CacheCommand;
use Gcego\Swatches\Console\ClearCommand;
use Gcego\Swatches\Console\ContrastCommand;
use Gcego\Swatches\Console\GenerateCommand;
use Gcego\Swatches\Console\ListCommand;
use Gcego\Swatches\Console\LogoCommand;
use Gcego\Swatches\Console\PaletteCommand;
use Gcego\Swatches\Console\ThemesCommand;
use Gcego\Swatches\Console\ValidateCommand;
use Gcego\Swatches\Contracts\BrandResolver;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Generators\CssGenerator;
use Gcego\Swatches\Http\ExplorerController;
use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\Palettes\PaletteRegistry;
use Gcego\Swatches\Palettes\ScaleGenerator;
use Gcego\Swatches\Support\PreviewClasses;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Validation\Issue;
use Gcego\Swatches\Validation\ThemeValidator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/swatches; also the "gcego/swatches" GCEGO package.
 *
 * Registers palettes and themes from config (validated before use), the CSS
 * generator, the scoped BrandResolver, the gcego-swatches Blade components, the
 * gcego:swatches:* commands and, when enabled, the developer color explorer.
 */
final class SwatchesServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-swatches';

    public function register(): void
    {
        $this->mergeConfigFrom(self::path('config/gcego-swatches.php'), 'gcego-swatches');

        $this->app->singleton(SwatchesConfig::class, static function (Application $app): SwatchesConfig {
            $repository = $app->make(Repository::class);
            self::adoptLegacyChromaConfig($repository);
            $config = $repository->get('gcego-swatches', []);

            return SwatchesConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(PaletteRegistry::class, static function (Application $app): PaletteRegistry {
            $palettes = PaletteRegistry::withDefaults();
            $generator = new ScaleGenerator;

            foreach ($app->make(SwatchesConfig::class)->palettes as $name => $definition) {
                $palettes->register(is_string($definition)
                    ? $generator->generate($name, Color::parse($definition))
                    : Palette::fromStrings($name, $definition, metadata: ['source' => 'config']), override: true);
            }

            return $palettes;
        });

        $this->app->singleton(ThemeRegistry::class, static function (Application $app): ThemeRegistry {
            $config = $app->make(SwatchesConfig::class);
            $themes = ThemeRegistry::withDefaults();

            foreach ($config->themes as $name => $definition) {
                $themes->register(Theme::fromConfig($name, $definition), override: true);
            }

            $validator = new ThemeValidator($themes);

            foreach ($config->themes as $name => $definition) {
                $report = $validator->validate($name, contrast: $config->enforceContrast);

                foreach ($report->errors() as $issue) {
                    if ($issue->code !== Issue::Contrast || $config->enforceContrast) {
                        throw InvalidThemeException::failsValidation($name, $issue->message);
                    }
                }
            }

            if (! $themes->has($config->theme)) {
                throw InvalidThemeException::invalidConfig('theme', sprintf('"%s" is not a registered theme (%s).', preg_replace('/[^a-z0-9-]/', '?', $config->theme), implode(', ', $themes->names())));
            }

            return $themes;
        });

        $this->app->singleton(CssGenerator::class);
        $this->app->singleton(BrandCompiler::class);
        $this->app->singleton(PreviewClasses::class);
        $this->app->singleton(CompiledCssCache::class, static function (Application $app): CompiledCssCache {
            $path = $app->make(SwatchesConfig::class)->cachePath;

            return new CompiledCssCache(str_starts_with($path, '/') ? $path : $app->basePath($path));
        });

        $this->app->scopedIf(BrandResolver::class, NullBrandResolver::class);
        $this->app->singleton(SwatchesManager::class);
        $this->app->alias(SwatchesManager::class, 'gcego.swatches');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::path('resources/views'), self::Views);

        if ($this->app->runningInConsole()) {
            $this->commands([ListCommand::class, ThemesCommand::class, ValidateCommand::class, ContrastCommand::class, PaletteCommand::class, LogoCommand::class, GenerateCommand::class, CacheCommand::class, ClearCommand::class]);
            $this->optimizes('gcego:swatches:cache', 'gcego:swatches:clear', 'swatches');
            $this->publishes([self::path('config/gcego-swatches.php') => $this->app->configPath('gcego-swatches.php')], 'gcego-swatches-config');
        }

        if ($this->app->make(SwatchesConfig::class)->explorer) {
            if (! Gate::has(ExplorerController::Ability)) {
                Gate::define(ExplorerController::Ability, fn (?Authenticatable $user = null): bool => $this->app->environment('local'));
            }

            $this->loadRoutesFrom(self::path('routes/explorer.php'));
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/swatches',
            requires: ['gcego/core'],
            capabilities: ['colors', 'colors.themes', 'colors.tailwind', 'colors.branding', 'colors.contrast'],
            description: 'Color values, palettes, semantic color tokens, themes, tenant branding and WCAG contrast validation.',
        );
    }

    /**
     * Appearance and accents used to live in config/gcego-chroma.php. An application that
     * still sets them there, and leaves the gcego-swatches section at its defaults, keeps its
     * values (merged over the defaults), with a deprecation notice.
     */
    public static function adoptLegacyChromaConfig(Repository $config): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require self::path('config/gcego-swatches.php');

        foreach (['appearance', 'accents'] as $key) {
            $legacy = $config->get('gcego-chroma.'.$key);
            $current = $config->get('gcego-swatches.'.$key);

            if (! is_array($legacy) || $legacy === [] || ! is_array($defaults[$key]) || ($current !== null && $current != $defaults[$key])) {
                continue;
            }

            $config->set('gcego-swatches.'.$key, array_replace($defaults[$key], $legacy));
            @trigger_error(sprintf('gcego-chroma.%1$s is deprecated: move it to gcego-swatches.%1$s (config/gcego-swatches.php).', $key), E_USER_DEPRECATED);
        }
    }

    /**
     * @internal
     */
    public static function path(string $relative = ''): string
    {
        return dirname(__DIR__).($relative === '' ? '' : '/'.$relative);
    }
}
