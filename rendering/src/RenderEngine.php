<?php

declare(strict_types=1);

namespace Gcego\Rendering;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Contracts\RenderAuthorizer;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\Renderers\ComponentRenderer;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Support\Identifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;

/**
 * The rendering entry point (a stateless container singleton).
 *
 *     Rendering::render($component);                // trusted server code: no authorization
 *     Rendering::render('table:subjects');          // resolved through gcego/registry
 *     Rendering::renderAuthorized('table:subjects'); // public entry points: RenderAuthorizer first
 *     app(Runtime::class)->execute(Rendering::operation('table:subjects'));  // through the execution kernel
 *
 * Rendering is the Gcego\Rendering\Facades\Rendering facade over this singleton.
 *
 * Lifecycle: resolve -> (authorize) -> validate -> context -> renderer -> lazy Blade output.
 */
final readonly class RenderEngine
{
    /** The operation category gcego/rendering declares in gcego/runtime. */
    public const string OperationType = 'render';

    public function __construct(
        private ComponentRenderer $renderer,
        private RendererRegistry $renderers,
        private RegistryInterface $registry,
        private RenderAuthorizer $authorizer,
        private RenderingConfig $config,
        private Application $app,
    ) {}

    /**
     * A render operation for gcego/runtime's execute(): input "mode", "theme", "heading_level".
     */
    public static function operation(string $target): Operation
    {
        return Operation::make(self::OperationType)->on($target);
    }

    /**
     * @param  string|null  $identity  The registry identity of a supplied component, for stable DOM ids and markers.
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     */
    public function render(
        ComponentInterface|string $target,
        RenderMode $mode = RenderMode::Static,
        ?string $identity = null,
        ?string $panel = null,
        ?string $theme = null,
        int $headingLevel = 1,
        array $metadata = [],
        array $state = [],
    ): RenderResult {
        [$component, $identity] = is_string($target)
            ? [$this->resolve($target), $this->descriptor($target)->key()]
            : [$target, $identity];

        return $this->renderComponent($component, $identity, $mode, $panel, $theme, $headingLevel, $metadata, $state);
    }

    /**
     * Resolve, authorize and render a registered definition (for public entry points).
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     *
     * @throws AuthorizationException
     */
    public function renderAuthorized(
        string $identifier,
        RenderMode $mode = RenderMode::Static,
        ?string $panel = null,
        ?string $theme = null,
        int $headingLevel = 1,
        array $metadata = [],
        array $state = [],
    ): RenderResult {
        $descriptor = $this->descriptor($identifier);
        $this->authorizer->authorize($descriptor);

        return $this->renderComponent($this->resolve($identifier), $descriptor->key(), $mode, $panel, $theme, $headingLevel, $metadata, $state);
    }

    /**
     * The registered blueprint behind an identifier, after the RenderAuthorizer allowed it.
     *
     * Authorization runs on the descriptor, before any definition code is loaded.
     *
     * @throws AuthorizationException|RenderException
     */
    public function authorizedBlueprint(string $identifier): BlueprintInterface
    {
        $descriptor = $this->descriptor($identifier);

        if ($descriptor->kind() !== BlueprintKind::Id) {
            throw RenderException::notBlueprint($descriptor->key(), $descriptor->kind());
        }

        $this->authorizer->authorize($descriptor);

        return $this->registry->get($descriptor->key(), BlueprintInterface::class);
    }

    /**
     * The canonical identity for an identifier ("subjects" -> "table:subjects").
     */
    public function identityOf(string $identifier): string
    {
        return $this->descriptor($identifier)->key();
    }

    public function canRender(ComponentInterface $component): bool
    {
        return $this->renderers->has($component);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     */
    private function renderComponent(ComponentInterface $component, ?string $identity, RenderMode $mode, ?string $panel, ?string $theme, int $headingLevel, array $metadata, array $state): RenderResult
    {
        $component = $this->validated($component, $identity ?? $component->type());

        $context = RenderContext::root(
            $component,
            identity: $identity,
            mode: $mode,
            locale: $this->app->getLocale(),
            panel: $panel,
            theme: $theme,
            headingLevel: $headingLevel,
            metadata: $metadata,
            state: $state,
        );

        return $this->renderer->render($component, $context);
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  T  $component
     * @return T
     */
    private function validated(ComponentInterface $component, string $identity): ComponentInterface
    {
        if (! $this->config->validate) {
            return $component;
        }

        try {
            return $component->assertValid();
        } catch (InvalidComponentException $exception) {
            throw RenderException::invalidDefinition($identity, $exception);
        }
    }

    private function resolve(string $identifier): ComponentInterface
    {
        $descriptor = $this->descriptor($identifier);
        $definition = $this->registry->get($descriptor->key());

        if (! $definition instanceof ComponentInterface) {
            throw RenderException::notRenderable($descriptor->key(), $definition::class);
        }

        return $definition;
    }

    /**
     * Only registry identities are accepted: never class names or paths.
     */
    private function descriptor(string $identifier): DefinitionDescriptor
    {
        if (! Identifiers::isDefinition($identifier)) {
            throw RenderException::invalidIdentifier($identifier);
        }

        return $this->registry->descriptor($identifier);
    }
}
