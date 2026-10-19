<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Blueprints\BlueprintRole;

/**
 * A page a resource can have. Each needs the definition it coordinates; a
 * resource gets an operation's page only when it declares that definition
 * (and, for create and edit, the Create or Edit action, or lists the
 * operation in Resource::Operations).
 *
 * Deleting, restoring and force-deleting are gcego/actions actions
 * (Delete, Restore, ForceDelete), never implied.
 */
enum Operation: string
{
    case Index = 'index';
    case Create = 'create';
    case View = 'view';
    case Edit = 'edit';

    /**
     * The blueprint role the page needs (Table, Form or Infolist).
     */
    public function requiredRole(): string
    {
        return match ($this) {
            self::Index => BlueprintRole::Table,
            self::Create, self::Edit => BlueprintRole::Form,
            self::View => BlueprintRole::Infolist,
        };
    }

    public function isRecordPage(): bool
    {
        return $this === self::View || $this === self::Edit;
    }

    /**
     * The policy ability authorized by default: on the model class for the
     * list and the create page, on the record for record pages (by gcego/pages).
     */
    public function ability(): string
    {
        return match ($this) {
            self::Index => 'viewAny',
            self::Create => 'create',
            self::View => 'view',
            self::Edit => 'update',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $operation): string => $operation->value, self::cases());
    }
}
