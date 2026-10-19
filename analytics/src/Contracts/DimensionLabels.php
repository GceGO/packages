<?php

declare(strict_types=1);

namespace Gcego\Analytics\Contracts;

use Gcego\Analytics\Security\AnalyticsContext;

/**
 * Display labels of dimension keys (trusted code named by a Dimension).
 * Return labels only for keys the viewer may know about; keys without a
 * label are shown as they are.
 */
interface DimensionLabels
{
    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function labels(array $keys, AnalyticsContext $context): array;
}
