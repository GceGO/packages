<?php

declare(strict_types=1);

namespace Gcego\Components\Examples\Values;

use Gcego\Components\Contracts\ValueObject;
use InvalidArgumentException;

final readonly class Money implements ValueObject
{
    public function __construct(public int $minorUnits, public string $currency)
    {
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter uppercase code.');
        }
    }

    /** @return array{minorUnits: int, currency: string} */
    public function normalize(): array
    {
        return ['minorUnits' => $this->minorUnits, 'currency' => $this->currency];
    }
}
