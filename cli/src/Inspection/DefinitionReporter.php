<?php

declare(strict_types=1);

namespace Gcego\Cli\Inspection;

use BackedEnum;
use Closure;
use Gcego\Cli\CliManager;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\PathResolver;
use Gcego\Cli\Output\Redactor;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutManager;
use Gcego\Modules\ModuleManager;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\Page;
use Gcego\Pages\PageManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Illuminate\Contracts\Container\Container;
use Throwable;
use UnitEnum;

/**
 * gcego:inspect <identity>: one definition — identity, source, properties,
 * children, requirements, validation, and what its package's inspector
 * reports (modules, panels, pages, navigation, layouts), plus sections of
 * custom inspectors. Read-only; values that look secret are redacted.
 */
final readonly class DefinitionReporter
{
    private const int MaxDepth = 4;

    private const int MaxChildren = 100;

    public function __construct(private Catalog $catalog, private DefinitionKinds $kinds, private CliManager $cli, private Container $container, private PathResolver $paths, private Redactor $redactor) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $identity): array
    {
        if (preg_match('/^[a-z][a-z0-9-]*:[a-z0-9][a-z0-9._-]*$/', $identity) !== 1) {
            throw GenerationException::invalid(sprintf('"%s" is not a definition identity (kind:name, e.g. module:academics).', $identity));
        }

        $descriptor = $this->catalog->descriptor($identity);

        if ($descriptor === null) {
            $similar = array_values(array_filter(array_map(static fn ($descriptor): string => $descriptor->key(), $this->catalog->descriptors()), static fn (string $key): bool => levenshtein($key, $identity) <= 3 || str_contains($key, substr($identity, (int) strpos($identity, ':') + 1))));

            throw GenerationException::missing(sprintf('No definition "%s" was discovered.%s List them with php artisan gcego:registry.', $identity, $similar === [] ? '' : ' Did you mean '.implode(', ', array_slice($similar, 0, 5)).'?'));
        }

        $definition = $this->catalog->definition($identity);

        if ($this->catalog->build()->failures === []) {
            // The package inspectors read the application's registry: let it see the files as they are now.
            $this->container->make(Registry::class)->refresh();
            $this->container->forgetScopedInstances();
        }

        $report = [
            'identity' => $descriptor->key(),
            'kind' => $descriptor->kind(),
            'name' => $descriptor->name(),
            'scope' => $descriptor->scope(),
            'source' => $descriptor->source->type->value === 'file' ? $this->paths->relative($descriptor->source->location) : $descriptor->source->toString(),
            'class' => $descriptor->class,
            'metadata' => $this->plain($descriptor->metadata),
        ];

        if ($definition instanceof ComponentInterface) {
            $report['properties'] = $this->plain($definition->effectiveProperties());
            $report['requirements'] = array_map(static fn (object $node): string => $node::class, iterator_to_array($definition->configurations()));
            $report['children'] = $this->children($definition, 1);
        }

        $problems = [];

        try {
            $problems = $definition === null ? ['The definition could not be loaded.'] : ($this->kinds->get($descriptor->kind())?->validate($definition) ?? []);
        } catch (Throwable $exception) {
            $problems = [$exception->getMessage()];
        }

        $report['valid'] = $problems === [];
        $report['problems'] = array_values($problems);
        $report['integration'] = $this->integration($descriptor->kind(), $descriptor->name(), $definition);

        foreach ($definition === null ? [] : $this->cli->inspectorsFor($descriptor->kind()) as $inspector) {
            assert(is_object($definition));

            try {
                $report['sections'][$inspector->id()] = $this->plain($inspector->inspect($definition, $descriptor));
            } catch (Throwable $exception) {
                $report['sections'][$inspector->id()] = ['error' => $exception->getMessage()];
            }
        }

        $redacted = $this->redactor->redact($report);
        assert(is_array($redacted));

        /** @var array<string, mixed> $redacted */
        return $redacted;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function integration(string $kind, string $name, ?object $definition): ?array
    {
        try {
            return match ($kind) {
                'module' => $this->container->make(ModuleManager::class)->inspect($name)->toArray(),
                'panel' => $this->container->make(PanelManager::class)->inspect($name)->toArray(),
                'page' => $definition instanceof Page ? $this->container->make(PageManager::class)->inspect($definition)->toArray() : null,
                'navigation' => $definition instanceof Navigation ? $this->container->make(NavigationManager::class)->inspect($definition)->toArray() : null,
                'layout' => $definition instanceof LayoutComponent ? $this->container->make(LayoutManager::class)->inspect($definition)->toArray() : null,
                default => null,
            };
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function children(ComponentInterface $component, int $depth): array
    {
        $children = [];

        foreach ($component->children() as $index => $child) {
            if ($index >= self::MaxChildren) {
                $children[] = ['truncated' => $component->childCount() - self::MaxChildren];

                break;
            }

            $children[] = [
                'class' => $child::class,
                'name' => $child instanceof Component ? $child->name() : null,
                'children' => $depth < self::MaxDepth ? $this->children($child, $depth + 1) : $child->childCount(),
            ];
        }

        return $children;
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Closure => 'Closure',
            is_object($value) => $value::class,
            is_array($value) => array_map(fn (mixed $item): mixed => $this->plain($item), $value),
            default => $value,
        };
    }
}
