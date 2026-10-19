<?php

declare(strict_types=1);

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Exceptions\InvalidNameException;
use Gcego\Cli\Exceptions\UnsafePathException;
use Gcego\Cli\Generation\NameNormalizer;
use Gcego\Cli\Generation\NamespaceResolver;
use Gcego\Cli\Generation\PathResolver;

it('normalizes names with Laravel\'s Str helpers', function (): void {
    $names = new NameNormalizer;

    expect($names->className('student_stats'))->toBe('StudentStats')
        ->and($names->className('student-stats'))->toBe('StudentStats')
        ->and($names->slug('StudentStats'))->toBe('student-stats')
        ->and($names->snake('StudentStats'))->toBe('student_stats')
        ->and($names->camel('PublishSubject'))->toBe('publishSubject')
        ->and($names->plural('Subject'))->toBe('Subjects')
        ->and($names->plural('Category'))->toBe('Categories')
        ->and($names->title('AcademicSettings'))->toBe('Academic Settings')
        ->and($names->qualifiedClass('\\App\\Models\\Subject', 'model'))->toBe('App\\Models\\Subject');
});

it('rejects names that are not identifiers, reserved words, or too long', function (string $name): void {
    expect(fn () => (new NameNormalizer)->className($name))->toThrow(InvalidNameException::class);
})->with(['../Subject', 'Sub ject', '9Lives', '', 'class', 'Namespace', str_repeat('A', 65), "Bad\0Name", 'Subject;']);

it('rejects invalid class names and identifiers', function (): void {
    $names = new NameNormalizer;

    expect(fn () => $names->qualifiedClass('App\\Models\\..\\Subject', 'model'))->toThrow(InvalidNameException::class)
        ->and(fn () => $names->qualifiedClass('App\\Class\\Subject', 'model'))->toThrow(InvalidNameException::class, 'reserved')
        ->and(fn () => $names->identifier('Academics', 'module'))->toThrow(InvalidNameException::class)
        ->and($names->identifier('content-approval', 'workflow'))->toBe('content-approval');
});

it('keeps every path inside the application and away from symbolic links', function (): void {
    $paths = new PathResolver(ws());
    @mkdir(ws('app'), 0777, true);
    @mkdir(sys_get_temp_dir().'/gcego-cli-outside-'.getmypid(), 0777, true);
    @symlink(sys_get_temp_dir().'/gcego-cli-outside-'.getmypid(), ws('app/escape'));

    expect($paths->resolve('app/GCEGo/Pages/a.php'))->toBe(ws('app/GCEGo/Pages/a.php'))
        ->and($paths->resolve(ws('app/./GCEGo//Pages/a.php')))->toBe(ws('app/GCEGo/Pages/a.php'))
        ->and($paths->relative(ws('app/GCEGo/Pages/a.php')))->toBe('app/GCEGo/Pages/a.php')
        ->and(fn () => $paths->resolve('app/../../etc/passwd'))->toThrow(UnsafePathException::class, 'contains ".."')
        ->and(fn () => $paths->resolve('/etc/passwd'))->toThrow(UnsafePathException::class, 'outside the application')
        ->and(fn () => $paths->resolve(ws().'-sibling/app/a.php'))->toThrow(UnsafePathException::class, 'outside the application')
        ->and(fn () => $paths->resolve('app/escape/a.php'))->toThrow(UnsafePathException::class, 'symbolic link')
        ->and(fn () => $paths->resolve("app/a\0.php"))->toThrow(UnsafePathException::class);

    @unlink(ws('app/escape'));
    @rmdir(sys_get_temp_dir().'/gcego-cli-outside-'.getmypid());
});

it('maps folders to namespaces with the application\'s PSR-4 rules', function (): void {
    $namespaces = app(NamespaceResolver::class);

    expect($namespaces->forDirectory(ws('app/GCEGo/Support/Actions')))->toBe('App\\GCEGo\\Support\\Actions')
        ->and($namespaces->forDirectory(ws('tests/Feature')))->toBe('Tests\\Feature')
        ->and($namespaces->qualify(ws('app/Notifications'), 'welcome'))->toBe('App\\Notifications\\Welcome')
        ->and(fn () => $namespaces->forDirectory(ws('lib/Thing')))->toThrow(GenerationException::class, 'not covered by a PSR-4 rule')
        ->and(fn () => $namespaces->forDirectory(ws('app/my-folder')))->toThrow(GenerationException::class, 'not a valid namespace segment');
});
