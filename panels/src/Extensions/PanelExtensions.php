<?php

declare(strict_types=1);

namespace Gcego\Panels\Extensions;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;

/**
 * The registered panel extensions (gcego-panels.extensions, then
 * PanelManager::extend()), resolved lazily from the container.
 */
final class PanelExtensions
{
    /** @var list<class-string<PanelExtension>> */
    private array $classes;

    /** @var array<class-string<PanelExtension>, PanelExtension> */
    private array $resolved = [];

    public function __construct(private readonly Container $container, PanelsConfig $config)
    {
        $this->classes = $config->extensions;
    }

    /**
     * @param  class-string<PanelExtension>  $class
     */
    public function add(string $class): void
    {
        if (! is_a($class, PanelExtension::class, true)) {
            throw InvalidPanelException::invalid('Extension '.$class, 'it must implement '.PanelExtension::class.'.');
        }

        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
        }
    }

    /**
     * @return list<class-string<PanelExtension>>
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * @return list<PanelExtension>
     */
    public function all(): array
    {
        $extensions = [];

        foreach ($this->classes as $class) {
            if (! isset($this->resolved[$class])) {
                $extension = $this->container->make($class);
                $this->resolved[$class] = $extension instanceof PanelExtension ? $extension : throw InvalidPanelException::invalid('Extension '.$class, 'it must implement '.PanelExtension::class.'.');
            }

            $extensions[] = $this->resolved[$class];
        }

        return $extensions;
    }

    public function topbar(PanelShell $shell): HtmlString
    {
        return $this->join(array_map(static fn (PanelExtension $extension): ?string => $extension->topbar($shell)?->toHtml(), $this->all()));
    }

    public function body(PanelShell $shell): HtmlString
    {
        return $this->join(array_map(static fn (PanelExtension $extension): ?string => $extension->body($shell)?->toHtml(), $this->all()));
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function join(array $parts): HtmlString
    {
        return new HtmlString(implode("\n", array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== '')));
    }
}
