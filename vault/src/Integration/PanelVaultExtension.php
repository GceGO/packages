<?php

declare(strict_types=1);

namespace Gcego\Vault\Integration;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Support\Context;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

/**
 * The Vault screens in each panel ("<panel>/vault/...", routes
 * "<namespace>vault.<screen>"), registered inside the panel's route group,
 * so the panel's authentication, tenancy and access checks run first. Each
 * route then requires its own Vault permission where the request acts
 * (403 otherwise), and every Livewire action re-checks on the server.
 */
final readonly class PanelVaultExtension implements PanelExtension
{
    public function __construct(private VaultConfig $config) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if (! $this->config->enabled || ! $this->config->showsIn($panel->name)) {
            return;
        }

        foreach (Screens::all() as $name => $screen) {
            $router->get(trim($this->config->panelPath.'/'.$screen['path'], '/'), static function (PanelManager $panels, Authority $authority, Context $context, ...$parameters) use ($screen): mixed {
                if ($screen['permission'] !== null) {
                    $authority->authorize($context->actor(), $screen['permission'], $context->tenant());
                }

                $mount = array_filter(['role' => request()->route('role'), 'holder' => request()->route('holder')], static fn (mixed $value): bool => is_string($value));

                return $panels->respond(
                    (string) __('gcego-vault::messages.screens.'.$screen['label']),
                    new HtmlString((string) Livewire::mount($screen['component'], $mount, $screen['component'])),
                );
            })->where(['role' => '[0-9]{1,18}', 'holder' => '[0-9]{1,2}-[A-Za-z0-9_-]{1,64}'])->name('vault.'.$name);
        }
    }

    public function topbar(PanelShell $shell): ?Htmlable
    {
        return null;
    }

    public function body(PanelShell $shell): ?Htmlable
    {
        return null;
    }
}
