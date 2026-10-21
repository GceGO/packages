<?php

declare(strict_types=1);

namespace Gcego\Settings\Pages;

use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingNotFoundException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\SettingsPage;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Which settings pages a viewer may open in a panel, and opening one:
 *
 * the page must appear in the panel, its scope must be resolvable from the
 * request alone (a tenant page needs the request's verified tenant), the
 * viewer must pass the page's requirements, and at least one of its
 * settings must be visible to them. Pages that fail are absent (404),
 * never half-rendered.
 */
final readonly class SettingsPageResolver
{
    public function __construct(private SettingsDefinitions $definitions, private ScopeResolver $scopes, private SettingsAuthorizer $authorizer) {}

    /**
     * @return list<SettingsScreen>
     */
    public function screens(?Authenticatable $user, ?string $panel): array
    {
        $screens = [];

        foreach ($this->definitions->pages() as $page) {
            $screen = $this->screen($page, $user, $panel);

            if ($screen !== null) {
                $screens[] = $screen;
            }
        }

        return $screens;
    }

    /**
     * @throws SettingNotFoundException|SettingAuthorizationException
     */
    public function open(string $name, ?Authenticatable $user, ?string $panel): SettingsScreen
    {
        $page = $this->definitions->page($name);

        return $this->screen($page, $user, $panel) ?? throw SettingNotFoundException::page($name);
    }

    public function screen(SettingsPage $page, ?Authenticatable $user, ?string $panel): ?SettingsScreen
    {
        if (! $page->appearsIn($panel) || $user === null) {
            return null;
        }

        try {
            $context = $this->scopes->forScreen($page->scope());
        } catch (SettingScopeException) {
            return null;
        }

        if (! $this->authorizer->canOpen($user, $page, $context)) {
            return null;
        }

        $visible = [];
        $editable = [];

        foreach ($this->definitions->inGroup($page->groupName()) as $definition) {
            if ($definition->hidden || ! $definition->allows($context->target) || ! $this->authorizer->canView($user, $definition, $context)) {
                continue;
            }

            $visible[] = $definition;
            $editable[$definition->key] = $this->authorizer->canUpdate($user, $definition, $context);
        }

        if ($visible === []) {
            return null;
        }

        return new SettingsScreen($page, $this->definitions->group($page->groupName()), $context, $visible, $editable);
    }

    /**
     * @param  list<SettingDefinition>  $definitions
     * @return array<string, SettingDefinition>
     */
    public static function byKey(array $definitions): array
    {
        $keyed = [];

        foreach ($definitions as $definition) {
            $keyed[$definition->key] = $definition;
        }

        return $keyed;
    }
}
