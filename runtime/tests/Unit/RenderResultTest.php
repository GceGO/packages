<?php

declare(strict_types=1);

use Gcego\Runtime\Rendering\RenderResult;
use Illuminate\Support\Facades\Blade;

it('escapes text and keeps trusted HTML', function (): void {
    expect(RenderResult::text('<b>Tom & Jerry</b>')->toHtml())->toBe('&lt;b&gt;Tom &amp; Jerry&lt;/b&gt;')
        ->and(RenderResult::trustedHtml('<b>ok</b>')->toHtml())->toBe('<b>ok</b>')
        ->and((string) RenderResult::empty())->toBe('');
});

it('is printed by Blade without double escaping', function (): void {
    expect(Blade::render('{{ $result }}', ['result' => RenderResult::text('<i>x</i>')]))->toBe('&lt;i&gt;x&lt;/i&gt;');
});

it('renders views lazily', function (): void {
    $rendered = false;
    $result = RenderResult::deferred(function () use (&$rendered): string {
        $rendered = true;

        return 'done';
    });

    expect($rendered)->toBeFalse()
        ->and($result->toHtml())->toBe('done')
        ->and($rendered)->toBeTrue();
});
