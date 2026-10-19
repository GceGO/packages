<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Component;

$packageRoot = dirname(__DIR__, 2);

/**
 * @return list<string>
 */
function blueprintSourceFiles(string $packageRoot): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageRoot.'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('depends only on PHP and gcego/components', function () use ($packageRoot): void {
    $manifest = json_decode((string) file_get_contents($packageRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($manifest) ? $manifest['require'] : null)->toBe(['php' => '^8.3', 'gcego/components' => '^0.1']);
});

it('works without Laravel', function (): void {
    expect(class_exists('Illuminate\\Foundation\\Application'))->toBeFalse()
        ->and(class_exists('Gcego\\Core\\Gcego'))->toBeFalse()
        ->and((new Blueprint('subjects'))->assertValid())->toBeInstanceOf(Component::class);
});

it('autoloads every source file from its PSR-4 path', function () use ($packageRoot): void {
    foreach (blueprintSourceFiles($packageRoot) as $file) {
        $symbol = 'Gcego\\Blueprints\\'.str_replace('/', '\\', substr($file, strlen($packageRoot.'/src/'), -4));

        expect(class_exists($symbol) || interface_exists($symbol) || enum_exists($symbol))->toBeTrue($symbol);
    }
});

it('declares strict types and references no UI framework', function () use ($packageRoot): void {
    foreach (blueprintSourceFiles($packageRoot) as $file) {
        $source = (string) file_get_contents($file);

        expect($source)->toContain('declare(strict_types=1);')
            ->and($source)->not->toMatch('/\b(Illuminate|Livewire|Filament|Eloquent)\\\\/');
    }
});

arch('it does not use debugging functions')
    ->expect('Gcego\Blueprints')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);
