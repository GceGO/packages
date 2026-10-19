<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Gcego\Studio\Integration\FrameworkCatalog;

/**
 * Every panel declares who may enter (a requirement or an access policy).
 * Panels without one deny everybody (gcego/panels is deny-by-default), so
 * this is reported as a configuration error, not a vulnerability. Also
 * surfaces the panels', modules' and pages' own inspector errors.
 */
final readonly class PanelAccessCheck implements StudioHealthCheck
{
    public function __construct(private FrameworkCatalog $catalog, private StudioConfiguration $studio) {}

    public function id(): string
    {
        return 'panels.access';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $panels = $this->catalog->panels();

        if ($panels === []) {
            return HealthCheckResult::warning($this->id(), 'Panels', 'No panel is registered, so there is no administration interface.', 'Create one: php artisan gcego:install (or add app/GCEGo/Panels/admin.php).', 'gcego/panels');
        }

        $undeclared = [];
        $errors = [];

        foreach ($panels as $panel) {
            $identity = is_string($panel['identity'] ?? null) ? $panel['identity'] : '?';

            foreach (is_array($panel['diagnostics'] ?? null) ? $panel['diagnostics'] : [] as $diagnostic) {
                if (! is_array($diagnostic) || ($diagnostic['level'] ?? null) !== 'error') {
                    continue;
                }

                if (($diagnostic['code'] ?? null) === 'access.undeclared') {
                    $undeclared[] = $identity;
                } else {
                    $errors[] = $identity.': '.(is_string($diagnostic['message'] ?? null) ? $diagnostic['message'] : '');
                }
            }
        }

        foreach ([...$this->catalog->modules(), ...$this->catalog->pages()] as $report) {
            foreach (is_array($report['diagnostics'] ?? null) ? $report['diagnostics'] : [] as $diagnostic) {
                if (is_array($diagnostic) && ($diagnostic['level'] ?? null) === 'error') {
                    $owner = $report['module'] ?? $report['identity'] ?? null;
                    $errors[] = (is_string($owner) ? $owner : '?').': '.(is_string($diagnostic['message'] ?? null) ? $diagnostic['message'] : '');
                }
            }
        }

        if ($errors !== []) {
            return HealthCheckResult::failure($this->id(), 'Panels', implode(' ', array_slice($errors, 0, 20)), 'Fix the reported definitions (php artisan gcego:studio:inspect shows each report).', 'gcego/panels');
        }

        if ($undeclared !== [] && $this->studio->requireAuthorization) {
            return HealthCheckResult::failure($this->id(), 'Panels', sprintf('%s declare no access rule, so nobody can enter them.', implode(', ', $undeclared)), 'Give each panel a requirement (new Ability(\'access-admin\')) or an AccessPolicy, and define that ability.', 'gcego/panels');
        }

        return HealthCheckResult::pass($this->id(), 'Panels', sprintf('%d panels declare their access rules.', count($panels)), 'gcego/panels');
    }
}
