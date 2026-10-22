<?php

declare(strict_types=1);

namespace Gcego\Rendering\Execution;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Contracts\RenderAuthorizer;
use Gcego\Rendering\Contracts\RenderHandler;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\RenderEngine;
use Gcego\Runtime\Contracts\AuthorizesOperation;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Support\Identifiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Validation\Rule;

/**
 * Handler for render operations (RenderEngine::operation('kind:name')), registered in
 * gcego/runtime's HandlerRegistry under the "render" category gcego/rendering declares.
 *
 * Renders exactly like RenderEngine::renderAuthorized(): the RenderAuthorizer must
 * also allow the definition. The output is rendered eagerly, so failures
 * surface inside the pipeline as categorized exceptions.
 *
 * Input: mode ("static"|"livewire"), theme, heading_level (1-6). The panel
 * comes from the trusted context, never from input.
 */
final readonly class RenderOperationHandler implements AuthorizesOperation, RenderHandler, ValidatesOperation
{
    public function __construct(
        private RenderEngine $engine,
        private RenderAuthorizer $authorizer,
        private AuthFactory $auth,
    ) {}

    public function rules(ExecutionRequest $request): array
    {
        return [
            'mode' => ['sometimes', Rule::enum(RenderMode::class)],
            'theme' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:'.Identifiers::NamePattern],
            'heading_level' => ['sometimes', 'integer', 'between:1,6'],
        ];
    }

    /**
     * The RenderAuthorizer checks the signed-in user, so it can only speak for
     * contexts of that same user. Contexts for anyone else are denied (fail
     * closed); system contexts are trusted.
     */
    public function authorize(ExecutionRequest $request): bool
    {
        $context = $request->context;

        if ($context->isSystem()) {
            return true;
        }

        $current = $this->auth->guard()->user();

        if (! self::sameUser($current, $context->user)) {
            return false;
        }

        try {
            $this->authorizer->authorize($request->target()->descriptor);
        } catch (AuthorizationException) {
            return false;
        }

        return true;
    }

    public function handle(ExecutionRequest $request): RenderResult
    {
        $target = $request->target();
        $component = $target->definition();

        if (! $component instanceof ComponentInterface) {
            throw InvalidOperationException::unexpectedTarget($target->identity(), 'component', $component::class);
        }

        $mode = $request->input('mode');
        $theme = $request->input('theme');
        $headingLevel = $request->input('heading_level', 1);

        $html = $this->engine->render(
            $component,
            mode: is_string($mode) ? RenderMode::from($mode) : RenderMode::Static,
            identity: $target->identity(),
            panel: $request->context->panel,
            theme: is_string($theme) ? $theme : null,
            headingLevel: is_numeric($headingLevel) ? (int) $headingLevel : 1,
        )->toHtml();

        return RenderResult::trustedHtml($html);
    }

    private static function sameUser(?Authenticatable $current, ?Authenticatable $user): bool
    {
        if ($current === null || $user === null) {
            return $current === $user;
        }

        return $current::class === $user::class && $current->getAuthIdentifier() === $user->getAuthIdentifier();
    }
}
