<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Modules
|--------------------------------------------------------------------------
|
| Modules group resources; each resource becomes gcego/pages pages (index,
| create, view, edit) for the operations its definition declares. Nothing is
| exposed or authorized on its own: a resource without a Table has no list,
| without a Form no create or edit page, and every page is authorized.
|
*/

return [

    'discovery' => [
        // Discover modules through gcego/registry ("module" definitions).
        'enabled' => true,
        // Folders searched recursively. Each module is a Module.php file in its
        // own folder (Academics/Module.php); sibling files are supporting code.
        'paths' => [
            app_path('GCEGo/Modules'),
        ],
        // Which files under those folders are module entrypoints (a regular
        // expression on the path relative to the folder).
        'entrypoint' => '#(^|/)Module\.php$#',
    ],

    'resources' => [
        // Generate pages for resources. When false, modules are metadata only.
        'auto_pages' => true,
        // Operations every resource gets without declaring them. Empty on
        // purpose: a resource exposes only what its definition declares.
        // Allowed: index, create, view, edit.
        'default_operations' => [],
    ],

    'navigation' => [
        // Add every module (as a group of its resources) to a gcego/navigation
        // navigation. When false, modules opt in with Module::Navigation.
        'auto' => false,
        // The navigation the contributor adds to.
        'target' => 'admin',
    ],

    'relationships' => [
        // Generate pages for Relation declarations.
        'enabled' => true,
    ],

    'security' => [
        // Generated pages of Eloquent resources require the model's policy:
        // viewAny for the list, create for the create page (record pages
        // authorize view/update on the record through gcego/pages).
        'authorize_by_default' => true,
    ],

    // Trusted ModuleExtension classes, applied in this order.
    'extensions' => [],

    // ModuleLifecycleHook classes told about each compilation stage.
    'lifecycle' => [
        'hooks' => [],
    ],

];
