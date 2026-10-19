<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

/**
 * The workflow inbox ("<panel>/workflows", route "<namespace>workflows")
 * and instance pages ("<panel>/workflows/{instance}", route
 * "<namespace>workflows.instance") in every gcego/panels panel, behind the
 * panel's authentication, tenancy and access checks. Instances the viewer
 * may not see (or of another tenant) are 404.
 */
final readonly class PanelWorkflowsExtension implements PanelExtension
{
    public const string RouteName = 'workflows';

    public function __construct(private WorkflowsConfig $config) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if (! $this->config->panels) {
            return;
        }

        $router->get(self::RouteName, static fn (PanelManager $panels) => $panels->respond(
            (string) __('gcego-workflows::messages.inbox'),
            new HtmlString((string) Livewire::mount('gcego-workflow-inbox', ['link' => url()->current().'/:instance'], 'gcego-workflow-inbox')),
        ))->name(self::RouteName);

        $router->get(self::RouteName.'/{instance}', static function (PanelManager $panels, WorkflowRepository $repository, WorkflowLabels $labels, string $instance) {
            $viewer = auth()->user();
            $record = $viewer instanceof Model ? $repository->find($instance, $viewer) : abort(403);

            return $panels->respond($labels->title($record), new HtmlString(view('gcego-workflows::pages.instance', ['record' => $record, 'labels' => $labels])->render()));
        })->where('instance', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name(self::RouteName.'.instance');
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
