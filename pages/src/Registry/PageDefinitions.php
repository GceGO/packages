<?php

declare(strict_types=1);

namespace Gcego\Pages\Registry;

use Gcego\Pages\Exceptions\PageNotFoundException;
use Gcego\Pages\Page;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Throwable;

/**
 * Registered pages by name or identity, through gcego/registry only:
 *
 *     $pages->identity('subjects.create');                           // "page:subjects-create"
 *     $pages->identity('subjects.create', 'page:school.dashboard');  // "page:school.subjects-create" (same registry scope)
 *     $pages->resolve('page:subjects-create');                       // the Page (404 when unknown)
 *
 * A page name is resolved relative to another page's identity when one is
 * given, so pages of a scoped discovery root ("page:school.…") link to,
 * and descend from, pages of the same scope.
 *
 * Anything that is not a page identity or name (a class name, a path, a
 * blueprint identity) is 404, so a request can never name a class.
 */
final readonly class PageDefinitions
{
    private const string Segment = '[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*';

    public function __construct(private RegistryInterface $registry) {}

    /**
     * The canonical identity of a page name or identity ("page:name" or
     * "page:scope.name"), a name taking the registry scope of $relativeTo.
     *
     * @throws PageNotFoundException when it cannot be a page identity.
     */
    public function identity(string $page, ?string $relativeTo = null): string
    {
        if (str_starts_with($page, PageKind::Id.':')) {
            $identity = $page;
        } else {
            $scope = $relativeTo === null ? null : self::scopeOf($relativeTo);
            $identity = PageKind::Id.':'.($scope === null ? '' : $scope.'.').str_replace('.', '-', $page);
        }

        if (strlen($identity) > 190 || preg_match('/^page:(?:'.self::Segment.'\.)*'.self::Segment.'$/', $identity) !== 1) {
            throw PageNotFoundException::make();
        }

        return $identity;
    }

    /**
     * The registry scope of a page identity ("page:school.subjects" -> "school"), or null.
     */
    public static function scopeOf(string $identity): ?string
    {
        $name = str_starts_with($identity, PageKind::Id.':') ? substr($identity, strlen(PageKind::Id) + 1) : '';
        $dot = strrpos($name, '.');

        return $dot === false ? null : substr($name, 0, $dot);
    }

    public function has(string $page, ?string $relativeTo = null): bool
    {
        try {
            return $this->registry->has($this->identity($page, $relativeTo));
        } catch (PageNotFoundException) {
            return false;
        }
    }

    /**
     * @throws PageNotFoundException
     */
    public function resolve(string $page, ?string $relativeTo = null): Page
    {
        $identity = $this->identity($page, $relativeTo);

        try {
            $definition = $this->registry->get($identity);
        } catch (Throwable) {
            throw PageNotFoundException::make();
        }

        return $definition instanceof Page ? $definition : throw PageNotFoundException::make();
    }

    /**
     * The indexed descriptor of a page (no definition is loaded).
     *
     * @throws PageNotFoundException
     */
    public function descriptor(string $page): DefinitionDescriptor
    {
        try {
            $descriptor = $this->registry->descriptor($this->identity($page));
        } catch (PageNotFoundException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw PageNotFoundException::make();
        }

        return $descriptor->kind() === PageKind::Id ? $descriptor : throw PageNotFoundException::make();
    }

    /**
     * Every registered page descriptor, ordered by identity.
     *
     * @return list<DefinitionDescriptor>
     */
    public function descriptors(): array
    {
        $descriptors = $this->registry->descriptors(PageKind::Id);
        usort($descriptors, static fn (DefinitionDescriptor $a, DefinitionDescriptor $b): int => strcmp($a->key(), $b->key()));

        return $descriptors;
    }
}
