<?php

declare(strict_types=1);

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Facades\Components;
use Gcego\Components\Properties\Property;

// Load this file from a booted Laravel application with the package provider registered.
$canEditProfile = true;
$showAuditLog = true;

$users = [
    ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test', 'role' => 'admin'],
    ['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.test', 'role' => 'editor'],
];

$metrics = Component::collect([
    Component::stat('users')->label('Users')->value('128')->icon('users'),
    Component::stat('orders')->label('Orders')->value('42')->icon('shopping-bag'),
    Component::stat('revenue')->label('Revenue')->value('250,000 XAF')->icon('banknotes'),
]);

$profileForm = Component::form('profile')
    ->label('Profile')
    ->action('/admin/profile')
    ->method('PATCH')
    ->setIfMissing('disabled', false)
    ->components([
        Component::textInput('name')
            ->label('Full name')
            ->required()
            ->minLength(2)
            ->maxLength(100)
            ->value('Ada'),
        Component::emailInput('email')
            ->label('Email address')
            ->required()
            ->autocomplete('email')
            ->value('ada@example.test'),
        Component::select('role')
            ->label('Role')
            ->options(['admin' => 'Administrator', 'editor' => 'Editor'])
            ->searchable()
            ->value('admin'),
        Component::numberInput('itemsPerPage')
            ->label('Items per page')
            ->min(5)
            ->max(100)
            ->step(5)
            ->value(20),
        Component::datePicker('joinedAt')->label('Joined at')->format('Y-m-d'),
        Component::toggle('notifications')->label('Email notifications')->checked(),
    ])
    ->when($canEditProfile,
        static fn (ComponentBuilder $builder): ComponentBuilder => $builder->child(
            Component::button('saveProfile')->label('Save profile')->buttonType('submit'),
        ),
        static fn (ComponentBuilder $builder): ComponentBuilder => $builder->disabled(),
    );

$activity = Component::timeline('activity')->components([
    Component::timelineItem('created')->title('Account created')->date('2026-01-10'),
    Component::timelineItem('updated')->title('Profile updated')->date('2026-01-12'),
]);

return Components::schema([
    Component::section('navigation')->label('Navigation')->components([
        Component::menu('mainMenu')->components([
            Component::menuItem('dashboard')->label('Dashboard')->url('/admin')->active(),
            Component::menuItem('users')->label('Users')->url('/admin/users'),
            Component::menuItem('settings')->label('Settings')->components([
                Component::menuItem('profile')->label('Profile')->url('/admin/profile'),
            ]),
        ]),
        Component::breadcrumbs('location')->components([
            Component::breadcrumb('home')->label('Home')->url('/'),
            Component::breadcrumb('admin')->label('Admin')->url('/admin'),
        ]),
    ]),
    Component::section('dashboard')->label('Dashboard')->components([
        Component::heading('title')->content('Administration')->level(1),
        Component::alert('welcome')
            ->title('Welcome back')
            ->message('Your workspace is ready.')
            ->status('info')
            ->dismissible(),
        Component::grid('metrics')->columns(3)->gap('lg')->components($metrics),
        Component::tabs('workspace')->active('overview')->components([
            Component::tab('overview')->title('Overview')->components([
                Component::card('analytics')->title('Weekly visits')->components([
                    Component::chart('visits')->chartType('line')->data([
                        'labels' => ['Monday', 'Tuesday', 'Wednesday'],
                        'series' => [120, 180, 150],
                    ]),
                    Component::progress('goal')->label('Monthly goal')->value(72)->max(100),
                ]),
                Component::table('users')
                    ->setMany(['label' => 'Users', 'id' => 'users-table'])
                    ->data($users)
                    ->pageSize(20)
                    ->searchable()
                    ->sortable()
                    ->components([
                        Component::tableColumn('name')->label('Name')->field('name')->sortable(),
                        Component::tableColumn('email')->label('Email')->field('email'),
                        Component::tableColumn('role')->label('Role')->field('role'),
                    ]),
            ]),
            Component::tab('profile')->title('Profile')->components([
                $profileForm,
                Component::image('profileImage')->src('/images/profile.png')->alt('Profile picture')->width(96)->height(96),
            ]),
            $showAuditLog ? Component::tab('audit')->title('Activity')->components($activity) : null,
        ]),
        Component::group('help')->components([
            Component::text('helpText')->content('Use the navigation to manage your workspace.'),
            Component::link('documentation')->label('Documentation')->url('/help'),
        ]),
        Component::modal('confirmDelete')
            ->title('Confirm deletion')
            ->open(false)
            ->dismissible()
            ->components([
                Component::text('warning')->content('Review the selected item before deleting it.'),
                Component::buttonGroup('confirmationActions')->components([
                    Component::button('cancel')->label('Cancel')->action('closeModal')->buttonType('button'),
                    Component::button('confirm')->label('Delete')->action('deleteSelected')->buttonType('button'),
                ]),
            ]),
    ])->apply([
        static fn (ComponentBuilder $builder): ComponentBuilder => $builder->prependChildren(
            Component::text('subtitle')->content('A declarative component layout.'),
        ),
    ]),
])->withProperty(
    new Property(Component::Name, 'admin'),
    new Property(Component::Id, 'admin-layout'),
)->assertValid();
