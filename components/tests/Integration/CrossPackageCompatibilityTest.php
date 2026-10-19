<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Type;

$packageRoot = dirname(__DIR__, 2);

/**
 * @return list<string>
 */
function sourceFiles(string $packageRoot): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageRoot.'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('requires only PHP at runtime', function () use ($packageRoot): void {
    $manifest = json_decode((string) file_get_contents($packageRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($manifest) ? $manifest['require'] : null)->toBe(['php' => '^8.3'])
        ->and(is_array($manifest) ? $manifest['autoload'] : null)->toBe(['psr-4' => ['Gcego\\Components\\' => 'src/']]);
});

it('autoloads every source file from its PSR-4 path', function () use ($packageRoot): void {
    foreach (sourceFiles($packageRoot) as $file) {
        $relative = substr($file, strlen($packageRoot.'/src/'), -4);
        $symbol = 'Gcego\\Components\\'.str_replace('/', '\\', $relative);

        expect(class_exists($symbol) || interface_exists($symbol) || enum_exists($symbol))->toBeTrue($symbol.' is not autoloadable');
    }
});

it('declares strict types in every source file', function () use ($packageRoot): void {
    foreach (sourceFiles($packageRoot) as $file) {
        expect((string) file_get_contents($file))->toContain('declare(strict_types=1);');
    }
});

it('does not reference frameworks or frontend runtimes', function () use ($packageRoot): void {
    foreach (sourceFiles($packageRoot) as $file) {
        expect((string) file_get_contents($file))->not->toMatch('/\b(Illuminate|Laravel|Livewire|Filament|Tailwind|Eloquent|Blade)\\\\/');
    }
});

arch('it does not use debugging functions')
    ->expect('Gcego\Components')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);

it('lets an external package extend components with its own schema', function (): void {
    $column = new class('name', 'Subject Name') extends Component
    {
        public const string Sortable = 'sortable';

        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()
                ->withType('tables.columns.text')
                ->withProperties(
                    new PropertyDefinition(self::Name, Type::string(), required: true),
                    new PropertyDefinition(self::Sortable, Type::bool(), default: true),
                )
                ->withoutChildren();
        }

        protected static function shorthand(): array
        {
            return [self::Name, self::Label];
        }
    };

    expect($column->type())->toBe('tables.columns.text')
        ->and($column->get($column::Sortable))->toBeTrue()
        ->and($column->validate()->isValid())->toBeTrue()
        ->and($column)->toBeInstanceOf(ComponentInterface::class);
});

it('accepts third-party ComponentInterface implementations as children', function (): void {
    $root = new Component(new Property(Component::Name, 'root'), new ChildComponent('child'));

    expect($root->children()->first())->toBeInstanceOf(ComponentInterface::class);
});
