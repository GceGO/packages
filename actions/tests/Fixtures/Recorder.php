<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures;

/**
 * Records lifecycle calls in order.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public static function hit(string $call): void
    {
        self::$calls[] = $call;
    }
}
