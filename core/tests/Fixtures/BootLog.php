<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

/**
 * Records boot and activation calls in order.
 */
final class BootLog
{
    /**
     * @var list<string>
     */
    public array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
