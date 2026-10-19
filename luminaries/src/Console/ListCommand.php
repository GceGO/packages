<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Console\Concerns\FormatsOutput;
use Gcego\Luminaries\Exceptions\LuminariesException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Illuminate\Console\Command;

/**
 * php artisan gcego:icons:list            semantic aliases and what they resolve to
 * php artisan gcego:icons:list lucide     every icon of a provider
 */
final class ListCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:icons:list
        {provider? : List the icons of this provider instead of the aliases}
        {--group= : Only aliases in this group (e.g. actions)}
        {--format=text : Output format: text or json}';

    protected $description = 'List semantic icon aliases, or the icons of one provider';

    public function handle(Luminaries $luminaries): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $provider = $this->argument('provider');

        if (is_string($provider)) {
            if (! $luminaries->hasProvider($provider)) {
                $this->components->error(sprintf('No provider named "%s". Run gcego:icons:providers.', $provider));

                return self::FAILURE;
            }

            $icons = array_map(static fn (string $icon): string => IconName::canonical($provider, $icon), $luminaries->icons($provider));

            if ($this->json()) {
                $this->emit(['provider' => $provider, 'icons' => $icons]);
            } else {
                foreach ($icons as $icon) {
                    $this->line($icon);
                }

                $this->components->info(sprintf('%d icons.', count($icons)));
            }

            return self::SUCCESS;
        }

        $group = $this->option('group');
        $rows = [];

        foreach ($luminaries->aliases() as $alias => $target) {
            if (is_string($group) && $group !== '' && ! str_starts_with($alias, $group.'.')) {
                continue;
            }

            try {
                $resolved = $luminaries->resolve($alias)->canonical();
            } catch (LuminariesException) {
                $resolved = null;
            }

            $rows[] = ['alias' => $alias, 'target' => $target, 'resolves_to' => $resolved, 'origin' => $luminaries->aliasOrigin($alias)];
        }

        if ($this->json()) {
            $this->emit(['aliases' => $rows]);

            return self::SUCCESS;
        }

        $this->table(['Alias', 'Target', 'Resolves to', 'Origin'], array_map(static fn (array $row): array => [
            $row['alias'],
            $row['target'],
            $row['resolves_to'] ?? '<fg=red>MISSING</>',
            (string) $row['origin'],
        ], $rows));

        return self::SUCCESS;
    }
}
