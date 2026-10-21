<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * An action over a selection of records (bulk toolbar):
 *
 *     new BulkAction('archive', 'Archive selected', new Property(BulkAction::Ability, 'update'),
 *         new Handler(fn (ActionContext $context) => $context->record->update(['archived' => true])))
 *
 * Selected keys are untrusted: they are capped, resolved through the scoped
 * source in chunks, and every record is authorized before anything runs.
 * The handler runs once per record. Confirmation per gcego-actions.confirmation.bulk.
 */
class BulkAction extends Action
{
    /** all-or-nothing (default) or partial (BulkPolicy). */
    public const string Policy = 'policy';

    public static function confirmationKey(): string
    {
        return 'bulk';
    }

    public function policy(): Bulk\BulkPolicy
    {
        $policy = $this->get(self::Policy);

        return is_string($policy) ? (Bulk\BulkPolicy::tryFrom($policy) ?? Bulk\BulkPolicy::AllOrNothing) : Bulk\BulkPolicy::AllOrNothing;
    }

    protected static function typeDefaults(): array
    {
        return ['intent' => ActionIntent::Secondary->value, 'placement' => ActionPlacement::Bulk, 'record' => false, 'success_message' => 'gcego-actions::messages.bulk_executed', 'order' => 0];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Policy, Type::string(), default: Bulk\BulkPolicy::AllOrNothing->value, allowedValues: array_map(static fn (Bulk\BulkPolicy $policy): string => $policy->value, Bulk\BulkPolicy::cases())));
    }
}
