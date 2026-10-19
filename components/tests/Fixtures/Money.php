<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Contracts\ValueObject;

final readonly class Money implements ValueObject
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    /**
     * @return array{amount: int, currency: string}
     */
    public function normalize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}
