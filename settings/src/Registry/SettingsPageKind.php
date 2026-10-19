<?php

declare(strict_types=1);

namespace Gcego\Settings\Registry;

use Gcego\Components\Validation\ValidationError;
use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Settings\SettingsPage;
use InvalidArgumentException;

/**
 * The "settings-page" kind of gcego/registry ("settings-page:general"),
 * discovered from app/GCEGo/SettingsPages (every PHP file returns a SettingsPage).
 */
final readonly class SettingsPageKind implements DefinitionKind
{
    public const string Id = 'settings-page';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return SettingsPage::class;
    }

    public function name(object $definition): string
    {
        $name = $this->page($definition)->pageName();

        if (! SettingsPage::validName($name)) {
            throw new InvalidArgumentException('A settings page needs a Name such as "general".');
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        return array_map(static fn (ValidationError $error): string => $error->format(), $this->page($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $page = $this->page($definition);

        return ['title' => $page->title(), 'group' => $page->group(), 'scope' => $page->scope()->value];
    }

    private function page(object $definition): SettingsPage
    {
        return $definition instanceof SettingsPage ? $definition : throw new InvalidArgumentException('Not a settings page.');
    }
}
