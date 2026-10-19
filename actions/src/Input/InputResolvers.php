<?php

declare(strict_types=1);

namespace Gcego\Actions\Input;

use Gcego\Actions\Contracts\InputResolver;
use Gcego\Actions\Resolution\ResolvedAction;

/**
 * Registered InputResolvers (application lifetime). The most recently
 * registered resolver that supports an action wins; RulesInputResolver is last.
 */
final class InputResolvers
{
    /**
     * @var list<InputResolver>
     */
    private array $resolvers = [];

    public function register(InputResolver $resolver): void
    {
        array_unshift($this->resolvers, $resolver);
    }

    public function for(ResolvedAction $action, InputResolver $fallback): ?InputResolver
    {
        foreach ([...$this->resolvers, $fallback] as $resolver) {
            if ($resolver->supports($action)) {
                return $resolver;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->resolvers);
    }
}
