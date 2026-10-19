<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Tests\Fixtures\Enums\Level;
use Gcego\Settings\Tests\Fixtures\Enums\Theme;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingType;

it('converts values exactly', function (SettingType $type, mixed $input, mixed $expected, ?string $enum = null): void {
    expect((new SettingCaster)->cast($type, $input, false, $enum))->toBe($expected);
})->with([
    'string' => [SettingType::String, 'GCEGO', 'GCEGO'],
    'integer to string' => [SettingType::String, 42, '42'],
    'integer' => [SettingType::Integer, 7, 7],
    'digit string' => [SettingType::Integer, '-12', -12],
    'whole float' => [SettingType::Integer, 3.0, 3],
    'float' => [SettingType::Float, '2.5', 2.5],
    'integer to float' => [SettingType::Float, 2, 2.0],
    'true text' => [SettingType::Boolean, 'TRUE', true],
    'off' => [SettingType::Boolean, 'off', false],
    'one' => [SettingType::Boolean, 1, true],
    'array' => [SettingType::Array, ['a' => [1, 2]], ['a' => [1, 2]]],
    'json text to array' => [SettingType::Array, '{"a":1}', ['a' => 1]],
    'json scalar' => [SettingType::Json, '"hello"', 'hello'],
    'json null' => [SettingType::Json, 'null', null],
    'enum value' => [SettingType::Enum, 'dark', Theme::Dark, Theme::class],
    'int enum from text' => [SettingType::Enum, '5', Level::High, Level::class],
    'media' => [SettingType::Media, '01JABCDEFGHJKMNPQRSTVWXYZ0', '01jabcdefghjkmnpqrstvwxyz0'],
]);

it('converts dates to UTC CarbonImmutable values', function (): void {
    $caster = new SettingCaster;

    expect($caster->cast(SettingType::Date, '2026-02-28', false)->toAtomString())->toBe('2026-02-28T00:00:00+00:00')
        ->and($caster->cast(SettingType::DateTime, '2026-02-28T10:15:00+02:00', false)->toAtomString())->toBe('2026-02-28T08:15:00+00:00')
        ->and($caster->cast(SettingType::DateTime, CarbonImmutable::parse('2026-01-01 12:00', 'Africa/Douala'), false)->toAtomString())->toBe('2026-01-01T11:00:00+00:00');
});

it('refuses lossy or invalid conversions', function (SettingType $type, mixed $input, string $reason, ?string $enum = null): void {
    try {
        (new SettingCaster)->cast($type, $input, false, $enum);
        $this->fail('The value was accepted.');
    } catch (InvalidSettingException $exception) {
        expect($exception->reason)->toBe($reason);
    }
})->with([
    'boolean as string' => [SettingType::String, true, 'text'],
    'fractional integer' => [SettingType::Integer, 3.5, 'integer'],
    'fractional integer text' => [SettingType::Integer, '3.5', 'integer'],
    'words as integer' => [SettingType::Integer, 'ten', 'integer'],
    'infinite float' => [SettingType::Float, INF, 'number'],
    'maybe' => [SettingType::Boolean, 'maybe', 'boolean'],
    'two' => [SettingType::Boolean, 2, 'boolean'],
    'invalid json' => [SettingType::Array, '{nope', 'json'],
    'json scalar as array' => [SettingType::Array, '"x"', 'array'],
    'object in array' => [SettingType::Array, [new stdClass], 'plain'],
    'impossible date' => [SettingType::Date, '2026-02-30', 'date'],
    'date with time' => [SettingType::Date, '2026-02-01 10:00', 'date'],
    'not a time' => [SettingType::DateTime, 'tomorrow', 'datetime'],
    'unknown case' => [SettingType::Enum, 'purple', 'enum', Theme::class],
    'not a ulid' => [SettingType::Media, '../../etc/passwd', 'media'],
    'null when not nullable' => [SettingType::String, null, 'empty'],
]);

it('keeps null only for nullable settings', function (): void {
    expect((new SettingCaster)->cast(SettingType::Integer, null, true))->toBeNull();
});
