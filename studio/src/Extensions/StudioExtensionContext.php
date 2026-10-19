<?php

declare(strict_types=1);

namespace Gcego\Studio\Extensions;

use Closure;
use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Studio\Contracts\InstallationStep;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Exceptions\ExtensionException;

/**
 * What a StudioExtension may contribute (collected, then applied by
 * Studio; nothing is executed here).
 */
final class StudioExtensionContext
{
    /** @var list<class-string<StudioHealthCheck>> */
    private array $checks = [];

    /** @var list<class-string<InstallationStep>> */
    private array $steps = [];

    /** @var list<class-string<PanelExtension>> */
    private array $panelExtensions = [];

    /** @var array<string, Closure(): array<string, mixed>> */
    private array $sections = [];

    public function __construct(public readonly string $extension) {}

    /**
     * @param  class-string<StudioHealthCheck>  $check
     */
    public function healthCheck(string $check): self
    {
        $this->checks[] = class_exists($check) && is_subclass_of($check, StudioHealthCheck::class) ? $check : throw ExtensionException::invalid($this->extension, sprintf('gave "%s", which is not a %s.', $check, StudioHealthCheck::class));

        return $this;
    }

    /**
     * @param  class-string<InstallationStep>  $step
     */
    public function installationStep(string $step): self
    {
        $this->steps[] = class_exists($step) && is_subclass_of($step, InstallationStep::class) ? $step : throw ExtensionException::invalid($this->extension, sprintf('gave "%s", which is not an %s.', $step, InstallationStep::class));

        return $this;
    }

    /**
     * @param  class-string<PanelExtension>  $extension
     */
    public function panelExtension(string $extension): self
    {
        $this->panelExtensions[] = class_exists($extension) && is_subclass_of($extension, PanelExtension::class) ? $extension : throw ExtensionException::invalid($this->extension, sprintf('gave "%s", which is not a %s.', $extension, PanelExtension::class));

        return $this;
    }

    /**
     * An inspector section (computed when inspected; return scalars, never secrets).
     *
     * @param  Closure(): array<string, mixed>  $section
     */
    public function inspectorSection(string $name, Closure $section): self
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $name) !== 1) {
            throw ExtensionException::invalid($this->extension, sprintf('gave an invalid inspector section name "%s".', $name));
        }

        $this->sections[$name] = $section;

        return $this;
    }

    /**
     * @return list<class-string<StudioHealthCheck>>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    /**
     * @return list<class-string<InstallationStep>>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<class-string<PanelExtension>>
     */
    public function panelExtensions(): array
    {
        return $this->panelExtensions;
    }

    /**
     * @return array<string, Closure(): array<string, mixed>>
     */
    public function sections(): array
    {
        return $this->sections;
    }
}
