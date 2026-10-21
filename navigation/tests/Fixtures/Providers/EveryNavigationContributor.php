<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Providers;

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\MultipleNavigationsContributor;

/**
 * Adds a "Status" item to every navigation except "admin" (as gcego/panels does per panel).
 */
final class EveryNavigationContributor implements MultipleNavigationsContributor
{
    public function navigation(): string
    {
        return 'admin';
    }

    public function nodes(): iterable
    {
        return [];
    }

    public function contributesTo(string $identity): bool
    {
        return $identity !== 'navigation:admin';
    }

    public function nodesFor(string $identity): iterable
    {
        yield new Item(new Property(Item::Name, 'status'), new Property(Item::Label, 'Status of '.substr($identity, 11)), new Property(Item::Url, '/status'), new Property(Item::Order, 99));
    }
}
