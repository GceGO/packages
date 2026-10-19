<?php

declare(strict_types=1);

namespace Gcego\Panels;

use Gcego\Pages\Page;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\Diagnostics\PanelInspector;
use Gcego\Panels\Diagnostics\PanelReport;
use Gcego\Panels\Exceptions\PanelNotFoundException;
use Gcego\Panels\Extensions\PanelExtensions;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Registry\PanelKind;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Panels\Shells\PanelShellFactory;
use Gcego\Panels\Switching\PanelLink;
use Gcego\Panels\Switching\PanelSwitcher;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point for panels:
 *
 *     app(PanelManager::class)->names();                          // ["admin", "school"]
 *     app(PanelManager::class)->current();                        // PanelContext of this request, or null
 *     app(PanelManager::class)->url('admin');                     // "/admin"
 *     app(PanelManager::class)->pageUrl('admin', 'overview');     // "/admin/overview"
 *     app(PanelManager::class)->switcher();                       // panels the user may switch to
 *     app(PanelManager::class)->inspect('admin');                 // PanelReport
 *
 * Services are resolved per call, so the manager is safe in long-running workers.
 */
final readonly class PanelManager
{
    public function __construct(private Container $container) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->definitions()->names();
    }

    public function has(string $name): bool
    {
        return $this->definitions()->has($name);
    }

    /**
     * @throws PanelNotFoundException
     */
    public function panel(string $name): Panel
    {
        return $this->definitions()->resolve($name);
    }

    /**
     * @throws PanelNotFoundException
     */
    public function descriptor(string $name): PanelDescriptor
    {
        return $this->definitions()->descriptor($name);
    }

    /**
     * The verified panel of the current request (null outside panels).
     */
    public function current(): ?PanelContext
    {
        return $this->container->make(PanelContextResolver::class)->current();
    }

    /**
     * The panel home URL (root-relative), or null when it has no route.
     *
     * @param  array<string, string|int>  $parameters  For example the tenant of a tenant panel.
     */
    public function url(string $name, array $parameters = []): ?string
    {
        $routes = $this->container->make(PanelRoutes::class);

        return $routes->url($routes->homeRoute($this->descriptor($name)), $parameters);
    }

    /**
     * The URL of a page in a panel, or null when the panel does not include it.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function pageUrl(string $name, string $page, string|int|null $record = null, array $parameters = []): ?string
    {
        $panel = $this->descriptor($name);
        $routes = $this->container->make(PanelRoutes::class);

        try {
            $identity = $this->container->make(PanelMembership::class)->pageIdentity($panel, $page);
        } catch (\Throwable) {
            return null;
        }

        if ($record !== null) {
            $parameters[Page::RecordParameter] = $record;
        }

        return $routes->url($routes->pageRoute($panel, $identity), $parameters);
    }

    /**
     * Panels the current user may switch to (empty outside panels).
     *
     * @return list<PanelLink>
     */
    public function switcher(): array
    {
        $current = $this->current();

        return $current === null ? [] : $this->container->make(PanelSwitcher::class)->links($current);
    }

    /**
     * Register a panel at runtime (tests, packages); discovery is the usual way.
     */
    public function register(Panel $panel, ?string $scope = null): DefinitionDescriptor
    {
        return $this->container->make(RegistryInterface::class)->register($panel, $scope);
    }

    /**
     * Add a panel extension (while booting, before routes are registered).
     *
     * @param  class-string<PanelExtension>  $extension
     */
    public function extend(string $extension): void
    {
        $this->container->make(PanelExtensions::class)->add($extension);
    }

    /**
     * A panel document (the current panel's shell) around trusted content,
     * for extension routes such as an inbox. Call it after AuthorizePanel.
     */
    public function respond(string $title, Htmlable $content): Response
    {
        $context = $this->container->make(PanelContextResolver::class)->require();

        return response()->view('gcego-panels::shells.document', [
            'shell' => $this->container->make(PanelShellFactory::class)->make($context, $title),
            'content' => $content,
        ]);
    }

    public function inspect(string $name): PanelReport
    {
        return $this->container->make(PanelInspector::class)->inspect($name);
    }

    /**
     * @return array<string, PanelReport>
     */
    public function inspectAll(): array
    {
        $reports = [];

        foreach ($this->names() as $name) {
            $reports[PanelKind::Id.':'.$name] = $this->inspect($name);
        }

        return $reports;
    }

    private function definitions(): PanelDefinitions
    {
        return $this->container->make(PanelDefinitions::class);
    }
}
