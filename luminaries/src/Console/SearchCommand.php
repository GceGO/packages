<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Console\Concerns\FormatsOutput;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Support\IconEnumGenerator;
use Illuminate\Console\Command;

/**
 * php artisan gcego:icons:search academic [--provider=lucide] [--limit=50]
 */
final class SearchCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:icons:search
        {query : Part of an icon or alias name}
        {--provider= : Only this provider ("alias" for aliases only)}
        {--limit=50 : Maximum number of results}
        {--format=text : Output format: text or json}';

    protected $description = 'Search icons and semantic aliases by name';

    public function handle(Luminaries $luminaries): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $provider = $this->option('provider');
        $provider = is_string($provider) && $provider !== '' ? $provider : null;

        if ($provider !== null && $provider !== 'alias' && ! $luminaries->hasProvider($provider)) {
            $this->components->error(sprintf('No provider named "%s". Run gcego:icons:providers.', $provider));

            return self::FAILURE;
        }

        $results = $luminaries->search((string) $this->argument('query'), $provider, max(1, (int) $this->option('limit')));

        $results = array_map(static fn (array $result): array => [...$result, 'enum' => IconEnumGenerator::reference($result['name'])], $results);

        if ($this->json()) {
            $this->emit(['results' => $results]);

            return self::SUCCESS;
        }

        if ($results === []) {
            $this->components->warn('No icons or aliases match.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Kind', 'Target', 'PHP', 'Blade'], array_map(static fn (array $result): array => [
            $result['name'],
            $result['kind'],
            $result['target'] ?? '',
            $result['enum'] ?? '',
            sprintf('<x-gcego-icon name="%s" />', $result['name']),
        ], $results));

        return self::SUCCESS;
    }
}
