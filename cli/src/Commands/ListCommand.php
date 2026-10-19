<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Commands\Concerns\PagesOutput;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\Catalog;
use Gcego\Cli\Validation\DefinitionValidation;
use Gcego\Components\Component;
use Gcego\Modules\Resource;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:list [--type=modules] [--format=json]
 */
final class ListCommand extends Command
{
    use PagesOutput;
    use RendersOutput;

    protected $signature = 'gcego:list
        {--type= : panels, modules, resources, pages, blueprints, widgets, layouts, navigation, workflows, settings, analytics}
        {--limit= : Rows per type (default gcego-cli.inspection.limit)}
        {--offset=0 : Rows to skip per type}
        {--format=text : Output format: text or json}';

    protected $description = 'List the panels, modules, resources, pages, blueprints, widgets, workflows, settings and analytics definitions';

    private const array Types = ['panels', 'modules', 'resources', 'pages', 'blueprints', 'widgets', 'layouts', 'navigation', 'workflows', 'settings', 'analytics'];

    public function handle(Catalog $catalog, DefinitionValidation $validation, CliConfig $config): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $type = $this->option('type');
        $types = is_string($type) && $type !== '' ? [$type] : self::Types;
        [$limit, $offset] = $this->page($config->limit);
        $lists = [];

        try {
            foreach ($types as $type) {
                if (! in_array($type, self::Types, true)) {
                    throw GenerationException::invalid(sprintf('--type must be one of %s.', implode(', ', self::Types)));
                }

                $names = $type === 'resources' ? $this->resources($catalog) : array_merge(...array_map(static fn (string $kind): array => array_map(static fn ($descriptor): string => $descriptor->key(), $catalog->descriptors($kind)), $validation->kindsFor($type)));
                $lists[$type] = ['total' => count($names), 'items' => array_slice($names, $offset, $limit)];
            }
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        if ($this->json()) {
            $this->emit(['lists' => $lists]);

            return ExitCode::Success;
        }

        foreach ($lists as $type => $list) {
            $this->components->twoColumnDetail('<fg=green>'.ucfirst($type).'</>', (string) $list['total']);

            foreach ($list['items'] as $item) {
                $this->line('  '.$item);
            }

            if ($list['total'] > $offset + count($list['items'])) {
                $this->line(sprintf('  <fg=gray>… %d more (--offset, --limit)</>', $list['total'] - $offset - count($list['items'])));
            }
        }

        return ExitCode::Success;
    }

    /**
     * "module.resource" for every resource of every module.
     *
     * @return list<string>
     */
    private function resources(Catalog $catalog): array
    {
        $resources = [];

        foreach ($catalog->descriptors('module') as $descriptor) {
            $module = $catalog->definition($descriptor->key());

            foreach ($module instanceof Component ? $module->children() : [] as $child) {
                if ($child instanceof Resource) {
                    $resources[] = $descriptor->name().'.'.$child->name();
                }
            }
        }

        return $resources;
    }
}
