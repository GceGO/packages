<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Commands\Concerns\PagesOutput;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Generation\PathResolver;
use Gcego\Cli\Inspection\Catalog;
use Gcego\Registry\Diagnostics\RegistryInspector;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\RegistryManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:registry [--kind=] [--limit=] [--offset=] [--format=json]
 */
final class RegistryCommand extends Command
{
    use PagesOutput;
    use RendersOutput;

    protected $signature = 'gcego:registry
        {--kind= : Only this registry kind}
        {--limit= : Rows to show (default gcego-cli.inspection.limit)}
        {--offset=0 : Rows to skip}
        {--format=text : Output format: text or json}';

    protected $description = 'Show the GCEGO registry: definitions, sources, discovery roots, cache status, duplicates and failures (read-only)';

    public function handle(Catalog $catalog, RegistryManager $manager, RegistryInspector $inspector, PathResolver $paths, CliConfig $config): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $kind = $this->option('kind');
            $kind = is_string($kind) && $kind !== '' ? $kind : null;
            $all = $catalog->descriptors($kind);
            [$limit, $offset] = $this->page($config->limit);
            $rows = array_map(static fn ($descriptor): array => [
                'identity' => $descriptor->key(),
                'kind' => $descriptor->kind(),
                'source' => $descriptor->source->type->value === 'file' ? $paths->relative($descriptor->source->location) : $descriptor->source->toString(),
                'class' => $descriptor->class,
            ], array_slice($all, $offset, $limit));
            $failures = array_map(static fn ($failure): array => [
                'source' => $paths->relative($failure->source),
                'duplicate' => $failure->exception instanceof DuplicateDefinitionException,
                'message' => $failure->exception->getMessage(),
            ], $catalog->build()->failures);
            $report = $inspector->inspect();
            $status = $manager->status();
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $exit = $failures === [] ? ExitCode::Success : ExitCode::ValidationFailed;
        $roots = array_map(static fn (array $root): array => [...$root, 'path' => $paths->relative((string) $root['path'])], $report->roots);

        if ($this->json()) {
            $this->emit(['total' => count($all), 'offset' => $offset, 'limit' => $limit, 'definitions' => $rows, 'counts' => $report->countsByKind, 'roots' => $roots, 'cache' => $status->toArray(), 'failures' => $failures, 'exit_code' => $exit]);

            return $exit;
        }

        $this->table(['Identity', 'Kind', 'Source'], array_map(static fn (array $row): array => [$row['identity'], $row['kind'], $row['source']], $rows));

        if (count($all) > $offset + count($rows)) {
            $this->line(sprintf('<fg=gray>Showing %d–%d of %d (use --offset and --limit).</>', $offset + 1, $offset + count($rows), count($all)));
        }

        $this->components->twoColumnDetail('Definitions by kind', implode(', ', array_map(static fn (string $kind, int $count): string => $kind.': '.$count, array_keys($report->countsByKind), $report->countsByKind)) ?: 'none');
        $this->components->twoColumnDetail('Cache', $status->enabled ? ($status->exists ? ($status->fresh === false ? '<fg=yellow>stale</>' : 'cached ('.($status->definitions ?? '?').')') : 'not built') : 'disabled');

        foreach ($roots as $root) {
            $this->components->twoColumnDetail('Root '.$root['path'], sprintf('%s%s', (string) $root['kind'], ($root['exists'] ?? false) ? '' : ' <fg=gray>(missing)</>'));
        }

        foreach ($failures as $failure) {
            $this->components->error(sprintf('%s %s: %s', $failure['duplicate'] ? 'Duplicate' : 'Failed', $failure['source'], $failure['message']));
        }

        return $exit;
    }
}
