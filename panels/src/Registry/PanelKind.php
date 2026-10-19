<?php

declare(strict_types=1);

namespace Gcego\Panels\Registry;

use Gcego\Components\Validation\ValidationError;
use Gcego\Panels\Panel;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The "panel" definition kind of gcego/registry ("panel:admin"), discovered
 * from app/GCEGo/Panels. The indexed metadata holds everything routing,
 * the switcher and navigation need, so booting never loads a panel.
 */
final readonly class PanelKind implements DefinitionKind
{
    public const string Id = 'panel';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Panel::class;
    }

    public function name(object $definition): string
    {
        $name = $this->panel($definition)->panelName();

        if (preg_match(Panel::NamePattern, $name) !== 1) {
            throw new InvalidArgumentException('A panel needs a Name such as "admin".');
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        return array_map(static fn (ValidationError $error): string => $error->format(), $this->panel($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $panel = $this->panel($definition);

        return [
            'title' => $panel->title(),
            'description' => is_string($description = $panel->get(Panel::Description)) ? $description : null,
            'path' => $panel->path(),
            'domain' => $panel->domain(),
            'route_name' => $panel->routeName(),
            'guard' => $panel->guard(),
            'middleware' => implode('|', $panel->middleware()),
            'tenant' => $panel->tenant(),
            'verified' => $panel->requiresVerifiedEmail(),
            'dashboard' => $panel->dashboard(),
            'navigation' => $panel->navigationName(),
            'modules' => implode(',', $panel->modules()),
            'pages' => implode(',', $panel->pages()),
            'unlisted' => implode(',', $panel->unlistedPages()),
            'order' => $panel->order(),
            'hidden' => $panel->isHidden(),
            'access' => $panel->declaresAccess(),
        ];
    }

    private function panel(object $definition): Panel
    {
        return $definition instanceof Panel ? $definition : throw new InvalidArgumentException('Not a panel.');
    }
}
