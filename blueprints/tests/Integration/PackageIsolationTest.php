<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\Actions;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Infolist;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Widgets;
use Gcego\Registry\Registry;

/**
 * @return list<string>
 */
function blueprintSourceFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('requires only PHP, gcego/core, gcego/registry and the Laravel contracts', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($manifest) && is_array($manifest['require']) ? array_keys($manifest['require']) : null)
        ->toBe(['php', 'gcego/core', 'gcego/registry', 'illuminate/contracts', 'illuminate/support']);
});

it('never references an optional feature engine in its code', function (string $file): void {
    $code = implode('', array_map(
        static fn (PhpToken $token): string => $token->is([T_COMMENT, T_DOC_COMMENT]) ? '' : $token->text,
        PhpToken::tokenize((string) file_get_contents($file)),
    ));

    expect($code)->not->toMatch('/Gcego\\\\(Components|Tables|Forms|Infolists|Actions|Pages|Widgets|Runtime|Modules)\\\\/');
})->with(blueprintSourceFiles(...));

it('registers references to feature packages that are not installed when references are unchecked', function (): void {
    config()->set('gcego-blueprints.validate_references', false);
    $blueprint = Blueprint::define(
        Name::make('subjects'),
        Table::make('Gcego\\Tables\\Table'),
        Form::make('Gcego\\Forms\\Form'),
        Infolist::make('Gcego\\Infolists\\Infolist'),
        Pages::make(['Gcego\\Pages\\Page']),
        Actions::make(['Gcego\\Actions\\Action']),
        Widgets::make(['Gcego\\Widgets\\Widget']),
    );

    expect(array_filter(['Gcego\\Components\\Component', 'Gcego\\Tables\\Table', 'Gcego\\Pages\\Page', 'Gcego\\Runtime\\Runtime'], class_exists(...)))->toBe([])
        ->and($blueprint->table())->toBe('Gcego\\Tables\\Table')
        ->and(app(Registry::class)->register($blueprint)->key())->toBe('blueprint:subjects');
});
