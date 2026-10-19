<?php

declare(strict_types=1);

namespace Gcego\Actions\Configuration;

use Closure;
use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * The trusted server-side work of an action:
 *
 *     new Handler(PublishSubject::class)                                  // an ActionHandler from the container
 *     new Handler(fn (ActionContext $context) => $context->record->publish())
 *
 * Declared in code only. It is never serialized: normalization reports the
 * class name or "closure", and browsers can only name an action, never a handler.
 */
final readonly class Handler implements ConfigurationNode
{
    /**
     * @param  Closure|class-string<ActionHandler>  $handler
     */
    public function __construct(public Closure|string $handler)
    {
        if (is_string($handler) && ! is_a($handler, ActionHandler::class, true)) {
            throw InvalidActionException::invalid('handler', sprintf('%s does not implement %s.', $handler, ActionHandler::class));
        }
    }

    public function describe(): string
    {
        return $this->handler instanceof Closure ? 'closure' : $this->handler;
    }

    /**
     * @return array{handler: string}
     */
    public function normalize(): array
    {
        return ['handler' => $this->describe()];
    }
}
