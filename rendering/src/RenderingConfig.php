<?php

declare(strict_types=1);

namespace Gcego\Rendering;

use Gcego\Rendering\Exceptions\InvalidRenderContextException;

/**
 * A validated, typed view of config('gcego-rendering').
 */
final readonly class RenderingConfig
{
    public function __construct(
        public bool $strict = true,
        public bool $validate = true,
        public int $maxDepth = 64,
        public string $ability = 'gcego-rendering.render',
        public bool $debug = false,
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws InvalidRenderContextException
     */
    public static function fromArray(array $config, bool $debug = false): self
    {
        $authorization = is_array($config['authorization'] ?? null) ? $config['authorization'] : [];
        $maxDepth = $config['max_depth'] ?? 64;
        $ability = $authorization['ability'] ?? 'gcego-rendering.render';

        if (! is_int($maxDepth) || $maxDepth < 1 || $maxDepth > 256) {
            throw InvalidRenderContextException::invalid('gcego-rendering.max_depth', 'must be an integer between 1 and 256.');
        }

        if (! is_string($ability) || $ability === '') {
            throw InvalidRenderContextException::invalid('gcego-rendering.authorization.ability', 'must be a non-empty Gate ability name.');
        }

        return new self(
            strict: self::bool($config['strict'] ?? true, 'strict'),
            validate: self::bool($config['validate'] ?? true, 'validate'),
            maxDepth: $maxDepth,
            ability: $ability,
            debug: $debug,
        );
    }

    private static function bool(mixed $value, string $key): bool
    {
        $parsed = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($parsed)) {
            throw InvalidRenderContextException::invalid('gcego-rendering.'.$key, 'must be a boolean.');
        }

        return $parsed;
    }
}
