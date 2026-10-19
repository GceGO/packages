<?php

declare(strict_types=1);

use Gcego\Luminaries\Security\TrustedSvgValidator;

it('accepts icon markup with gradients and local references', function (): void {
    $violations = (new TrustedSvgValidator)->validate((string) file_get_contents(fixtures('icons/trusted/ordinary-level.svg')));

    expect($violations)->toBe([]);
});

it('rejects unsafe SVG content', function (string $file, string $violation): void {
    $violations = (new TrustedSvgValidator)->validate((string) file_get_contents(fixtures("icons/unsafe/{$file}.svg")));

    expect(implode("\n", $violations))->toContain($violation);
})->with([
    'script element' => ['script', 'the <script> element is not allowed'],
    'event handler' => ['handler', 'the event handler attribute "onload" is not allowed'],
    'external reference' => ['external', 'may only reference a fragment'],
    'foreignObject' => ['foreign', 'the <foreignObject> element is not allowed'],
    'entity declaration' => ['entity', 'DOCTYPE and ENTITY declarations are not allowed'],
    'external url()' => ['javascript-url', 'references an external resource'],
    'obfuscated javascript: URL' => ['javascript-url', 'contains a script or data URL'],
]);

it('rejects markup that is not a single svg document', function (string $markup, string $violation): void {
    expect(implode("\n", (new TrustedSvgValidator)->validate($markup)))->toContain($violation);
})->with([
    'not xml' => ['<svg viewBox="0 0 24 24"><path></svg>', 'not well-formed XML'],
    'wrong root' => ['<html xmlns="http://www.w3.org/1999/xhtml"/>', 'root element must be <svg>'],
    'no viewBox' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'must declare a viewBox'],
    'style attribute' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1" style="background:url(x)"/>', 'inline style attributes are not allowed'],
    'style element' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><style>*{}</style></svg>', 'the <style> element is not allowed'],
    'image element' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><image href="#a"/></svg>', 'the <image> element is not allowed'],
    'animation' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><set attributeName="href" to="javascript:alert(1)"/></svg>', 'the <set> element is not allowed'],
    'processing instruction' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><?php echo 1; ?></svg>', 'processing instructions are not allowed'],
    'data URL' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><use href="#a" fill="data:text/html,x"/></svg>', 'contains a script or data URL'],
    'oversized' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><!--'.str_repeat('x', 70000).'--></svg>', 'larger than'],
]);
