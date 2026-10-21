<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\Fit;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\InvalidMediaException;
use Gcego\Media\Fields\MediaUpload;
use Gcego\Media\MediaCollection;
use Gcego\Media\MediaConfig;

it('defines collections fluently and immutably, without touching storage or the database', function (): void {
    $queries = queriesDuring(static function () use (&$collection, &$base): void {
        $base = new MediaCollection('lesson-resources');
        $collection = $base->disk('local')->private()->maxFiles(20)->maxSize(2048)->acceptedFileTypes(['application/pdf', 'image/*'])->variants(Variant::make('thumb', 200, 200, Fit::Cover));
    });

    expect($queries)->toBe([])
        ->and($base->fileLimit())->toBeNull()
        ->and($collection->collectionName())->toBe('lesson-resources')
        ->and($collection->diskName())->toBe('local')
        ->and($collection->visibility())->toBe(MediaVisibility::Private)
        ->and($collection->fileLimit())->toBe(20)
        ->and($collection->sizeLimit())->toBe(2048)
        ->and($collection->types())->toBe(['application/pdf', 'image/*'])
        ->and($collection->variantDefinitions()[0]->variantName())->toBe('thumb')
        ->and($collection->validate()->errors())->toBe([]);
});

it('accepts the property syntax for upload fields', function (): void {
    $field = new MediaUpload(
        new Property(MediaUpload::Name, 'document'),
        new Property(MediaUpload::Label, 'Document'),
        new Property(MediaUpload::Required, true),
        new Property(MediaUpload::MaxSize, 10240),
    );
    $fluent = (new MediaUpload('attachments', 'Attachments'))->multiple()->maxFiles(10)->maxFileSize(5120)->acceptedFileTypes(['application/pdf'])->collection('lesson-resources')->onDisk('public')->public();

    expect($field->validate()->errors())->toBe([])
        ->and($field->collectionName())->toBe('document')
        ->and($field->isRequired())->toBeTrue()
        ->and($fluent->isMultiple())->toBeTrue()
        ->and($fluent->restrictions()->fileLimit())->toBe(10)
        ->and($fluent->restrictions()->sizeLimit())->toBe(5120)
        ->and($fluent->restrictions()->diskName())->toBe('public')
        ->and($fluent->restrictions()->visibility())->toBe(MediaVisibility::Public)
        ->and($fluent->collectionName())->toBe('lesson-resources')
        ->and((new MediaUpload('cover', 'Cover'))->image()->restrictions()->types())->toBe(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
});

it('tightens limits: configuration, then collection, then field', function (): void {
    $config = app(MediaConfig::class);
    $collection = (new MediaCollection('docs'))->maxSize(4096)->maxFiles(5)->acceptedFileTypes(['application/pdf', 'image/*']);
    $rules = CollectionRules::resolve($config, $collection, (new MediaCollection('docs'))->maxSize(99999)->maxFiles(2)->acceptedFileTypes(['image/png', 'video/mp4']));

    expect($rules->maxSize)->toBe(4096)
        ->and($rules->maxFiles)->toBe(2)
        ->and($rules->types)->toBe(['image/png'])
        ->and($rules->accepts('image/png'))->toBeTrue()
        ->and($rules->accepts('application/pdf'))->toBeFalse()
        ->and($rules->visibility)->toBe(MediaVisibility::Private)
        ->and($rules->disk)->toBe('local')
        ->and($rules->variant('thumb'))->not->toBeNull()
        ->and(CollectionRules::resolve($config, (new MediaCollection('one'))->single())->maxFiles)->toBe(1);
});

it('refuses disks outside the allow-list', function (): void {
    expect(fn () => CollectionRules::resolve(app(MediaConfig::class), (new MediaCollection('x'))->disk('s3-secret')))->toThrow(InvalidMediaException::class, 'gcego-media.disks');
});

it('rejects invalid definitions', function (Closure $definition, string $message): void {
    expect(implode("\n", array_map(static fn ($error): string => $error->format(), $definition()->validate()->errors())))->toContain($message);
})->with([
    'collection name' => [fn () => new MediaCollection('Lesson Resources'), 'lowercase name'],
    'type' => [fn () => (new MediaCollection('x'))->acceptedFileTypes(['pdf']), 'content types'],
    'visibility' => [fn () => new MediaCollection('x', new Property(MediaCollection::Visibility, 'world')), 'world'],
    'variant size' => [fn () => (new MediaCollection('x'))->variants(Variant::make('huge', 9000)), 'between 16 and 4096'],
    'duplicate variants' => [fn () => (new MediaCollection('x'))->variants(Variant::make('a', 100), Variant::make('a', 200)), 'its own name'],
    'field collection' => [fn () => new MediaUpload('x', 'X', new Property(MediaUpload::Collection, '../etc')), 'collection name'],
]);

it('registers collections from configuration and code', function (): void {
    $this->reboot(['gcego-media.collections.definitions' => ['avatars' => ['types' => ['image/*'], 'single' => true, 'variants' => ['small' => ['width' => 96, 'height' => 96, 'fit' => 'cover']]]]]);
    $registry = app(MediaCollectionRegistry::class);
    media()->collection((new MediaCollection('reports'))->acceptedFileTypes(['application/pdf']));

    expect($registry->has('avatars'))->toBeTrue()
        ->and($registry->get('avatars')->isSingle())->toBeTrue()
        ->and($registry->get('avatars')->variantDefinitions()[0]->width())->toBe(96)
        ->and($registry->get('reports')->types())->toBe(['application/pdf'])
        ->and($registry->get('unregistered')->collectionName())->toBe('unregistered')
        ->and(fn () => $registry->get('../x'))->toThrow(InvalidMediaException::class);
});

it('validates its configuration', function (array $config, string $message): void {
    expect(fn () => MediaConfig::fromArray($config))->toThrow(InvalidMediaException::class, $message);
})->with([
    [['disk' => 'other', 'disks' => ['local']], 'one of gcego-media.disks'],
    [['path_prefix' => '../media'], 'path_prefix'],
    [['uploads' => ['max_size' => 0]], 'between'],
    [['uploads' => ['allowed_types' => ['pdf']]], 'content type'],
    [['visibility' => 'world'], 'private'],
    [['security' => ['access_policy' => stdClass::class]], 'does not implement'],
    [['scanning' => ['on_failure' => 'ignore']], 'quarantine'],
]);
