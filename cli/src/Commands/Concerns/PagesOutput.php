<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Concerns;

/**
 * --limit / --offset, capped by gcego-cli.inspection.limit, for commands that list.
 */
trait PagesOutput
{
    /**
     * @return array{0: int, 1: int}
     */
    protected function page(int $max): array
    {
        $limit = $this->option('limit');
        $offset = $this->option('offset');
        $limit = is_numeric($limit) ? (int) $limit : $max;
        $offset = is_numeric($offset) ? (int) $offset : 0;

        return [max(1, min($limit, $max)), max(0, $offset)];
    }
}
