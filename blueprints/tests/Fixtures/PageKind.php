<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

final readonly class PageKind implements DefinitionKind
{
    public function id(): string
    {
        return 'page';
    }

    public function contract(): string
    {
        return PageDefinition::class;
    }

    public function name(object $definition): string
    {
        return $this->page($definition)->name;
    }

    public function validate(object $definition): array
    {
        return [];
    }

    public function metadata(object $definition): array
    {
        return ['class' => $this->page($definition)->class];
    }

    private function page(object $definition): PageDefinition
    {
        return $definition instanceof PageDefinition ? $definition : throw new InvalidArgumentException('Expected a page.');
    }
}
