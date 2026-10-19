<?php

declare(strict_types=1);

use Gcego\Cli\CliManager;
use Gcego\Cli\Exceptions\ExtensionException;
use Gcego\Cli\Exceptions\StubException;
use Gcego\Cli\Stubs\StubManager;
use Gcego\Cli\Stubs\StubRenderer;
use Gcego\Cli\Stubs\StubValue;

function stubFile(string $directory, string $name, string $contents): void
{
    @mkdir(ws($directory), 0777, true);
    file_put_contents(ws($directory.'/'.$name.'.stub'), $contents);
}

it('replaces placeholders with typed, escaped values', function (): void {
    stubFile('stubs/gcego', 'blueprint', "<?php\n\nreturn ['{{ name }}', {{ code }}, {{ class }}::class];\n");
    $rendered = app(StubRenderer::class)->render('blueprint', ['name' => StubValue::string("O'Brien \\ x"), 'code' => StubValue::code('[1, 2]'), 'class' => StubValue::identifier('App\\Models\\Subject')]);

    expect($rendered)->toBe("<?php\n\nreturn ['O\\'Brien \\\\ x', [1, 2], App\\Models\\Subject::class];\n")
        ->and(eval(substr($rendered, 5)))->toBe(["O'Brien \\ x", [1, 2], 'App\\Models\\Subject']);
});

it('refuses unknown or malformed placeholders, invalid PHP and unsafe values', function (): void {
    stubFile('stubs/gcego', 'blueprint', "<?php\n\nreturn '{{ unknown }}';\n");
    expect(fn () => app(StubRenderer::class)->render('blueprint', ['name' => StubValue::string('x')]))->toThrow(StubException::class, 'unknown placeholder {{ unknown }}');

    stubFile('stubs/gcego', 'blueprint', "<?php\n\nreturn '{{ Name }}';\n");
    expect(fn () => app(StubRenderer::class)->render('blueprint', []))->toThrow(StubException::class, 'malformed placeholder');

    stubFile('stubs/gcego', 'blueprint', "<?php\n\nreturn {{ name }};\n");
    expect(fn () => app(StubRenderer::class)->render('blueprint', ['name' => StubValue::code('new (')]))->toThrow(StubException::class, 'renders invalid PHP')
        ->and(fn () => StubValue::identifier('App\\Models; system("x")'))->toThrow(StubException::class)
        ->and(fn () => StubValue::string("line\x07"))->toThrow(StubException::class)
        ->and(fn () => app(StubManager::class)->path('../../etc/passwd'))->toThrow(StubException::class, 'not a valid stub name');
});

it('prefers application overrides, then package contributions, then the built-in stubs', function (): void {
    $stubs = app(StubManager::class);
    expect($stubs->all()['page']['source'])->toBe('built-in');

    stubFile('vendor-stubs/acme', 'page', "<?php\n// acme\n");
    app(CliManager::class)->stubs('acme/pages', ws('vendor-stubs/acme'));
    expect($stubs->all()['page']['source'])->toBe('acme/pages')->and($stubs->contents('page'))->toContain('// acme');

    stubFile('stubs/gcego', 'page', "<?php\n// mine\n");
    expect($stubs->all()['page']['source'])->toBe('custom')->and($stubs->contents('page'))->toContain('// mine');

    stubFile('vendor-stubs/other', 'page', "<?php\n// other\n");
    expect(fn () => app(CliManager::class)->stubs('other/pages', ws('vendor-stubs/other')))->toThrow(ExtensionException::class, 'contributed by both acme/pages and other/pages')
        ->and(fn () => app(CliManager::class)->stubs('other/pages', ws('nowhere')))->toThrow(ExtensionException::class, 'does not exist');
});

it('generates with a customized stub, and reports one that lost a placeholder', function (): void {
    stubFile('stubs/gcego', 'page', str_replace('// Discovered as', "// Our house style.\n// Discovered as", (string) file_get_contents(dirname(__DIR__, 2).'/stubs/page.stub')));
    [$code] = gcego('gcego:make-page', ['name' => 'Reports']);

    expect($code)->toBe(0)->and(file_get_contents(ws('app/GCEGo/Pages/reports.php')))->toContain('// Our house style.');

    stubFile('stubs/gcego', 'page', "<?php\n\nreturn {{ page }};\n");
    [$code, $output] = gcego('gcego:make-page', ['name' => 'Other']);
    expect($code)->toBe(1)->and($output)->toContain('unknown placeholder {{ page }}')->and(is_file(ws('app/GCEGo/Pages/other.php')))->toBeFalse();
});
