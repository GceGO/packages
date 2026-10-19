<?php

declare(strict_types=1);

use Gcego\Swatches\Branding\LogoColorExtractor;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidLogoException;

/**
 * Write a PNG: white background, a large brand block, a smaller accent block and a black outline.
 */
function logoPng(int $width = 200, int $height = 100, bool $transparent = false): string
{
    $path = sys_get_temp_dir().'/gcego-logo-'.getmypid().'-'.$width.'x'.$height.($transparent ? '-t' : '').'.png';
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, $transparent ? (int) imagecolorallocatealpha($image, 255, 255, 255, 127) : (int) imagecolorallocate($image, 255, 255, 255));
    imagefilledrectangle($image, 10, 10, (int) ($width * 0.6), $height - 10, (int) imagecolorallocate($image, 0x1D, 0x4E, 0xD8));
    imagefilledrectangle($image, (int) ($width * 0.65), 10, $width - 10, (int) ($height * 0.45), (int) imagecolorallocate($image, 0xF5, 0x9E, 0x0B));
    imagerectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 0));
    imagepng($image, $path);

    return $path;
}

it('finds the brand colors of a raster logo and suggests a brand', function (): void {
    $palette = (new LogoColorExtractor)->extract(logoPng());
    $brand = $palette->suggestBrand();

    $white = array_values(array_filter($palette->colors, static fn ($color): bool => $color->color->toHex() === '#ffffff'))[0] ?? null;

    expect($palette->source)->toBe('image/png')
        ->and($white?->isNeutral())->toBeTrue()                       // the background is found but never the brand color
        ->and($white?->share)->toBeGreaterThan(0.3)
        ->and($brand->primary?->deltaE(Color::parse('#1d4ed8')))->toBeLessThan(0.02)
        ->and($brand->accent?->deltaE(Color::parse('#f59e0b')))->toBeLessThan(0.02)
        ->and(array_sum(array_map(static fn ($color): float => $color->share, $palette->colors)))->toBeLessThanOrEqual(1.0001);
});

it('ignores transparent pixels', function (): void {
    $palette = (new LogoColorExtractor)->extract(logoPng(transparent: true));

    expect(array_map(static fn ($color): string => $color->color->toHex(), $palette->colors))->not->toContain('#ffffff')
        ->and($palette->suggestBrand()->primary?->toHex())->toBe('#1d4ed8');
});

it('reads SVG paints from attributes, style attributes and style blocks', function (): void {
    $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
          <style>.mark { fill: #047857 } .ring{stroke:#047857}</style>
          <rect width="10" height="10" fill="#ffffff"/>
          <circle class="mark" r="3" style="fill: #047857; stroke: none"/>
          <path d="M0 0" fill="#047857"/>
          <path d="M0 0" stroke="rgb(190 18 60)"/>
          <linearGradient><stop stop-color="#be123c"/></linearGradient>
          <text fill="url(#gradient)">x</text>
        </svg>
        SVG;
    $colors = (new LogoColorExtractor)->svg($svg);

    expect($colors[0]->color->toHex())->toBe('#047857')
        ->and(array_map(static fn ($color): string => $color->color->toHex(), $colors))->toContain('#be123c', '#ffffff');
});

it('suggests no colors for a monochrome logo, so the theme stays', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path fill="#111111" d="M0 0"/><path fill="#ffffff" d="M0 0"/></svg>';
    $path = sys_get_temp_dir().'/gcego-logo-mono-'.getmypid().'.svg';
    file_put_contents($path, $svg);

    expect((new LogoColorExtractor)->extract($path)->suggestBrand()->isEmpty())->toBeTrue();
});

it('refuses entity tricks, oversized files, huge images and non-images', function (): void {
    $dir = sys_get_temp_dir();
    $xxe = $dir.'/gcego-logo-xxe-'.getmypid().'.svg';
    file_put_contents($xxe, '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text fill="#123456">&x;</text></svg>');
    $text = $dir.'/gcego-logo-text-'.getmypid().'.png';
    file_put_contents($text, 'not an image');
    $big = $dir.'/gcego-logo-big-'.getmypid().'.svg';
    file_put_contents($big, '<svg>'.str_repeat(' ', LogoColorExtractor::MaxBytes).'</svg>');

    // A decompression bomb: a tiny file whose PNG header claims 6000×6000 pixels.
    // The pixel limit is checked from the header, before anything is decoded.
    $bomb = $dir.'/gcego-logo-bomb-'.getmypid().'.png';
    $header = pack('NNCCCCC', 6000, 6000, 8, 6, 0, 0, 0);
    file_put_contents($bomb, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$header.pack('N', crc32('IHDR'.$header)));

    expect(fn () => (new LogoColorExtractor)->extract($xxe))->toThrow(InvalidLogoException::class, 'could not be decoded')
        ->and(fn () => (new LogoColorExtractor)->extract($text))->toThrow(InvalidLogoException::class)
        ->and(fn () => (new LogoColorExtractor)->extract($big))->toThrow(InvalidLogoException::class, 'limit')
        ->and(fn () => (new LogoColorExtractor)->extract($bomb))->toThrow(InvalidLogoException::class, 'pixels')
        ->and(fn () => (new LogoColorExtractor)->extract('https://example.com/logo.png'))->toThrow(InvalidLogoException::class, 'readable local file')
        ->and(fn () => (new LogoColorExtractor)->extract($dir))->toThrow(InvalidLogoException::class);
});
