<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Infolists\Diagnostics\InfolistInspector;
use Gcego\Infolists\Entries\Image;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\InfolistsServiceProvider;
use Gcego\Infolists\Tests\Fixtures\Blueprints;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('inspects a healthy infolist without record values', function (): void {
    $report = app(InfolistInspector::class)->inspect('subjects');

    expect($report->isHealthy())->toBeTrue()
        ->and($report->relations)->toEqualCanonicalizing(['category', 'topics', 'topics.teacher', 'tags'])
        ->and(array_column($report->entries, 'path'))->toContain('topics.teacher.name')
        ->and($report->checks())->toContain('html')
        ->and((string) json_encode($report->toArray()))->not->toContain('TOP SECRET');
});

it('reports broken infolists', function (): void {
    app(Registry::class)->register(new Blueprint('broken', new Source(Subject::class), new Infolist(
        new Text('api_token', 'Token'),
        new Text('category.missing.name', 'Bad'),
        new Image('logo', new Property(Image::Disk, 'local')),
    )));
    $report = app(InfolistInspector::class)->inspect('broken');

    expect($report->isHealthy())->toBeFalse()
        ->and($report->checks())->toContain('hidden', 'authorization', 'media', 'paths')
        ->and(app(InfolistInspector::class)->inspect(new Infolist(new Text('a'), new Text('a')))->errors()[0]->check)->toBe('schema')
        ->and(app(InfolistInspector::class)->inspect('nope')->isHealthy())->toBeFalse();
});

it('registers services and builds definitions without queries', function (): void {
    expect(queriesDuring(function (): void {
        (new InfolistsServiceProvider(app()))->register();
        Blueprints::subjects();
    }))->toBe([]);
});
