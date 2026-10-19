<?php

declare(strict_types=1);

use Gcego\Luminaries\Rendering\SvgRootTag;

it('replaces managed root attributes instead of duplicating them', function (): void {
    $svg = SvgRootTag::merge('<svg viewBox="0 0 24 24" aria-hidden="true" width="48" class="old"><path d="M0 0"/></svg>', ['class' => 'size-5', 'role' => 'img', 'aria-label' => 'Edit']);

    expect($svg)->toBe('<svg viewBox="0 0 24 24" class="size-5" role="img" aria-label="Edit"><path d="M0 0"/></svg>');
});

it('escapes attribute values', function (): void {
    $svg = SvgRootTag::merge('<svg viewBox="0 0 24 24"/>', ['aria-label' => '"><script>alert(1)</script>']);

    expect($svg)->toContain('aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<script>');
});

it('strips embedded titles so decorative icons have no tooltip or name', function (): void {
    expect(SvgRootTag::merge("<svg viewBox='0 0 24 24'><title>Logo</title><path d=\"M0 0\"/></svg>", []))->toBe('<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>');
});
