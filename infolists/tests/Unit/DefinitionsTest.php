<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Infolists\Configuration\Format;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Entries\Badge;
use Gcego\Infolists\Entries\DateTime;
use Gcego\Infolists\Entries\KeyValue;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Infolists\Layout\Grid;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Layout\Split;
use Gcego\Infolists\Layout\Tabs;
use Gcego\Infolists\Schema\SchemaCompiler;
use Gcego\Infolists\Tests\Fixtures\Blueprints;

it('accepts the documented definitions', function (Infolist $infolist): void {
    expect($infolist->validate()->isValid())->toBeTrue();
})->with([
    'minimal' => [new Infolist(new Text('name', 'Subject Name'), new Text('code', 'Subject Code'), new Badge('status', 'Status'), new DateTime('created_at', 'Created At'))],
    'property syntax' => [new Infolist(new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Copyable, true)))],
    'sections' => [new Infolist(new Section('Subject Information', new Text('name', 'Subject Name'), new Text('code', 'Subject Code'), new Badge('status', 'Status')), new Section('Record Information', new DateTime('created_at', 'Created At'), new DateTime('updated_at', 'Last Updated')))],
    'tailwind' => [new Infolist(new Section('Subject Information', new Text('name', 'Subject Name')), new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'shadow-sm']), new Slot(Tailwind::Body, ['p-6', 'space-y-6'])))],
    'grid' => [new Infolist(new Grid(2, new Text('name', 'Name'), new Text('category.name', 'Category')))],
    'repeatable' => [new Infolist(new RepeatableEntry('objectives', new Text('title', 'Objective'), new Text('description', 'Description')))],
    'full fixture' => [Blueprints::infolist()],
]);

it('rejects invalid definitions', function (Component $component): void {
    expect($component->validate()->isValid())->toBeFalse();
})->with([
    'duplicate entries' => [new Infolist(new Text('name'), new Section('S', new Text('name')))],
    'duplicate in repeatable' => [new Infolist(new RepeatableEntry('items', new Text('title'), new Text('title')))],
    'bad path' => [new Text('name; drop')],
    'method call path' => [new Text('delete()')],
    'empty infolist' => [new Infolist],
    'tabs with entries' => [new Tabs(new Text('a'))],
    'split with one child' => [new Split(new Text('a'))],
    'grid columns' => [new Grid(7, new Text('a'))],
    'empty repeatable' => [new RepeatableEntry('items')],
    'key value without keys' => [new KeyValue('settings')],
    'sensitive key' => [new KeyValue('settings', new Property(KeyValue::Keys, ['api_key' => 'API key']))],
    'unknown badge variant' => [new Badge('s', new Property(Badge::Variants, ['a' => 'purple-500']))],
    'unsafe icon name' => [new Badge('s', new Property(Badge::Icons, ['a' => '<svg/onload=1>']))],
    'non-infolist child' => [new Infolist(new Component)],
]);

it('reads entry properties from shorthand and Property syntax alike', function (): void {
    $short = new Text('category.name', 'Category');
    $long = new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Copyable, true));

    expect($short->entryName())->toBe('category.name')
        ->and($short->relations())->toBe(['category'])
        ->and((new Text('first_name'))->label())->toBe('First Name')
        ->and($long->label())->toBe('Subject Name')
        ->and($long->get(Text::Copyable))->toBeTrue()
        ->and((new KeyValue('settings', new Property(KeyValue::Keys, ['api_key' => 'Key']), new Property(KeyValue::AllowSensitive, true)))->validate()->isValid())->toBeTrue();
});

it('compiles schemas with access paths and relations, without queries', function (): void {
    $queries = queriesDuring(function () use (&$schema, &$infolist): void {
        $infolist = Blueprints::infolist();
        $infolist->validate();
        $schema = (new SchemaCompiler)->compile($infolist);
    });

    expect($queries)->toBe([])
        ->and($schema->paths())->toContain('topics.title', 'topics.teacher.name', 'objectives.title', 'tags.label', 'category.name')
        ->and($schema->relations)->toContain('category', 'topics', 'topics.teacher', 'tags', 'objectives')
        ->and((string) json_encode($infolist->normalize()))->not->toContain('Closure')
        ->and(fn () => (new SchemaCompiler)->compile(new Infolist(new Text('a'), new Text('a'))))->toThrow(InvalidEntryException::class, 'two entries show the same path');
});

it('keeps definitions immutable', function (): void {
    $entry = new Text('name');

    expect($entry->withProperty(new Property(Text::Copyable, true))->get(Text::Copyable))->toBeTrue()
        ->and($entry->get(Text::Copyable))->toBeFalse()
        ->and(fn () => new Format('stdClass'))->toThrow(InvalidEntryException::class)
        ->and(fn () => new VisibleWhen('bad path!'))->toThrow(InvalidEntryException::class);
});

it('validates configuration', function (): void {
    expect(InfolistsConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-infolists.php'))->toEqual(new InfolistsConfig)
        ->and(fn () => InfolistsConfig::fromArray(['layouts' => ['default_columns' => 9]]))->toThrow(InvalidEntryException::class)
        ->and(fn () => InfolistsConfig::fromArray(['formatting' => ['timezone' => 'Mars/Base']]))->toThrow(InvalidEntryException::class);
});
