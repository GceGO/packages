<?php

declare(strict_types=1);

namespace Gcego\Security\Resources;

/**
 * The conventional module abilities. Values are Laravel policy method names.
 */
enum ResourceAbility: string
{
    case ViewAny = 'viewAny';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'forceDelete';

    /**
     * Whether the ability concerns one record (and therefore needs it).
     */
    public function needsRecord(): bool
    {
        return ! in_array($this, [self::ViewAny, self::Create], true);
    }

    /**
     * The conventional permission suffix: subjects.view_any, subjects.force_delete.
     */
    public function permissionSuffix(): string
    {
        return match ($this) {
            self::ViewAny => 'view_any',
            self::ForceDelete => 'force_delete',
            default => $this->value,
        };
    }
}
