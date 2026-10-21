<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Luminaries\Icons\Lucide;
use Gcego\Tables\Columns\Badge;
use Gcego\Tables\Columns\Boolean;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Columns\Date;
use Gcego\Tables\Columns\DateTime;
use Gcego\Tables\Columns\Icon;
use Gcego\Tables\Columns\Image;
use Gcego\Tables\Columns\Link;
use Gcego\Tables\Columns\Money;
use Gcego\Tables\Columns\Number;
use Gcego\Tables\Columns\Progress;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Format;
use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Gcego\Tables\Tests\Fixtures\GradeFormatter;
use Gcego\Tables\Tests\Fixtures\Models\Category;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Gcego\Tables\Tests\Fixtures\Models\Tag;
use Gcego\Tables\Tests\Fixtures\Status;
use Illuminate\Support\Carbon;

function cell(Column $column, mixed $value, mixed $record = null, ?TablesConfig $config = null): array
{
    return $column->cellData($value, $record, $config ?? new TablesConfig);
}

it('formats text with limits, tooltips, prefixes and enums', function (): void {
    expect(cell(new Text('name'), 'Algebra')['text'])->toBe('Algebra')
        ->and(cell(new Text('name'), null)['text'])->toBeNull()
        ->and(cell(new Text('code', new Property(Text::Prefix, '#')), 'MTH')['text'])->toBe('#MTH')
        ->and(cell(new Text('body', new Property(Text::Limit, 5)), 'Linear algebra'))->toMatchArray(['text' => 'Linea…', 'title' => 'Linear algebra'])
        ->and(cell(new Text('status'), Status::Published)['text'])->toBe('Published')
        ->and(cell(new Text('tags.label'), ['Core', 'Lab'])['text'])->toBe('Core, Lab')
        ->and((new Text('name'))->placeholder())->toBe('—');
});

it('formats numbers and money by locale and currency without floats for minor units', function (): void {
    app()->setLocale('en');

    expect(cell(new Number('n'), 1234567)['text'])->toBe('1,234,567')
        ->and(cell(new Number('n', new Property(Number::Decimals, 2)), '1234.5')['text'])->toBe('1,234.50')
        ->and(cell(new Number('n', new Property(Number::Grouping, false)), 1234)['text'])->toBe('1234')
        ->and(cell(new Number('n'), 'abc')['text'])->toBeNull()
        ->and(cell(new Number('n', new Property(Number::Locale, 'fr')), 1234.5)['text'])->toBe("1\u{202F}234,5")
        ->and(cell(new Money('fee'), '25000.00')['text'])->toBe("FCFA\u{00A0}25,000")
        ->and(cell(new Money('fee', new Property(Money::Currency, 'EUR')), '-1234.5')['text'])->toBe('-€1,234.50')
        ->and(cell(new Money('fee', new Property(Money::Currency, 'USD'), new Property(Money::MinorUnits, true)), 1999)['text'])->toBe('$19.99')
        ->and((new Money('fee', new Property(Money::Currency, 'USD'), new Property(Money::MinorUnits, true)))->amount('90071992547409931'))->toBe('900719925474099.31')
        ->and((new Money('fee'))->fractionDigits())->toBe(0)
        ->and(cell(new Money('fee'), 'ten')['text'])->toBeNull()
        ->and((new Money('fee'))->exportValue('25000.00', null, new TablesConfig))->toBe('25000.00');
});

it('never relies on color alone for booleans and keeps null distinct', function (): void {
    $true = cell(new Boolean('on'), true);
    $false = cell(new Boolean('on'), '0');
    $null = cell(new Boolean('on'), null);

    expect($true)->toMatchArray(['state' => true, 'text' => 'Yes', 'icon' => 'tables.true'])
        ->and($false)->toMatchArray(['state' => false, 'text' => 'No', 'icon' => 'tables.false'])
        ->and($null['state'])->toBeNull()
        ->and(cell(new Boolean('on', new Property(Boolean::TrueLabel, 'Active')), 1)['text'])->toBe('Active');
});

it('formats dates without moving days or mutating the record', function (): void {
    app()->setLocale('en');
    $stored = Carbon::parse('2026-03-01 23:30:00', 'UTC');
    $config = new TablesConfig(timezone: 'Africa/Douala');

    expect(cell(new Date('d'), '2026-03-01', null, $config)['text'])->toBe('Mar 1, 2026')
        ->and(cell(new Date('d', new Property(Date::Format, 'd/m/Y')), '2026-03-01')['text'])->toBe('01/03/2026')
        ->and(cell(new DateTime('at'), $stored, null, $config)['text'])->toBe('Mar 2, 2026 12:30 AM')
        ->and(cell(new DateTime('at'), $stored, null, $config)['datetime'])->toBe('2026-03-02T00:30:00+01:00')
        ->and($stored->toDateTimeString())->toBe('2026-03-01 23:30:00')
        ->and($stored->timezoneName)->toBe('UTC')
        ->and(cell(new Date('d'), 'not a date')['text'])->toBeNull()
        ->and(cell(new Date('d', new Property(Date::Relative, true)), Carbon::now()->subDays(3))['text'])->toBe('3 days ago');
});

it('maps badges and icons only to known variants and trusted icons', function (): void {
    $badge = new Badge('status', new Property(Badge::Variants, ['published' => 'success']), new Property(Badge::Labels, ['published' => 'Live']));
    $icon = new Icon('priority', new Property(Icon::Icons, ['high' => 'exclamation-triangle']), new Property(Icon::Labels, ['high' => 'High']));

    expect(cell($badge, 'published'))->toMatchArray(['text' => 'Live', 'variant' => 'success'])
        ->and(cell($badge, 'other'))->toMatchArray(['text' => 'other', 'variant' => 'neutral'])
        ->and(cell($badge, Status::Published)['variant'])->toBe('success')
        ->and(cell($icon, 'high'))->toMatchArray(['icon' => 'exclamation-triangle', 'text' => 'High'])
        ->and(cell(new Icon('raw'), '<script>')['icon'])->toBeNull()
        ->and(cell(new Icon('raw'), 'star')['icon'])->toBe('star')
        ->and((string) app(Icons::class)->svg('"><script>'))->toBe('')
        ->and(fn () => app(Icons::class)->register('evil', '<script>'))->toThrow(InvalidTableException::class);
});

it('accepts only safe image and link URLs', function (): void {
    expect(cell(new Image('logo'), 'https://cdn.gcego.test/a.png')['src'])->toBe('https://cdn.gcego.test/a.png')
        ->and(cell(new Image('logo'), 'javascript:alert(1)')['src'])->toBeNull()
        ->and(cell(new Image('logo', new Property(Image::Fallback, '/img/none.png')), 'data:image/png;base64,AAA')['src'])->toBe('/img/none.png')
        ->and(cell(new Image('logo', new Property(Image::Disk, 'public')), '../../.env')['src'])->toBeNull()
        ->and(cell(new Link('site'), 'https://gcego.test')['href'])->toBe('https://gcego.test')
        ->and(cell(new Link('site'), 'javascript:alert(1)')['href'])->toBeNull()
        ->and(cell(new Link('site'), 'mailto:a@gcego.test')['href'])->toBe('mailto:a@gcego.test')
        ->and(cell(new Link('site', new Property(Link::NewTab, true)), 'https://gcego.test'))->toMatchArray(['target' => '_blank', 'rel' => 'noopener noreferrer'])
        ->and(SafeUrl::web('//evil.test/x'))->toBeNull()
        ->and(SafeUrl::web('https://gcego.test/a"onerror="x'))->toBeNull();
});

it('clamps progress and exposes accessible values', function (): void {
    expect(cell(new Progress('p'), 45))->toMatchArray(['percent' => 45.0, 'text' => '45%'])
        ->and(cell(new Progress('p'), 250)['percent'])->toBe(100.0)
        ->and(cell(new Progress('p'), -5)['percent'])->toBe(0.0)
        ->and(cell(new Progress('p', new Property(Progress::Max, 180)), 90)['text'])->toBe('50%')
        ->and(cell(new Progress('p'), 'x')['percent'])->toBeNull();
});

it('applies custom formatters as plain text', function (): void {
    $closure = new Text('score', new Format(fn (?int $score) => $score === null ? null : $score.' pts'));
    $class = new Text('score', new Format(GradeFormatter::class));

    expect(cell($closure, 7)['text'])->toBe('7 pts')
        ->and(cell($class, 70)['text'])->toBe('Pass')
        ->and(cell($class, 10)['text'])->toBe('Fail')
        ->and(cell(new Text('x', new Format(fn () => '<b>bold</b>')), 1)['text'])->toBe('<b>bold</b>');
});

it('resolves values without lazy loading or hidden attributes', function (): void {
    $category = new Category(['name' => 'Science']);
    $subject = (new Subject(['name' => 'Algebra', 'api_token' => 'secret']))->setRelation('category', $category);
    $unloaded = new Subject(['name' => 'Biology']);

    expect(ValueResolver::get($subject, 'name'))->toBe('Algebra')
        ->and(ValueResolver::get($subject, 'category.name'))->toBe('Science')
        ->and(ValueResolver::get($subject, 'api_token'))->toBeNull()
        ->and(queriesDuring(fn () => ValueResolver::get($unloaded, 'category.name')))->toBe([])
        ->and(ValueResolver::get($unloaded, 'category'))->toBeNull()
        ->and(ValueResolver::get(['a' => ['b' => 1]], 'a.b'))->toBe(1)
        ->and(ValueResolver::get((object) ['title' => 'Row'], 'title'))->toBe('Row')
        ->and(ValueResolver::get($subject->setRelation('tags', collect([new Tag(['label' => 'Core']), new Tag(['label' => 'Lab'])])), 'tags.label'))->toBe(['Core', 'Lab']);
});

it('takes typed Luminaries icons in icon maps and as icon values', function (): void {
    $badge = new Badge('status', new Property(Badge::Icons, ['published' => Heroicon::CheckCircle]));
    $icon = new Icon('priority', new Property(Icon::Icons, ['high' => Lucide::Flame]));

    expect($badge->validate()->isValid())->toBeTrue()
        ->and($icon->validate()->isValid())->toBeTrue()
        ->and(cell($badge, 'published')['icon'])->toBe('heroicon:check-circle')
        ->and(cell($icon, 'high')['icon'])->toBe('lucide:flame')
        ->and(cell(new Icon('raw'), Heroicon::Star)['icon'])->toBe('heroicon:star')
        ->and((new Boolean('done', new Property(Boolean::TrueIcon, Heroicon::Check)))->get(Boolean::TrueIcon))->toBe('heroicon:check');
});
