<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures;

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Components\Divider;
use Gcego\Navigation\Components\Dropdown;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Heading;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Navigation\Tests\Fixtures\Providers\SubjectBreadcrumbs;
use Gcego\Security\Requirements\TenantMember;

final class Definitions
{
    public static function admin(): Navigation
    {
        return new Navigation('admin', new Property(Navigation::Label, 'Administration'), new Property(Navigation::Home, 'dashboard'),
            new Item(new Property(Item::Name, 'dashboard'), new Property(Item::Label, 'Dashboard'), new Property(Item::Route, 'admin.dashboard'), new Property(Item::Icon, 'home')),
            new Group('Academic Management', new Property(Group::Icon, 'academic-cap'),
                new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Route, 'admin.students.index'), new Property(Item::Active, 'admin.students.*'), new Property(Item::Icon, 'users'), new Property(Item::Order, 20)),
                new Item(new Property(Item::Name, 'subjects'), new Property(Item::Label, 'Subjects'), new Property(Item::Route, 'admin.subjects.index'), new Property(Item::Active, 'admin.subjects.*'), new Property(Item::Order, 10), new Property(Item::Breadcrumbs, SubjectBreadcrumbs::class)),
                new Heading(new Property(Heading::Label, 'Planning'), new Property(Heading::Order, 30)),
                new Item(new Property(Item::Label, 'Topics'), new Property(Item::Link, '/admin/topics'), new Property(Item::Order, 40)),
            ),
            new Group(new Property(Group::Name, 'administration'), new Property(Group::Label, 'Administration'), new Property(Group::Collapsed, true), new TenantMember,
                new Item(new Property(Item::Name, 'institutions'), new Property(Item::Label, 'Institutions'), new Property(Item::Route, 'admin.institutions.index'), new Property(Item::Permission, 'institution.members.manage')),
                new Item(new Property(Item::Name, 'members'), new Property(Item::Label, 'Members'), new Property(Item::Route, 'admin.institutions.members'), new Property(Item::Parameters, ['institution' => ':tenant'])),
                new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Route, 'admin.requests.index'), new Property(Item::Badge, PendingRequestsBadge::class), new Property(Item::BadgeLabel, 'pending requests')),
            ),
            new Group(new Property(Group::Name, 'vault'), new Property(Group::Label, 'Vault'), new Property(Group::Permission, 'secret.view'),
                new Item('Secrets', '/admin/vault'),
            ),
            new Dropdown(new Property(Dropdown::Name, 'reports'), new Property(Dropdown::Label, 'Reports'), new Property(Dropdown::Align, 'end'),
                new Item(new Property(Item::Name, 'attendance'), new Property(Item::Label, 'Attendance'), new Property(Item::Route, 'admin.reports.attendance')),
                new Group('Finance', new Item(new Property(Item::Name, 'fees'), new Property(Item::Label, 'Fees'), new Property(Item::Url, '/admin/reports/fees'), new Property(Item::Badge, 'New'), new Property(Item::BadgeVariant, 'success'))),
            ),
            new Divider,
            new Item(new Property(Item::Name, 'missing'), new Property(Item::Label, 'Missing'), new Property(Item::Route, 'admin.missing')),
            new ExternalLink('Documentation', 'https://docs.example.com/gcego'),
        );
    }
}
