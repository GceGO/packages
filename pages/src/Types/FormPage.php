<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Actions\Action;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Pages\Page;

/**
 * A page around a gcego/forms Form whose submission runs one gcego/actions
 * action (CreatePage: "create", EditPage: "edit"). Without an action of that
 * name, the page adds the default one, placed in the form footer only, so
 * the form host submits through it and the page header does not show it.
 */
abstract class FormPage extends Page
{
    public function __construct(mixed ...$items)
    {
        foreach ($items as $item) {
            if ($item instanceof Action && $item->actionName() === static::formAction()) {
                parent::__construct(...$items);

                return;
            }
        }

        parent::__construct(...[...$items, static::defaultFormAction()]);
    }

    public static function requiredRole(): string
    {
        return BlueprintRole::Form;
    }

    /**
     * The name of the action the form submits.
     */
    abstract public static function formAction(): string;

    abstract protected static function defaultFormAction(): Action;
}
