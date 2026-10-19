<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

enum ActionStatus: string
{
    case Success = 'success';
    case ValidationFailed = 'validation_failed';
    case Denied = 'denied';
    case ConfirmationRequired = 'confirmation_required';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case Failed = 'failed';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Success => 200,
            self::ValidationFailed => 422,
            self::Denied => 403,
            self::ConfirmationRequired => 409,
            self::NotFound => 404,
            self::Conflict => 409,
            self::Failed => 500,
        };
    }
}
