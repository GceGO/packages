<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No such metric, dataset, report, dashboard, export or schedule for the viewer (404).
 */
final class AnalyticsNotFoundException extends NotFoundHttpException implements AnalyticsException
{
    public static function make(string $what): self
    {
        return new self(sprintf('The %s was not found.', $what));
    }
}
