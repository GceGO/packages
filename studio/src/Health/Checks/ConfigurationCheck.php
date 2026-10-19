<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Every package's typed configuration builds (each validates its own
 * values). Messages name the setting, never its value.
 */
final readonly class ConfigurationCheck implements StudioHealthCheck
{
    /** Typed configuration classes of the foundation packages (and Studio). */
    public const array Classes = [
        'gcego/studio' => 'Gcego\Studio\Configuration\StudioConfiguration',
        'gcego/registry' => 'Gcego\Registry\RegistryConfig',
        'gcego/runtime' => 'Gcego\Runtime\RuntimeConfig',
        'gcego/chroma' => 'Gcego\Chroma\ChromaConfig',
        'gcego/data' => 'Gcego\Data\DataConfig',
        'gcego/security' => 'Gcego\Security\SecurityConfig',
        'gcego/actions' => 'Gcego\Actions\ActionsConfig',
        'gcego/forms' => 'Gcego\Forms\FormsConfig',
        'gcego/tables' => 'Gcego\Tables\TablesConfig',
        'gcego/infolists' => 'Gcego\Infolists\InfolistsConfig',
        'gcego/widgets' => 'Gcego\Widgets\WidgetsConfig',
        'gcego/layouts' => 'Gcego\Layouts\LayoutsConfig',
        'gcego/navigation' => 'Gcego\Navigation\NavigationConfig',
        'gcego/pages' => 'Gcego\Pages\PagesConfig',
        'gcego/modules' => 'Gcego\Modules\ModulesConfig',
        'gcego/panels' => 'Gcego\Panels\PanelsConfig',
    ];

    public function __construct(private Container $container) {}

    public function id(): string
    {
        return 'configuration.valid';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $problems = [];
        $checked = 0;

        foreach (self::Classes as $package => $class) {
            if (! class_exists($class)) {
                continue;
            }

            try {
                $this->container->make($class);
                $checked++;
            } catch (Throwable $exception) {
                $problems[] = $package.': '.mb_substr($exception->getMessage(), 0, 300);
            }
        }

        return $problems === []
            ? HealthCheckResult::pass($this->id(), 'Configuration', sprintf('%d package configurations are valid.', $checked), 'gcego/studio')
            : HealthCheckResult::failure($this->id(), 'Configuration', implode(' ', $problems), 'Correct the named settings in config/ (each message says which and why).', 'gcego/studio');
    }
}
