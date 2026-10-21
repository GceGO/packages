<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

/**
 * A definition of another registry kind, as a pages package would derive it.
 */
final readonly class PageDefinition
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $name,
        public string $class,
    ) {}
}
