<?php

declare(strict_types=1);

return [

    'container' => [
        // Default Container width: sm, md, lg, xl, 2xl … 7xl, prose or full.
        'default_max_width' => '7xl',
    ],

    'grid' => [
        // Default gap of grids, rows, stacks and layouts, on the spacing scale
        // (0, 1, 2, 3, 4, 6, 8, 10, 12; 6 = 1.5rem).
        'default_gap' => 6,
    ],

    'diagnostics' => [
        // LayoutInspector warns above these sizes (performance risks).
        'max_nodes' => 400,
        'max_depth' => 12,
        'max_livewire_components' => 12,
    ],

];
