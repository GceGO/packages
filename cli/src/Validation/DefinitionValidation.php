<?php

declare(strict_types=1);

namespace Gcego\Cli\Validation;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\PathResolver;
use Gcego\Modules\ModuleManager;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\Page;
use Gcego\Pages\PageManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryManager;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Validates every discovered definition with the validators the packages
 * already have — nothing is re-implemented:
 *
 *  1. a non-strict registry build (gcego/registry) loads every definition
 *     and reports files that fail to load, invalid definitions and
 *     duplicate identities, without stopping at the first;
 *  2. each definition's kind validates it (DefinitionKind::validate(): the
 *     component tree plus kind rules, e.g. module compilation);
 *  3. the inspectors of gcego/modules, gcego/panels, gcego/pages and
 *     gcego/navigation add their integration errors (missing references,
 *     undeclared access, route conflicts) — when every definition loads,
 *     since they read the application's strict registry.
 */
final readonly class DefinitionValidation
{
    /** --type aliases => registry kinds. */
    public const array Types = [
        'blueprints' => ['blueprint'],
        'modules' => ['module'],
        'resources' => ['module'],
        'pages' => ['page'],
        'panels' => ['panel'],
        'layouts' => ['layout'],
        'navigation' => ['navigation'],
        'widgets' => ['widget'],
        'workflows' => ['workflow'],
        'settings' => ['settings', 'settings-page'],
        'analytics' => ['dataset', 'metric', 'report', 'analytics-dashboard'],
    ];

    public function __construct(private RegistryManager $registry, private DefinitionKinds $kinds, private Container $container, private PathResolver $paths) {}

    /**
     * @param  list<string>  $kinds  registry kinds to keep (empty: all)
     */
    public function run(array $kinds = [], ?string $identity = null): ValidationReport
    {
        $started = hrtime(true);
        $build = $this->registry->build(strict: false);
        $entries = [];

        foreach ($build->failures as $failure) {
            $duplicate = $failure->exception instanceof DuplicateDefinitionException;
            $entries[] = new ValidationEntry(null, null, $this->paths->relative($failure->source), [new ValidationProblem($duplicate ? 'registration.duplicate' : 'registration.failed', $this->clean($failure->exception->getMessage()))]);
        }

        // The integration inspectors read the application's (strict) registry: make it see the files
        // as they are now, and skip them when it cannot build (the failures above say why).
        $integrate = $build->failures === [];

        if ($integrate) {
            $this->container->make(Registry::class)->refresh();
            $this->container->forgetScopedInstances();
        }

        if ($identity !== null && ! $build->index->has($identity)) {
            throw GenerationException::missing(sprintf('No definition "%s" was discovered. List them with php artisan gcego:registry.', $identity));
        }

        foreach ($build->index as $descriptor) {
            if (($kinds !== [] && ! in_array($descriptor->kind(), $kinds, true)) || ($identity !== null && $descriptor->key() !== $identity)) {
                continue;
            }

            $entries[] = $this->entry($descriptor, $build->definitions[$descriptor->key()] ?? null, $integrate);
        }

        if ($kinds !== [] || $identity !== null) {
            $entries = array_values(array_filter($entries, static fn (ValidationEntry $entry): bool => $entry->identity !== null || $identity === null));
        }

        $notices = $integrate ? [] : ['Integration checks (references between modules, panels, pages and navigation) were skipped: they need every definition to load. Fix the failures above and validate again.'];

        return new ValidationReport($entries, round((hrtime(true) - $started) / 1e6, 1), $notices);
    }

    /**
     * @return list<string>
     */
    public function kindsFor(string $type): array
    {
        if (isset(self::Types[$type])) {
            return self::Types[$type];
        }

        if ($this->kinds->has($type)) {
            return [$type];
        }

        throw GenerationException::invalid(sprintf('Unknown --type "%s". Use one of: %s, or a registry kind (%s).', $type, implode(', ', array_keys(self::Types)), implode(', ', $this->kinds->ids())));
    }

    private function entry(DefinitionDescriptor $descriptor, ?object $definition, bool $integrate): ValidationEntry
    {
        $source = $descriptor->source->type->value === 'file' ? $this->paths->relative($descriptor->source->location) : $descriptor->source->toString();
        $problems = [];

        try {
            $definition ??= $this->container->make(RegistryInterface::class)->get($descriptor->key());
            $kind = $this->kinds->get($descriptor->kind());

            foreach ($kind?->validate($definition) ?? [] as $error) {
                $problems[] = new ValidationProblem('definition.invalid', $this->clean($error));
            }

            if ($problems === [] && $integrate) {
                $problems = $this->integration($descriptor, $definition);
            }
        } catch (Throwable $exception) {
            $problems[] = new ValidationProblem('definition.unloadable', $this->clean($exception->getMessage()));
        }

        return new ValidationEntry($descriptor->key(), $descriptor->kind(), $source, $problems);
    }

    /**
     * @return list<ValidationProblem>
     */
    private function integration(DefinitionDescriptor $descriptor, object $definition): array
    {
        $problems = [];

        try {
            $diagnostics = match ($descriptor->kind()) {
                'module' => array_map(static fn ($diagnostic): array => [$diagnostic->check, $diagnostic->message], $this->container->make(ModuleManager::class)->inspect($descriptor->name())->errors()),
                'panel' => array_map(static fn ($diagnostic): array => [$diagnostic->code, $diagnostic->message], array_values(array_filter($this->container->make(PanelManager::class)->inspect($descriptor->name())->diagnostics, static fn ($diagnostic): bool => $diagnostic->level === 'error'))),
                'page' => $definition instanceof Page ? array_map(static fn ($diagnostic): array => [$diagnostic->check, $diagnostic->message], $this->container->make(PageManager::class)->inspect($definition)->errors()) : [],
                'navigation' => $definition instanceof Navigation ? array_map(static fn ($diagnostic): array => [$diagnostic->check, $diagnostic->message], $this->container->make(NavigationManager::class)->inspect($definition)->errors()) : [],
                default => [],
            };
        } catch (Throwable $exception) {
            $diagnostics = [['inspection', $exception->getMessage()]];
        }

        foreach ($diagnostics as [$check, $message]) {
            $problems[] = new ValidationProblem('integration.'.$check, $this->clean((string) $message));
        }

        return $problems;
    }

    private function clean(string $message): string
    {
        return trim(str_replace($this->paths->basePath().'/', '', $message));
    }
}
