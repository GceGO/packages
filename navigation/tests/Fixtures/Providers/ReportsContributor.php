<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Providers;

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\NavigationContributor;

/**
 * What a module would add: an item in the existing Administration group and a root item.
 */
final class ReportsContributor implements NavigationContributor
{
    public function navigation(): string
    {
        return 'admin';
    }

    public function nodes(): iterable
    {
        yield new Group(new Property(Group::Name, 'administration'), new Property(Group::Label, 'Administration'),
            new Item(new Property(Item::Name, 'audit'), new Property(Item::Label, 'Audit log'), new Property(Item::Url, '/admin/audit'), new Property(Item::Order, -1)),
        );
        yield new Item(new Property(Item::Name, 'help'), new Property(Item::Label, 'Help'), new Property(Item::Url, '/admin/help'), new Property(Item::Order, 99));
    }
}
