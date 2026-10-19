<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Commands\Concerns\PagesOutput;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\RouteInspector;
use Illuminate\Console\Command;

/**
 * php artisan gcego:routes [--panel=admin] [--module=academics] [--format=json]
 */
final class RoutesCommand extends Command
{
    use PagesOutput;
    use RendersOutput;

    protected $signature = 'gcego:routes
        {--panel= : Only this panel\'s routes}
        {--module= : Only this module\'s routes}
        {--middleware : Show each route\'s middleware}
        {--limit= : Rows to show}
        {--offset=0 : Rows to skip}
        {--format=text : Output format: text or json}';

    protected $description = 'List the routes GCEGO registered (panels, pages, modules), read-only';

    public function handle(RouteInspector $inspector, CliConfig $config): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $panel = is_string($this->option('panel')) && $this->option('panel') !== '' ? (string) $this->option('panel') : null;
        $module = is_string($this->option('module')) && $this->option('module') !== '' ? (string) $this->option('module') : null;
        $all = $inspector->routes($panel, $module);
        [$limit, $offset] = $this->page($config->limit);
        $rows = array_slice($all, $offset, $limit);

        if ($this->json()) {
            $this->emit(['total' => count($all), 'offset' => $offset, 'limit' => $limit, 'routes' => $rows]);

            return ExitCode::Success;
        }

        $headers = ['Method', 'URI', 'Name', 'Panel'];
        $middleware = (bool) $this->option('middleware');

        if ($middleware) {
            $headers[] = 'Middleware';
        }

        $this->table($headers, array_map(static fn (array $route): array => [implode('|', $route['methods']), ($route['domain'] === null ? '' : $route['domain']).$route['uri'], $route['name'], $route['panel'] ?? '', ...($middleware ? [implode(', ', $route['middleware'])] : [])], $rows));
        $this->line(sprintf('<fg=gray>%d GCEGO routes%s.</>', count($all), count($all) > count($rows) ? sprintf(', showing %d from %d', count($rows), $offset + 1) : ''));

        return ExitCode::Success;
    }
}
