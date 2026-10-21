<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Facades\Components;

return Components::schema([
    Component::section('dashboard')
        ->label('Dashboard')
        ->components([
            Component::group('metrics')
                ->child([
                    Component::item('statistics'),
                    Component::item('activity'),
                ]),
        ]),
]);
