<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

use Gcego\Widgets\Contracts\ChartAdapter;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * Registered chart adapters (filled at boot; "chartjs" is built in).
 */
final class ChartAdapters
{
    /**
     * @var array<string, ChartAdapter>
     */
    private array $adapters = [];

    public function register(ChartAdapter $adapter, bool $override = false): void
    {
        if (isset($this->adapters[$adapter->id()]) && ! $override) {
            throw InvalidWidgetException::invalid(sprintf('Chart adapter "%s"', $adapter->id()), 'it is already registered.');
        }

        $this->adapters[$adapter->id()] = $adapter;
    }

    public function has(string $id): bool
    {
        return isset($this->adapters[$id]);
    }

    public function get(string $id): ChartAdapter
    {
        return $this->adapters[$id] ?? throw InvalidWidgetException::invalid(sprintf('Chart adapter "%s"', $id), 'it is not registered.');
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->adapters);
    }
}
