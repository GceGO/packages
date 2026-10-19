<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use DOMDocument;
use DOMElement;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidLogoException;
use GdImage;

/**
 * Extracts the prominent colors of a logo file, to suggest a tenant Brand.
 *
 * Raster logos (PNG, JPEG, GIF, WebP) are decoded with GD, scaled down to at most 96 px,
 * and their visible pixels (alpha ≥ 50%) are counted in 4-bit-per-channel buckets; buckets
 * closer than ΔE OK 0.06 are merged and weighted by pixel count. SVG logos are parsed
 * without network access or entity expansion, and every paint (fill, stroke, stop-color,
 * flood-color, lighting-color, color, in attributes, style attributes and <style> blocks)
 * is counted once; only values the color parser accepts are kept.
 *
 * Safety: local files only, a byte limit, a pixel limit checked before decoding (no
 * decompression bombs), and type detection from the file's content, not its name.
 */
final readonly class LogoColorExtractor
{
    public const int MaxBytes = 5 * 1024 * 1024;

    public const int MaxPixels = 25_000_000;

    private const int SampleSize = 96;

    private const float MergeDistance = 0.06;

    private const array Paints = ['fill', 'stroke', 'stop-color', 'flood-color', 'lighting-color', 'color'];

    /**
     * @throws InvalidLogoException
     */
    public function extract(string $path, int $limit = 8): LogoPalette
    {
        $real = realpath($path);

        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw InvalidLogoException::unreadable();
        }

        $bytes = (int) filesize($real);

        if ($bytes > self::MaxBytes) {
            throw InvalidLogoException::tooLarge($bytes, self::MaxBytes);
        }

        $head = (string) file_get_contents($real, false, null, 0, 512);

        if (preg_match('/<svg[\s>]/i', $head) === 1 || (str_contains($head, '<?xml') && str_contains((string) file_get_contents($real), '<svg'))) {
            return new LogoPalette(array_slice($this->svg((string) file_get_contents($real)), 0, $limit), 'svg');
        }

        $info = @getimagesize($real);

        if ($info === false) {
            throw InvalidLogoException::unsupported('unknown');
        }

        [$width, $height] = $info;
        $type = $info['mime'];

        if (! in_array($type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            throw InvalidLogoException::unsupported($type);
        }

        if ($width * $height > self::MaxPixels) {
            throw InvalidLogoException::tooManyPixels($width, $height, self::MaxPixels);
        }

        return new LogoPalette(array_slice($this->raster($real, $type, $width, $height), 0, $limit), $type);
    }

    /**
     * @return list<LogoColor>
     */
    public function svg(string $markup): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($markup, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $document->doctype !== null) {
            throw InvalidLogoException::malformed();
        }

        $paints = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (self::Paints as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $paints[] = $element->getAttribute($attribute);
                }
            }

            if ($element->hasAttribute('style')) {
                $paints = [...$paints, ...self::declarations($element->getAttribute('style'))];
            }

            if ($element->localName === 'style') {
                $paints = [...$paints, ...self::declarations((string) $element->textContent)];
            }
        }

        $counts = [];

        foreach ($paints as $paint) {
            $color = Color::tryParse(trim($paint));

            if ($color === null || $color->alpha < 0.5) {
                continue;
            }

            $hex = $color->withAlpha(1.0)->toHex();
            $counts[$hex] = ($counts[$hex] ?? 0) + 1;
        }

        return $this->rank($counts);
    }

    /**
     * @return list<LogoColor>
     */
    private function raster(string $path, string $type, int $width, int $height): array
    {
        $image = match ($type) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif' => @imagecreatefromgif($path),
            default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };

        if (! $image instanceof GdImage) {
            throw InvalidLogoException::malformed();
        }

        $scale = min(1.0, self::SampleSize / max($width, $height, 1));
        $sampleWidth = max(1, (int) round($width * $scale));
        $sampleHeight = max(1, (int) round($height * $scale));
        $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
        imagealphablending($sample, false);
        imagesavealpha($sample, true);
        imagefill($sample, 0, 0, (int) imagecolorallocatealpha($sample, 0, 0, 0, 127));
        imagecopyresampled($sample, $image, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);

        $buckets = [];

        for ($y = 0; $y < $sampleHeight; $y++) {
            for ($x = 0; $x < $sampleWidth; $x++) {
                $pixel = imagecolorat($sample, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;

                if ($alpha > 63) {
                    continue;
                }

                $r = ($pixel >> 16) & 0xFF;
                $g = ($pixel >> 8) & 0xFF;
                $b = $pixel & 0xFF;
                $key = ($r >> 4) << 8 | ($g >> 4) << 4 | ($b >> 4);
                $buckets[$key] ??= [0, 0, 0, 0];
                $buckets[$key][0] += $r;
                $buckets[$key][1] += $g;
                $buckets[$key][2] += $b;
                $buckets[$key][3]++;
            }
        }

        $counts = [];

        foreach ($buckets as [$r, $g, $b, $count]) {
            $hex = sprintf('#%02x%02x%02x', (int) round($r / $count), (int) round($g / $count), (int) round($b / $count));
            $counts[$hex] = ($counts[$hex] ?? 0) + $count;
        }

        return $this->rank($counts);
    }

    /**
     * Merge near-identical colors (weighted), then sort by share.
     *
     * @param  array<string, int>  $counts  hex => weight
     * @return list<LogoColor>
     */
    private function rank(array $counts): array
    {
        arsort($counts);
        $total = array_sum($counts);

        if ($total === 0) {
            return [];
        }

        /** @var list<array{Color, int}> $clusters */
        $clusters = [];

        foreach ($counts as $hex => $weight) {
            $color = Color::parse((string) $hex);

            foreach ($clusters as $index => [$center, $clusterWeight]) {
                if ($center->deltaE($color) < self::MergeDistance) {
                    $clusters[$index][1] = $clusterWeight + $weight;

                    continue 2;
                }
            }

            $clusters[] = [$color, $weight];
        }

        usort($clusters, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_map(static fn (array $cluster): LogoColor => new LogoColor($cluster[0], $cluster[1] / $total), $clusters);
    }

    /**
     * Paint values of CSS declarations ("fill: #123; stroke: red").
     *
     * @return list<string>
     */
    private static function declarations(string $css): array
    {
        preg_match_all('/(?<![a-z-])(?:'.implode('|', array_map('preg_quote', self::Paints)).')\s*:\s*([^;}"\']+)/i', $css, $matches);

        return array_map('trim', $matches[1]);
    }
}
