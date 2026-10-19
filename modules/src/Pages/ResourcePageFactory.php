<?php

declare(strict_types=1);

namespace Gcego\Modules\Pages;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\View;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Data\Query\Query;
use Gcego\Data\Source;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\RecordSetDefinition;
use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\ModulesConfig;
use Gcego\Modules\Operation;
use Gcego\Modules\Relationships\Attach;
use Gcego\Modules\Relationships\Detach;
use Gcego\Modules\Relationships\ParentAccess;
use Gcego\Modules\Relationships\ParentScope;
use Gcego\Modules\Relationships\RelatedRecords;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Page;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Types\CreatePage;
use Gcego\Pages\Types\EditPage;
use Gcego\Pages\Types\IndexPage;
use Gcego\Pages\Types\ViewPage;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns compiled resources and relations into gcego/pages pages, one per
 * supported operation, composed only of the declared definitions:
 *
 * | Operation | Page       | Children                                                                 |
 * | index     | IndexPage  | Source, Table, row/bulk/header actions, "New" link, View row action      |
 * | create    | CreatePage | Source, Form, the Create action                                           |
 * | view      | ViewPage   | Source, Infolist, record actions, "Edit" link, relationship links         |
 * | edit      | EditPage   | Source, Form, the Edit action, record actions                             |
 *
 * Every page carries the module's and resource's requirements, the
 * operation's permission (Resource::Permissions) and, by default, the
 * model's policy (viewAny, create; record pages authorize view/update on the
 * record). Relation pages also require the parent (ParentAccess) and their
 * Source is constrained to it; their Create, Attach and Detach actions get
 * handlers that link records through the relationship.
 */
final readonly class ResourcePageFactory
{
    public function __construct(private ModulesConfig $config) {}

    /**
     * @return list<Page>
     */
    public function pages(ModuleDefinition $module): array
    {
        $pages = [];

        foreach ($module->resources as $resource) {
            foreach ($resource->operations as $operation) {
                $pages[] = $this->page($resource, $operation);
            }

            foreach ($resource->pages as $page) {
                $pages[] = $page;
            }

            foreach ($resource->relations as $relation) {
                foreach ($relation->operations as $operation) {
                    $pages[] = $this->page($relation, $operation, $resource);
                }
            }
        }

        return [...$pages, ...$module->pages];
    }

    public function page(RecordSetDefinition $set, Operation $operation, ?ResourceDefinition $parent = null): Page
    {
        $items = [...$this->properties($set, $operation), ...$this->requirements($set, $operation), $set->source];

        return match ($operation) {
            Operation::Index => new IndexPage(...$items, ...$this->indexChildren($set)),
            Operation::Create => new CreatePage(...$items, ...$this->createChildren($set)),
            Operation::View => new ViewPage(...$items, ...$this->viewChildren($set)),
            Operation::Edit => new EditPage(...$items, ...$this->editChildren($set)),
        };
    }

    /**
     * @return list<Property>
     */
    private function properties(RecordSetDefinition $set, Operation $operation): array
    {
        $title = match ($operation) {
            Operation::Index => $set->pluralLabel,
            Operation::Create => self::text('gcego-modules::messages.titles.create', ['label' => $set->label]),
            Operation::View => 'gcego-modules::messages.titles.view',
            Operation::Edit => 'gcego-modules::messages.titles.edit',
        };
        $properties = [
            new Property(Page::Name, $set->pageName($operation)),
            new Property(Page::Title, $title),
            new Property(Page::Route, $set->routeName($operation)),
            new Property(Page::Path, $set->pagePath($operation)),
        ];

        if ($set->icon !== null) {
            $properties[] = new Property(Page::Icon, $set->icon);
        }

        if ($operation->isRecordPage() && $set->recordKey !== null) {
            $properties[] = new Property(Page::RecordKey, $set->recordKey);
        }

        if ($operation->isRecordPage() && $set->recordTitle !== null) {
            $properties[] = new Property(Page::RecordTitle, $set->recordTitle);
        }

        if ($set instanceof RelationDefinition) {
            $properties[] = new Property(Page::Where, [RelationDefinition::ParentParameter => RecordKey::pattern($set->parentRecordKey)]);
        }

        return $properties;
    }

    /**
     * @return list<Requirement>
     */
    private function requirements(RecordSetDefinition $set, Operation $operation): array
    {
        $requirements = $set->requirements;
        $permission = $set->permissions[$operation->value] ?? null;

        if ($permission !== null) {
            $requirements[] = new Permission($permission);
        }

        if ($this->config->authorizeByDefault) {
            $requirements[] = match (true) {
                $operation->isRecordPage() => new Authenticated,
                $set->model !== null && is_subclass_of($set->model, Model::class) => new Ability($operation->ability(), $set->model),
                default => new Authenticated,
            };
        }

        if ($set instanceof RelationDefinition) {
            $requirements[] = new ParentAccess($set->reference, $operation === Operation::Create ? 'update' : 'view');
        }

        return $requirements;
    }

    /**
     * @return list<ComponentInterface>
     */
    private function indexChildren(RecordSetDefinition $set): array
    {
        $children = [self::part($set->table, 'Table')];

        if ($this->exposes($set, Operation::Create)) {
            $children[] = new PageLink(
                self::text('gcego-modules::messages.links.create', ['label' => $set->label]),
                $set->pageName(Operation::Create),
                new Property(PageLink::Intent, 'primary'),
                new Property(PageLink::Icon, 'actions.create'),
            );
        }

        if ($set->has(Operation::View) && ! self::declares($set->actions, 'view')) {
            $children[] = new View;
        }

        return [...$children, ...$this->actions($set, $set->actions)];
    }

    /**
     * @return list<ComponentInterface>
     */
    private function createChildren(RecordSetDefinition $set): array
    {
        $action = $set->createAction ?? new Create(new Property(Action::Placement, ActionPlacement::FormFooter));

        if ($set instanceof RelationDefinition) {
            $reference = $set->reference;
            $action = $action->withConfigurations(...[
                new Handler(static fn (ActionContext $context): Model => Container::getInstance()->make(RelatedRecords::class)->create($context, $reference)),
                ...$action->configurations()->all(),
                new ParentAccess($reference, 'update'),
            ]);
        }

        return [self::part($set->form, 'Form'), $action];
    }

    /**
     * @return list<ComponentInterface>
     */
    private function viewChildren(RecordSetDefinition $set): array
    {
        $children = [self::part($set->infolist, 'Infolist')];

        if ($this->exposes($set, Operation::Edit)) {
            $children[] = new PageLink(self::text('gcego-modules::messages.links.edit'), $set->pageName(Operation::Edit));
        }

        if ($set instanceof ResourceDefinition) {
            foreach ($set->relations as $relation) {
                if ($relation->has(Operation::Index)) {
                    $children[] = new PageLink($relation->pluralLabel, $relation->pageName(Operation::Index), new Property(PageLink::Parameters, [RelationDefinition::ParentParameter => ':record']));
                }
            }
        }

        return [...$children, ...$this->actions($set, $set->actions)];
    }

    /**
     * @return list<ComponentInterface>
     */
    private function editChildren(RecordSetDefinition $set): array
    {
        return [self::part($set->form, 'Form'), $set->editAction ?? new Edit(new Property(Action::Placement, ActionPlacement::FormFooter)), ...$this->actions($set, $set->actions)];
    }

    /**
     * Relationship actions get their handler, input and parent requirement.
     *
     * @param  list<Action|ActionGroup>  $actions
     * @return list<Action|ActionGroup>
     */
    private function actions(RecordSetDefinition $set, array $actions): array
    {
        if (! $set instanceof RelationDefinition) {
            return $actions;
        }

        $reference = $set->reference;
        $candidates = $this->candidates($set);

        return array_map(static function (Action|ActionGroup $action) use ($reference, $candidates, $set): Action|ActionGroup {
            return match (true) {
                $action instanceof Attach => $action->withConfigurations(...[
                    new Handler(static fn (ActionContext $context): Model => Container::getInstance()->make(RelatedRecords::class)->attach($context, $reference, $candidates)),
                    new Input(['record' => ['required', 'string', 'max:'.RecordKey::MaxLength]], ['record' => $set->label]),
                    ...$action->configurations()->all(),
                    new ParentAccess($reference, 'update'),
                ]),
                $action instanceof Detach => $action->withConfigurations(...[
                    new Handler(static function (ActionContext $context) use ($reference): void {
                        Container::getInstance()->make(RelatedRecords::class)->detach($context, $reference);
                    }),
                    ...$action->configurations()->all(),
                    new ParentAccess($reference, 'update'),
                ]),
                default => $action,
            };
        }, $actions);
    }

    /**
     * The records Attach may choose from: the related records' Source
     * without the parent constraint (its own scopes still apply).
     */
    private function candidates(RelationDefinition $relation): Source
    {
        $configurations = array_values(array_filter(
            $relation->source->configurations()->all(),
            static fn (mixed $node): bool => ! ($node instanceof Query && $node->constraint instanceof ParentScope),
        ));

        return $relation->source->withConfigurations(...$configurations);
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  T|null  $part
     * @return T
     */
    private static function part(?ComponentInterface $part, string $name): ComponentInterface
    {
        return $part ?? throw InvalidModuleException::invalid($name, 'the operation needs it, but the definition has none.');
    }

    private function exposes(RecordSetDefinition $set, Operation $operation): bool
    {
        return $set instanceof ResourceDefinition ? $set->exposes($operation) : $set->has($operation);
    }

    /**
     * @param  list<Action|ActionGroup>  $actions
     */
    private static function declares(array $actions, string $name): bool
    {
        foreach ($actions as $action) {
            if ($action instanceof Action && $action->actionName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
