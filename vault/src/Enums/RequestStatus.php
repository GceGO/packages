<?php

declare(strict_types=1);

namespace Gcego\Vault\Enums;

/**
 * The life of an access request: pending until enough approvals (approved)
 * or one rejection (rejected), or withdrawn by the requester (cancelled).
 */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return (string) __('gcego-vault::messages.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
