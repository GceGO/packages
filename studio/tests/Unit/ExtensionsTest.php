<?php

declare(strict_types=1);

use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Exceptions\ExtensionException;
use Gcego\Studio\Extensions\StudioExtensionContext;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsExtension;

function extension(string $id, int $priority = 100, ?Closure $register = null): StudioExtension
{
    return new class($id, $priority, $register) implements StudioExtension
    {
        public static array $order = [];

        public function __construct(private string $name, private int $order_, private ?Closure $callback) {}

        public function id(): string
        {
            return $this->name;
        }

        public function priority(): int
        {
            return $this->order_;
        }

        public function register(StudioExtensionContext $context): void
        {
            self::$order[] = $this->name;
            ($this->callback)?->__invoke($context);
        }
    };
}

it('applies trusted extensions once, in priority then id order', function (): void {
    $registry = new StudioExtensionRegistry(app());
    $registry->add(extension('vendor/zeta', 10));
    $registry->add(extension('vendor/alpha', 10));
    $registry->add(extension('vendor/first', 1));

    expect($registry->ids())->toBe(['vendor/first', 'vendor/alpha', 'vendor/zeta'])
        ->and($registry->contexts())->toBe($registry->contexts());
});

it('refuses duplicate ids, invalid ids, invalid contributions and late registration', function (): void {
    $registry = new StudioExtensionRegistry(app());
    $registry->add(extension('vendor/reports'));
    $registry->add(extension('vendor/reports'));   // the same class again: ignored

    expect($registry->ids())->toBe(['vendor/reports'])
        ->and(fn () => $registry->add(new ReportsExtension))->not->toThrow(ExtensionException::class)
        ->and(fn () => (static function (): void {
            $clash = new StudioExtensionRegistry(app());
            $clash->add(new ReportsExtension);
            $clash->add(extension('acme/reports'));
        })())->toThrow(ExtensionException::class, 'two different classes')
        ->and(fn () => $registry->add(extension('Not An Id!')))->toThrow(ExtensionException::class, 'needs an id')
        ->and(fn () => $registry->add(stdClass::class))->toThrow(ExtensionException::class, 'is not a class implementing');

    $broken = new StudioExtensionRegistry(app());
    $broken->add(extension('vendor/broken', register: static fn (StudioExtensionContext $context) => $context->healthCheck(stdClass::class)));
    expect(fn () => $broken->contexts())->toThrow(ExtensionException::class, 'is not a');

    $registry->contexts();
    expect(fn () => $registry->add(extension('vendor/late')))->toThrow(ExtensionException::class, 'after Studio applied');
});

it('registers configured extensions at boot, once, and counts the same class once', function (): void {
    ReportsExtension::$registered = 0;
    $this->reboot(['gcego-studio.extensions' => [ReportsExtension::class, ReportsExtension::class]]);

    expect(studio()->manager()->extensions()->ids())->toBe(['acme/reports'])
        ->and(ReportsExtension::$registered)->toBe(1)
        ->and(studio()->inspect()['sections']['acme/reports:reports'] ?? null)->toBe(['reports' => 3])
        ->and(fn () => studio()->extend(extension('vendor/late')))->toThrow(ExtensionException::class);
});
