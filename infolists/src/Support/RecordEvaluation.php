<?php

declare(strict_types=1);

namespace Gcego\Infolists\Support;

use Closure;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Facades\Components;
use Gcego\Infolists\Rendering\InfolistRenderContext;
use Gcego\Security\SecurityManager;

/**
 * Calls Filament-style closures for one record: $record (also by model
 * type), $root (the infolist's record, also inside repeatable items), $user,
 * plus extra named values such as $state and $entry.
 */
final class RecordEvaluation
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function context(mixed $record, array $parameters = []): EvaluationContext
    {
        $frame = app(InfolistRenderContext::class)->current();
        $parameters = ['record' => $record, 'root' => $frame === null ? $record : ($frame->root ?? $frame->record), 'user' => app(SecurityManager::class)->context()->user, ...$parameters];

        return EvaluationContext::make($parameters)->withTyped(...array_values(array_filter([$record, ...array_values($parameters)], is_object(...))));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function call(Closure $closure, mixed $record, array $parameters = [], ?ComponentInterface $component = null, string $subject = 'an infolist closure'): mixed
    {
        return Components::evaluator()->evaluate($closure, self::context($record, $parameters), $component, $subject);
    }
}
