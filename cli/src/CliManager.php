<?php

declare(strict_types=1);

namespace Gcego\Cli;

use Gcego\Cli\Contracts\DefinitionInspector;
use Gcego\Cli\Contracts\DiagnosticCheck;
use Gcego\Cli\Contracts\Generator;
use Gcego\Cli\Exceptions\ExtensionException;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generators\ActionGenerator;
use Gcego\Cli\Generators\AnalyticsGenerator;
use Gcego\Cli\Generators\BlueprintGenerator;
use Gcego\Cli\Generators\LayoutGenerator;
use Gcego\Cli\Generators\ModuleGenerator;
use Gcego\Cli\Generators\NavigationGenerator;
use Gcego\Cli\Generators\NotificationGenerator;
use Gcego\Cli\Generators\PageGenerator;
use Gcego\Cli\Generators\PanelGenerator;
use Gcego\Cli\Generators\PartGenerator;
use Gcego\Cli\Generators\ResourceGenerator;
use Gcego\Cli\Generators\SettingsGenerator;
use Gcego\Cli\Generators\TestGenerator;
use Gcego\Cli\Generators\WidgetGenerator;
use Gcego\Cli\Generators\WorkflowGenerator;
use Gcego\Cli\Stubs\StubManager;
use Illuminate\Contracts\Container\Container;

/**
 * The registry of generators, definition inspectors, diagnostic checks
 * and stub directories — built-in and contributed by trusted packages:
 *
 *     app(CliManager::class)->generator(ReportCardGenerator::class);   // gcego:make report-card
 *     app(CliManager::class)->check(QueueWorkersCheck::class);          // gcego:doctor
 *     app(CliManager::class)->inspector(ReportCardInspector::class);    // gcego:inspect
 *     app(CliManager::class)->stubs('acme/report-cards', __DIR__.'/../stubs');
 *
 * Identifiers are unique: a contribution that collides with a built-in or
 * another contribution is refused with an ExtensionException.
 */
final class CliManager
{
    /** Built-in generators: id => [class, variant]. */
    public const array Generators = [
        'blueprint' => [BlueprintGenerator::class, null],
        'module' => [ModuleGenerator::class, null],
        'resource' => [ResourceGenerator::class, null],
        'page' => [PageGenerator::class, null],
        'panel' => [PanelGenerator::class, null],
        'form' => [PartGenerator::class, 'form'],
        'table' => [PartGenerator::class, 'table'],
        'infolist' => [PartGenerator::class, 'infolist'],
        'widget' => [WidgetGenerator::class, null],
        'action' => [ActionGenerator::class, null],
        'layout' => [LayoutGenerator::class, null],
        'navigation' => [NavigationGenerator::class, null],
        'notification' => [NotificationGenerator::class, null],
        'workflow' => [WorkflowGenerator::class, null],
        'settings' => [SettingsGenerator::class, null],
        'metric' => [AnalyticsGenerator::class, 'metric'],
        'dataset' => [AnalyticsGenerator::class, 'dataset'],
        'report' => [AnalyticsGenerator::class, 'report'],
        'test' => [TestGenerator::class, null],
    ];

    private const string IdPattern = '/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/';

    /** @var array<string, Generator> */
    private array $custom = [];

    /** @var array<string, DefinitionInspector> */
    private array $inspectors = [];

    /** @var array<string, DiagnosticCheck> */
    private array $checks = [];

    /** @var list<string> built-in diagnostic check ids, reserved */
    private array $reservedChecks = [];

    public function __construct(private readonly Container $container, CliConfig $config)
    {
        foreach ($config->generators as $generator) {
            $this->generator($generator);
        }

        foreach ($config->inspectors as $inspector) {
            $this->inspector($inspector);
        }

        foreach ($config->checks as $check) {
            $this->check($check);
        }
    }

    /**
     * Registers a custom generator.
     *
     * @param  class-string<Generator>|Generator  $generator
     */
    public function generator(string|Generator $generator): void
    {
        $instance = $this->instance($generator, Generator::class);
        $id = $instance->id();

        if (preg_match(self::IdPattern, $id) !== 1) {
            throw ExtensionException::because(sprintf('The generator %s has an invalid id "%s" (lowercase letters, digits, "-" and ".").', $instance::class, $id));
        }

        if (isset(self::Generators[$id]) || (isset($this->custom[$id]) && $instance::class !== $this->custom[$id]::class)) {
            throw ExtensionException::because(sprintf('The generator id "%s" of %s is already taken%s.', $id, $instance::class, isset(self::Generators[$id]) ? ' by a built-in generator' : ' by '.$this->custom[$id]::class));
        }

        $this->custom[$id] = $instance;
    }

    /**
     * Every generator, built-in first, by id.
     *
     * @return array<string, Generator>
     */
    public function generators(): array
    {
        $generators = [];

        foreach (array_keys(self::Generators) as $id) {
            $generators[$id] = $this->resolve($id);
        }

        return [...$generators, ...$this->custom];
    }

    public function resolve(string $id): Generator
    {
        if (isset($this->custom[$id])) {
            return $this->custom[$id];
        }

        if (! isset(self::Generators[$id])) {
            throw GenerationException::invalid(sprintf('Unknown generator "%s". Available: %s.', $id, implode(', ', array_keys($this->generatorIds()))));
        }

        [$class, $variant] = self::Generators[$id];
        $generator = $this->container->make($class);

        return match (true) {
            $generator instanceof PartGenerator && $variant !== null => $generator->forPart($variant),
            $generator instanceof AnalyticsGenerator && $variant !== null => $generator->forKind($variant),
            default => $generator,
        };
    }

    public function isCustom(string $id): bool
    {
        return isset($this->custom[$id]);
    }

    /**
     * @param  class-string<DefinitionInspector>|DefinitionInspector  $inspector
     */
    public function inspector(string|DefinitionInspector $inspector): void
    {
        $instance = $this->instance($inspector, DefinitionInspector::class);
        $id = $instance->id();

        if (preg_match(self::IdPattern, $id) !== 1) {
            throw ExtensionException::because(sprintf('The inspector %s has an invalid id "%s".', $instance::class, $id));
        }

        if (isset($this->inspectors[$id]) && $instance::class !== $this->inspectors[$id]::class) {
            throw ExtensionException::because(sprintf('The inspector id "%s" is already taken by %s.', $id, $this->inspectors[$id]::class));
        }

        $this->inspectors[$id] = $instance;
    }

    /**
     * Inspectors describing $kind.
     *
     * @return list<DefinitionInspector>
     */
    public function inspectorsFor(string $kind): array
    {
        return array_values(array_filter($this->inspectors, static fn (DefinitionInspector $inspector): bool => in_array($kind, $inspector->kinds(), true)));
    }

    /**
     * @param  class-string<DiagnosticCheck>|DiagnosticCheck  $check
     */
    public function check(string|DiagnosticCheck $check): void
    {
        $instance = $this->instance($check, DiagnosticCheck::class);
        $id = $instance->id();

        if (preg_match(self::IdPattern, $id) !== 1) {
            throw ExtensionException::because(sprintf('The diagnostic check %s has an invalid id "%s".', $instance::class, $id));
        }

        if (in_array($id, $this->reservedChecks, true) || (isset($this->checks[$id]) && $instance::class !== $this->checks[$id]::class)) {
            throw ExtensionException::because(sprintf('The diagnostic check id "%s" of %s is already taken.', $id, $instance::class));
        }

        $this->checks[$id] = $instance;
    }

    /**
     * @return list<DiagnosticCheck>
     */
    public function checks(): array
    {
        return array_values($this->checks);
    }

    /**
     * Built-in check ids (custom checks may not use them).
     *
     * @param  list<string>  $ids
     */
    public function reserveChecks(array $ids): void
    {
        foreach ($ids as $id) {
            if (isset($this->checks[$id])) {
                throw ExtensionException::because(sprintf('The diagnostic check id "%s" of %s is reserved for a built-in check.', $id, $this->checks[$id]::class));
            }
        }

        $this->reservedChecks = array_values(array_unique([...$this->reservedChecks, ...$ids]));
    }

    /**
     * Adds a package's stub directory (its stubs override the built-in ones of the same name).
     */
    public function stubs(string $package, string $directory): void
    {
        $this->container->make(StubManager::class)->addDirectory($package, $directory);
    }

    /**
     * @return array<string, true>
     */
    private function generatorIds(): array
    {
        return array_fill_keys([...array_keys(self::Generators), ...array_keys($this->custom)], true);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function instance(string|object $value, string $contract): object
    {
        if (is_string($value)) {
            if (! is_subclass_of($value, $contract)) {
                throw ExtensionException::because(sprintf('%s does not implement %s.', $value, $contract));
            }

            $value = $this->container->make($value);
        }

        if (! is_object($value) || ! $value instanceof $contract) {
            throw ExtensionException::because(sprintf('%s does not implement %s.', get_debug_type($value), $contract));
        }

        return $value;
    }
}
