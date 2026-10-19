<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Infolists\Configuration\Format;
use Gcego\Infolists\Configuration\Resolve;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Entries\Badge;
use Gcego\Infolists\Entries\Boolean;
use Gcego\Infolists\Entries\Date;
use Gcego\Infolists\Entries\DateTime;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Entries\Icon;
use Gcego\Infolists\Entries\Image;
use Gcego\Infolists\Entries\KeyValue;
use Gcego\Infolists\Entries\Link;
use Gcego\Infolists\Entries\ListEntry;
use Gcego\Infolists\Entries\Money;
use Gcego\Infolists\Entries\Number;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Infolists\Support\SafeHtml;
use Gcego\Infolists\Tests\Fixtures\Status;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Luminaries\Icons\Lucide;
use Illuminate\Support\Carbon;

function show(Entry $entry, mixed $value, mixed $record = null, ?InfolistsConfig $config = null): array
{
    return $entry->display($value, $record, $config ?? new InfolistsConfig);
}

it('formats text, multiline, truncation and copy values', function (): void {
    expect(show(new Text('name'), 'Algebra')['text'])->toBe('Algebra')
        ->and(show(new Text('name'), null)['text'])->toBeNull()
        ->and(show(new Text('name'), '')['text'])->toBeNull()
        ->and(show(new Text('status'), Status::Published)['text'])->toBe('Published')
        ->and(show(new Text('notes', new Property(Text::Limit, 4)), 'Long text'))->toMatchArray(['text' => 'Long…', 'full' => 'Long text', 'title' => 'Long text'])
        ->and(show(new Text('notes', new Property(Text::Multiline, true)), "a\nb")['multiline'])->toBeTrue()
        ->and(show(new Text('code', new Property(Text::Copyable, true)), 'MTH')['copy'])->toBeTrue()
        ->and(show(new Text('code', new Property(Text::Copyable, true)), null)['copy'])->toBeFalse()
        ->and((new Text('x'))->placeholder(new InfolistsConfig))->toBe('—');
});

it('renders Markdown and HTML only through the sanitizer', function (): void {
    $markdown = show(new Text('d', new Property(Text::Markdown, true)), '**bold** <script>alert(1)</script> [x](javascript:alert(1)) <img src=x onerror=alert(1)>')['html']->toHtml();
    $html = show(new Text('d', new Property(Text::Html, true)), '<p onclick="x()">Hi <a href="javascript:alert(1)">bad</a> <a href="https://gcego.test">ok</a></p><iframe src="//evil"></iframe><style>*{}</style>')['html']->toHtml();

    expect($markdown)->toContain('<strong>bold</strong>')
        ->and($markdown)->not->toContain('<script')
        ->and($markdown)->not->toContain('javascript:')
        ->and($markdown)->not->toContain('onerror')
        ->and($html)->toContain('href="https://gcego.test"')
        ->and($html)->toContain('rel="noopener noreferrer"')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<iframe')
        ->and($html)->not->toContain('<style')
        ->and(SafeHtml::html('<b>x</b>')->toHtml())->toBe('<b>x</b>');
});

it('formats numbers and money with intl, including XAF and minor units', function (): void {
    app()->setLocale('en');

    expect(show(new Number('n'), -1234567)['text'])->toBe('-1,234,567')
        ->and(show(new Number('n', new Property(Number::Decimals, 2)), '3.5')['text'])->toBe('3.50')
        ->and(show(new Number('n'), 'abc')['text'])->toBeNull()
        ->and(show(new Money('fee'), '25000.00')['text'])->toBe("FCFA\u{00A0}25,000")
        ->and(show(new Money('fee', new Property(Money::Currency, 'EUR')), '-12.5')['text'])->toBe('-€12.50')
        ->and(show(new Money('fee', new Property(Money::Currency, 'USD'), new Property(Money::MinorUnits, true)), 1999)['text'])->toBe('$19.99')
        ->and(show(new Money('fee'), null)['text'])->toBeNull();
});

it('shows booleans with icon and text, null distinct from false', function (): void {
    expect(show(new Boolean('b'), true))->toMatchArray(['state' => true, 'text' => 'Yes', 'icon' => 'infolists.true'])
        ->and(show(new Boolean('b', new Property(Boolean::FalseLabel, 'Disabled')), 0))->toMatchArray(['state' => false, 'text' => 'Disabled', 'icon' => 'infolists.false'])
        ->and(show(new Boolean('b'), null)['state'])->toBeNull();
});

it('maps badges and icons through trusted variants and icons only', function (): void {
    $badge = new Badge('status', new Property(Badge::Variants, ['published' => 'success']), new Property(Badge::Labels, ['published' => 'Live']));

    expect(show($badge, 'published')['items'])->toBe([['text' => 'Live', 'variant' => 'success', 'icon' => null]])
        ->and(show($badge, 'evil-class bg-red-500')['items'][0]['variant'])->toBe('neutral')
        ->and(show($badge, ['published', 'draft'])['items'])->toHaveCount(2)
        ->and(show(new Icon('p', new Property(Icon::Icons, ['high' => 'exclamation-triangle'])), 'high')['icon'])->toBe('exclamation-triangle')
        ->and(show(new Icon('p'), '<svg onload=alert(1)>')['icon'])->toBeNull()
        ->and(show(new Icon('p'), 'star')['icon'])->toBe('star');
});

it('formats dates without shifting or mutating them', function (): void {
    app()->setLocale('en');
    $stored = Carbon::parse('2026-03-01 23:30:00', 'UTC');

    expect(show(new Date('d'), '2026-03-01'))->toMatchArray(['text' => 'Mar 1, 2026', 'datetime' => '2026-03-01'])
        ->and(show(new DateTime('at'), $stored, null, new InfolistsConfig(timezone: 'Africa/Douala'))['text'])->toBe('Mar 2, 2026 12:30 AM')
        ->and(show(new DateTime('at'), $stored, null, new InfolistsConfig(dateTimeFormat: 'Y-m-d H:i'))['text'])->toBe('2026-03-01 23:30')
        ->and($stored->toDateTimeString())->toBe('2026-03-01 23:30:00')
        ->and(show(new Date('d'), 'garbage')['text'])->toBeNull();
});

it('accepts only safe link and image URLs', function (): void {
    expect(show(new Link('w'), 'https://gcego.test')['href'])->toBe('https://gcego.test')
        ->and(show(new Link('w'), 'javascript:alert(1)')['href'])->toBeNull()
        ->and(show(new Link('w'), 'data:text/html,x')['href'])->toBeNull()
        ->and(show(new Link('e', new Property(Link::Mailto, true)), 'a@gcego.test')['href'])->toBe('mailto:a@gcego.test')
        ->and(show(new Link('e', new Property(Link::Mailto, true)), 'not an email')['href'])->toBeNull()
        ->and(show(new Link('w', new Property(Link::NewTab, true)), 'https://gcego.test'))->toMatchArray(['target' => '_blank', 'rel' => 'noopener noreferrer'])
        ->and(show(new Image('i'), 'https://cdn.gcego.test/a.png')['src'])->toBe('https://cdn.gcego.test/a.png')
        ->and(show(new Image('i'), 'javascript:alert(1)')['src'])->toBeNull()
        ->and(show(new Image('i', new Property(Image::Disk, 'local')), '../../.env')['src'])->toBeNull()
        ->and(show(new Image('i', new Property(Image::Disk, 'local'), new Property(Image::Private, true)), 'scans/a.png')['src'])->toBeNull()
        ->and(show(new Image('i'), 'https://cdn.gcego.test/a.png')['alt'])->toBe('I');
});

it('shows only allow-listed keys of structured data', function (): void {
    $entry = new KeyValue('settings', new Property(KeyValue::Keys, ['term' => 'Term', 'grading.scale' => 'Scale', 'missing' => 'Missing']));
    $pairs = show($entry, ['term' => 'First', 'grading' => ['scale' => 20], 'api_key' => 'sk_live'])['pairs'];

    expect(array_column($pairs, 'text', 'key'))->toBe(['term' => 'First', 'grading.scale' => '20', 'missing' => null])
        ->and((string) json_encode($pairs))->not->toContain('sk_live')
        ->and(show($entry, '{"term":"Second"}')['pairs'][0]['text'])->toBe('Second')
        ->and(show($entry, 'not json')['text'])->toBeNull();
});

it('lists values with limits and keeps repeatable items keyed', function (): void {
    $config = new InfolistsConfig(maxListItems: 2);
    $list = show(new ListEntry('languages'), ['English', null, 'French', 'Spanish'], null, $config);

    expect($list)->toMatchArray(['items' => ['English', 'French'], 'more' => 1])
        ->and(show(new ListEntry('x'), [])['items'])->toBe([])
        ->and(array_column((new RepeatableEntry('o', new Text('title')))->items([['title' => 'A'], ['title' => 'B']], new InfolistsConfig), 'key'))->toBe(['0', '1']);
});

it('uses custom formatters, resolvers and record conditions', function (): void {
    $record = ['status' => 'archived', 'score' => 70, 'notes' => ''];

    expect(show(new Text('score', new Format(fn (?int $score) => $score.' pts')), 70)['text'])->toBe('70 pts')
        ->and((new Text('avg', new Resolve(fn (array $row) => $row['score'] / 7)))->value($record))->toBe(10)
        ->and((new VisibleWhen('status', 'archived'))->passes($record, fn () => true))->toBeTrue()
        ->and(VisibleWhen::not('status', 'archived')->passes($record, fn () => true))->toBeFalse()
        ->and(VisibleWhen::filled('notes')->passes($record, fn () => true))->toBeFalse()
        ->and(VisibleWhen::blank('notes')->passes($record, fn () => true))->toBeTrue()
        ->and(VisibleWhen::using(fn (array $row) => $row['score'] > 50)->passes($record, fn () => true))->toBeTrue()
        ->and((new VisibleWhen('status', 'archived'))->passes($record, fn () => false))->toBeFalse();
});

it('takes typed Luminaries icons in icon maps and as icon values', function (): void {
    $badge = new Badge('status', new Property(Badge::Icons, ['published' => Heroicon::CheckCircle]));

    expect($badge->validate()->isValid())->toBeTrue()
        ->and(show($badge, 'published')['items'][0]['icon'])->toBe('heroicon:check-circle')
        ->and(show(new Icon('p', new Property(Icon::Icons, ['high' => Lucide::Flame])), 'high')['icon'])->toBe('lucide:flame')
        ->and(show(new Icon('p'), Heroicon::Star)['icon'])->toBe('heroicon:star');
});
