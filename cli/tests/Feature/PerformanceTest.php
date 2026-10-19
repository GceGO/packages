<?php

declare(strict_types=1);

use Gcego\Cli\Stubs\StubRenderer;
use Gcego\Cli\Stubs\StubValue;

function recordPerformance(array $numbers): void
{
    $file = dirname(__DIR__, 2).'/build/performance.json';
    @mkdir(dirname($file), 0777, true);
    $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    file_put_contents($file, (string) json_encode([...(is_array($existing) ? $existing : []), ...$numbers], JSON_PRETTY_PRINT));
}

it('inspects and validates a large registry within bounds', function (): void {
    @mkdir(ws('app/GCEGo/Pages'), 0777, true);

    foreach (range(1, 300) as $number) {
        file_put_contents(ws("app/GCEGo/Pages/page-{$number}.php"), "<?php\n\nuse Gcego\\Components\\Property;\nuse Gcego\\Pages\\Page;\nuse Gcego\\Security\\Requirements\\Authenticated;\n\nreturn new Page(new Property(Page::Name, 'page-{$number}'), new Property(Page::Title, 'Page {$number}'), new Authenticated);\n");
    }

    $memory = memory_get_usage();
    $measure = static function (Closure $callback): float {
        $start = hrtime(true);
        $callback();

        return round((hrtime(true) - $start) / 1e6, 1);
    };

    $registry = $measure(fn () => expect(gcegoJson('gcego:registry', ['--limit' => 50])['total'])->toBe(300));
    $validate = $measure(fn () => expect(gcegoJson('gcego:validate')['summary']['definitions'])->toBe(300));
    $list = $measure(function (): void {
        foreach (range(1, 10) as $ignored) {
            gcego('gcego:list', ['--type' => 'pages', '--limit' => 20]);
        }
    });
    $growth = memory_get_usage() - $memory;
    recordPerformance(['cli.registry_300_ms' => $registry, 'cli.validate_300_ms' => $validate, 'cli.list_10x_300_ms' => $list, 'cli.memory_growth_bytes' => $growth]);

    expect(gcegoJson('gcego:registry', ['--limit' => 50])['definitions'])->toHaveCount(50)
        ->and($registry)->toBeLessThan(3000)->and($validate)->toBeLessThan(5000)->and($list)->toBeLessThan(15000)
        ->and($growth)->toBeLessThan(64 * 1024 * 1024);
});

it('renders stubs and plans generations quickly', function (): void {
    $renderer = app(StubRenderer::class);
    $start = hrtime(true);

    foreach (range(1, 500) as $number) {
        $renderer->render('page', ['imports' => StubValue::code('use Gcego\\Pages\\Page;'), 'page_class' => StubValue::identifier('Page'), 'name' => StubValue::string('p'.$number), 'title' => StubValue::string('P'), 'requirement' => StubValue::code('new Authenticated')]);
    }

    $render = round((hrtime(true) - $start) / 1e6, 1);
    $start = hrtime(true);
    gcego('gcego:make-page', ['name' => 'Reports', '--dry-run' => true]);
    $plan = round((hrtime(true) - $start) / 1e6, 1);
    recordPerformance(['cli.render_500_stubs_ms' => $render, 'cli.plan_page_ms' => $plan]);

    expect($render)->toBeLessThan(1000)->and($plan)->toBeLessThan(1000);
});
