<?php

declare(strict_types=1);

namespace Gcego\Infolists\Diagnostics;

use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\DataManager;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Entries\Image;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\InfolistManager;
use Gcego\Infolists\Layout\LayoutComponent;
use Gcego\Infolists\Schema\SchemaCompiler;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Describes an infolist without finding any record: entries (path, type,
 * access, conditions), the relations it eager-loads, and findings: schema
 * (invalid, duplicates), source, paths (a dotted path that is neither a
 * relation nor readable from an attribute), hidden ($hidden attributes),
 * authorization (no requirement: everyone is denied), renderer, styling,
 * html (rich text entries), media (images on public disks without Private)
 * and performance (to-many relations inside repeatables). Never resolves
 * or reports record values.
 */
final readonly class InfolistInspector
{
    public function __construct(private Container $container) {}

    public function inspect(string|Infolist $definition): InfolistReport
    {
        $diagnostics = [];
        $resolved = null;
        $relations = [];

        try {
            if (is_string($definition)) {
                $resolved = $this->container->make(InfolistManager::class)->resolve($definition);
                $infolist = $resolved->infolist;
            } else {
                $infolist = $definition;
            }

            $schema = (new SchemaCompiler)->compile($infolist);
        } catch (Throwable $exception) {
            return new InfolistReport(is_string($definition) ? $definition : $definition->infolistName(), is_string($definition) ? $definition : null, [], [], [new InfolistDiagnostic('schema', InfolistDiagnostic::Error, $exception->getMessage())]);
        }

        foreach ($infolist->validate()->errors() as $error) {
            $diagnostics[] = new InfolistDiagnostic('schema', InfolistDiagnostic::Error, (string) $error);
        }

        $model = null;

        if ($resolved !== null) {
            $manager = $this->container->make(InfolistManager::class);
            $model = $manager->model($resolved);
            $relations = $manager->relations($resolved);

            if (! $this->container->make(DataManager::class)->inspect($resolved->source)->isHealthy()) {
                $diagnostics[] = new InfolistDiagnostic('source', InfolistDiagnostic::Error, 'The blueprint Source has problems; see DataManager::inspect() for details.');
            }

            $requirements = array_filter([...$resolved->blueprint->configurations()->all(), ...$infolist->configurations()->all()], static fn (mixed $node): bool => $node instanceof Requirement);

            if ($requirements === []) {
                $diagnostics[] = new InfolistDiagnostic('authorization', InfolistDiagnostic::Error, 'The blueprint declares no gcego/security requirement, so every user is denied; add an Ability, Permission or ResourceAccess node.');
            }

            foreach ($schema->relations as $path) {
                if (! isset($relations[$path]) && $model !== null && method_exists($model, explode('.', $path)[0]) && ! $this->isAttribute($model, $path)) {
                    $diagnostics[] = new InfolistDiagnostic('paths', InfolistDiagnostic::Warning, sprintf('"%s" is not a relation path of %s; entries reading it show the placeholder unless it is an array attribute.', $path, class_basename($model)), ['path' => $path]);
                }
            }
        }

        $hidden = $model === null ? [] : (new $model)->getHidden();

        foreach ($schema->entries as $path => $entry) {
            $details = ['entry' => $path];

            if (! str_contains($path, '.') && in_array($path, $hidden, true)) {
                $diagnostics[] = new InfolistDiagnostic('hidden', InfolistDiagnostic::Error, sprintf('Entry "%s" reads an attribute in the model\'s $hidden list; it always shows the placeholder.', $path), $details);
            }

            if ($entry instanceof Text && ($entry->get(Text::Html) === true || $entry->get(Text::Markdown) === true)) {
                $diagnostics[] = new InfolistDiagnostic('html', InfolistDiagnostic::Ok, sprintf('Entry "%s" renders rich text through the sanitizer.', $path), $details);
            }

            $disk = $entry instanceof Image ? $entry->get(Image::Disk) : null;

            if (is_string($disk) && $entry->get(Image::Private) !== true) {
                $diagnostics[] = new InfolistDiagnostic('media', InfolistDiagnostic::Warning, sprintf('Entry "%s" shows files from disk "%s" with public URLs; set Private for files that are not public.', $path, $disk), $details);
            }

            if ($entry instanceof RepeatableEntry) {
                foreach ($entry->entries() as $child) {
                    if ($child instanceof RepeatableEntry) {
                        $diagnostics[] = new InfolistDiagnostic('performance', InfolistDiagnostic::Warning, sprintf('Entry "%s" nests repeatable entries; each level is eager-loaded, keep collections small.', $path), $details);
                    }
                }
            }
        }

        $renderers = $this->container->bound(RendererRegistry::class) ? $this->container->make(RendererRegistry::class) : null;
        $slots = $this->container->bound(SlotRegistry::class) ? $this->container->make(SlotRegistry::class) : null;

        foreach (app(ComponentWalker::class)->preOrder($infolist) as $node) {
            $component = $node->component;

            if (! $component instanceof Infolist && ! $component instanceof Entry && ! $component instanceof LayoutComponent) {
                continue;
            }

            if ($renderers !== null && ! $renderers->has($component)) {
                $diagnostics[] = new InfolistDiagnostic('renderer', InfolistDiagnostic::Error, sprintf('%s has no renderer.', $component::class));
            }

            if ($slots !== null && $slots->for($component) === null) {
                $diagnostics[] = new InfolistDiagnostic('styling', InfolistDiagnostic::Warning, sprintf('%s has no styling slots.', $component::class));
            }
        }

        if ($diagnostics === []) {
            $diagnostics[] = new InfolistDiagnostic('infolist', InfolistDiagnostic::Ok, sprintf('Infolist "%s" has %d entr%s and no problems.', $infolist->infolistName(), count($schema->entries), count($schema->entries) === 1 ? 'y' : 'ies'));
        }

        return new InfolistReport(
            $infolist->infolistName(),
            $resolved?->definition,
            array_values(array_map(static fn (string $path, Entry $entry): array => [
                'path' => $path,
                'type' => $entry::class,
                'access' => $entry->access()?->read?->describe(),
                'masked' => $entry->access() !== null && $entry->access()->mask,
                'conditions' => count($entry->conditions()),
                'relations' => $entry->relations(),
            ], array_keys($schema->entries), $schema->entries)),
            array_keys($relations),
            $diagnostics,
        );
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function isAttribute(string $model, string $path): bool
    {
        $casts = (new $model)->getCasts();

        return isset($casts[explode('.', $path)[0]]);
    }
}
