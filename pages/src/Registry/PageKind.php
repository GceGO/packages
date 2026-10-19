<?php

declare(strict_types=1);

namespace Gcego\Pages\Registry;

use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Pages\Page;
use Gcego\Pages\Routing\PageRouteDefinition;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The "page" definition kind of gcego/registry: pages are registered and
 * discovered (app/GCEGo/Pages, every PHP file) under "page:<name>", dots in
 * the name becoming "-" ("subjects.create" -> "page:subjects-create").
 * Clients refer to pages by that identity only, never by class.
 *
 * Validation is the blueprint validator's (structure, roles, parts, page
 * rules). The indexed metadata holds everything routing needs (path, route
 * name, middleware, constraints), so page routes are registered from the
 * index without loading a single page definition.
 */
final readonly class PageKind implements DefinitionKind
{
    public const string Id = 'page';

    public function __construct(private BlueprintValidator $validator = new BlueprintValidator) {}

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Page::class;
    }

    public function name(object $definition): string
    {
        $page = $this->page($definition);

        if (preg_match(Page::NamePattern, $page->pageName()) !== 1) {
            throw new InvalidArgumentException('A page needs a Name such as "subjects" or "subjects.create".');
        }

        return $page->registryName();
    }

    public function validate(object $definition): array
    {
        return array_map(
            static fn (BlueprintValidationError $error): string => sprintf('%s: %s', $error->path, $error->message),
            $this->validator->validate($this->page($definition))->errors(),
        );
    }

    public function metadata(object $definition): array
    {
        $page = $this->page($definition);
        $navigation = $page->navigation();

        return [
            'name' => $page->pageName(),
            'type' => $page::pageType(),
            'title' => $page->pageTitle(),
            'icon' => $page->icon(),
            'parent' => $page->parentName(),
            ...PageRouteDefinition::fromPage($page)->toMetadata(),
            'navigation' => $navigation['enabled'],
            'navigation_label' => $navigation['label'],
            'navigation_group' => $navigation['group'],
            'navigation_order' => $navigation['order'],
        ];
    }

    private function page(object $definition): Page
    {
        return $definition instanceof Page ? $definition : throw new InvalidArgumentException(sprintf('%s is not a page.', $definition::class));
    }
}
