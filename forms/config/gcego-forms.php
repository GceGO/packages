<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Forms
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-forms-config".
|
*/

return [

    'state' => [
        // The Livewire property holding form state (wire:model="data.name").
        'path' => 'data',

        // Trim submitted strings; empty strings become null.
        'trim_strings' => true,
    ],

    'uploads' => [
        // Default maximum file size; a FileUpload's MaxSize may only lower it.
        'max_size_kb' => 10240,

        // Disks FileUpload fields may store to. Files are stored with random names and private visibility.
        'disks' => ['local'],

        'directory' => 'gcego-uploads',
    ],

    'options' => [
        // Maximum relationship options loaded for a select (at most 100, the gcego/data page limit).
        'limit' => 100,
    ],

    'dates' => [
        // Time zone in which users enter and see DateTimePicker values. Null: app.timezone.
        // Values are stored in app.timezone. DatePicker and TimePicker values are never converted.
        'timezone' => null,
    ],

    'reactive' => [
        // Debounce of text fields other fields depend on.
        'debounce' => '400ms',
    ],

];
