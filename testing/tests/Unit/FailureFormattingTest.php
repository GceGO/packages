<?php

declare(strict_types=1);

use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Diagnostics\AssertionFailureFormatter;
use Gcego\Testing\Diagnostics\Failure;
use Gcego\Testing\Diagnostics\Redactor;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

it('formats a failure with subject, context, expected, actual and suggestion', function (): void {
    $text = (new AssertionFailureFormatter)->format(new Failure('table', "assertColumnSearchable('name')", 'Expected table column [name] to be searchable.', ['Table' => 'subjects'], ['searchable' => true], ['searchable' => false], 'Enable searching for the registered column.'));

    expect($text)->toBe(implode("\n", [
        'GCEGO TABLE ASSERTION FAILED',
        '',
        'Expected table column [name] to be searchable.',
        '',
        'Table: subjects',
        "Assertion: assertColumnSearchable('name')",
        '',
        'Expected:',
        '  searchable = true',
        '',
        'Actual:',
        '  searchable = false',
        '',
        'Suggestion:',
        '  Enable searching for the registered column.',
    ]));
});

it('redacts sensitive keys at any depth, never the values elsewhere', function (): void {
    $text = (new AssertionFailureFormatter)->format(new Failure('form', 'assertValid()', 'Invalid.', ['User' => 7], ['state' => ['name' => 'Ada', 'password' => 'hunter2']], ['api_token' => 'tok_live_123', 'remember_token' => 'abc', 'nested' => ['client_secret' => 's3cr3t', 'note' => 'visible']]));

    expect($text)->not->toContain('hunter2')->not->toContain('tok_live_123')->not->toContain('s3cr3t')->not->toContain('= abc')
        ->toContain('[redacted]')->toContain('Ada')->toContain('visible');
});

it('uses configurable redaction patterns and scrubs known secrets from text', function (): void {
    $redactor = new Redactor(['*pin*']);

    expect($redactor->redact(['pin_code' => '1234', 'password' => 'kept']))->toBe(['pin_code' => '[redacted]', 'password' => 'kept'])
        ->and($redactor->scrub('token=abc123; other', ['abc123']))->toBe('token=[redacted]; other');
});

it('prints only the message when verbose failures are off', function (): void {
    expect((new AssertionFailureFormatter(verbose: false))->format(new Failure('page', 'assertOpens()', 'Expected the page to open.', ['Page' => 'x'], ['status' => 200])))
        ->toBe("GCEGO PAGE ASSERTION FAILED\n\nExpected the page to open.");
});

it('raises PHPUnit failures and counts passes as assertions', function (): void {
    $verifier = new Verifier;
    $before = Assert::getCount();
    $verifier->that(true, new Failure('x', 'y', 'z'));

    expect(Assert::getCount())->toBe($before + 1)
        ->and(fn () => $verifier->that(false, new Failure('x', 'assertThing()', 'It failed.')))->toThrow(AssertionFailedError::class, 'It failed.');
});

it('builds the failure lazily', function (): void {
    $built = false;
    (new Verifier)->that(true, function () use (&$built): Failure {
        $built = true;

        return new Failure('x', 'y', 'z');
    });

    expect($built)->toBeFalse();
});

it('finds a root cause in a wrapped exception', function (): void {
    $root = new InvalidArgumentException('root');
    $wrapped = new RuntimeException('outer', 0, new LogicException('middle', 0, $root));

    expect((new Verifier)->rootOf($wrapped, InvalidArgumentException::class))->toBe($root)
        ->and((new Verifier)->rootOf($wrapped, DomainException::class))->toBeNull();
});
