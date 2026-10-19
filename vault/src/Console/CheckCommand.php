<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Console\Concerns\FindsHolders;
use Gcego\Vault\Vault;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    use FindsHolders;

    protected $signature = 'gcego:vault:check {holder : Key or email} {permission?} {--tenant= : Scope ("institution:7"); global by default} {--panel= : Evaluate inside this panel}';

    protected $description = 'Explain whether someone holds a permission, and why; without a permission, list everything they hold';

    public function handle(Vault $vault): int
    {
        $holder = $this->findHolder((string) $this->argument('holder'));

        if ($holder === null) {
            $this->components->error('No account matches '.$this->argument('holder').'.');

            return self::FAILURE;
        }

        $tenant = is_string($this->option('tenant')) ? $this->option('tenant') : null;
        $panel = is_string($this->option('panel')) ? $this->option('panel') : null;
        $permission = $this->argument('permission');

        if (! is_string($permission) || $permission === '') {
            $effective = $vault->effective($holder, $tenant, $panel);
            $this->components->twoColumnDetail('Roles', implode(', ', $effective->roles) ?: '—');
            $this->components->twoColumnDetail('Inherited roles', implode(', ', $effective->inherited) ?: '—');
            $this->components->twoColumnDetail('Super administrator', $effective->super ? 'yes' : 'no');
            $this->table(['Permission', 'Granted by'], array_map(static fn (string $name): array => [$name, implode(', ', $effective->sources($name))], $effective->names()));

            return self::SUCCESS;
        }

        $explanation = $vault->explain($holder, $permission, $tenant, $panel);
        $explanation->allowed ? $this->components->info('ALLOWED — '.$explanation->message()) : $this->components->warn('DENIED — '.$explanation->message());

        return $explanation->allowed ? self::SUCCESS : 2;
    }
}
