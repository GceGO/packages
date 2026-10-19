<?php

declare(strict_types=1);

namespace Gcego\Testing\Exceptions;

use InvalidArgumentException;

final class InvalidFixtureException extends InvalidArgumentException implements TestingException
{
    public static function invalid(string $fixture, string $reason): self
    {
        return new self(sprintf('The fixture "%s" %s.', $fixture, $reason));
    }
}
