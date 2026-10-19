<?php

declare(strict_types=1);

use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsServiceProvider;

it('reads a large group with one query per scope level, and none once cached', function (): void {
    settings()->register(new SettingsGroup('large', ...array_map(static fn (int $i): Setting => new Setting(name: 'large_'.$i, default: $i, scopes: ['global', 'tenant', 'user']), range(1, 200))));
    $world = world();
    settings()->global()->asSystem()->setMany(array_combine(array_map(static fn (int $i): string => 'large_'.$i, range(1, 50)), range(1001, 1050)));

    actAs($world['teacher'], 1);
    $cold = queriesDuring(fn () => expect(settings()->group('large'))->toHaveCount(200));
    $values = settings()->group('large');

    actAs($world['teacher'], 1);
    $warm = queriesDuring(fn () => settings()->group('large'));

    expect(array_filter($cold, static fn (string $sql): bool => str_contains($sql, 'gcego_settings')))->toHaveCount(1)
        ->and($warm)->toBe([])
        ->and($values['large_1'])->toBe(1001)
        ->and($values['large_200'])->toBe(200);
});

it('writes a bulk update with a bounded number of queries per setting', function (): void {
    settings()->register(new SettingsGroup('bulk', ...array_map(static fn (int $i): Setting => new Setting('bulk_'.$i, 0), range(1, 50))));

    $queries = queriesDuring(fn () => settings()->global()->asSystem()->setMany(array_combine(array_map(static fn (int $i): string => 'bulk_'.$i, range(1, 50)), range(1, 50))));

    expect(count($queries))->toBeLessThanOrEqual(3 * 50 + 5);
});

it('keeps the settings form payload small', function (): void {
    $world = world();
    $page = html($this->actingAs($world['admin'])->get('/desk/settings/general')->assertOk());

    expect(strlen(snapshotOf($page, 'gcego-settings-form')))->toBeLessThan(4000);
});

it('runs no query while definitions are constructed or the package registers', function (): void {
    $queries = queriesDuring(function (): void {
        new SettingsGroup('fresh', new Setting('fresh_value', ''));
        (new SettingsServiceProvider(app()))->register();
    });

    expect($queries)->toBe([]);
});
