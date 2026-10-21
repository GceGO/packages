<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Blueprints\Tests\Fixtures\ContractTable;
use Gcego\Blueprints\Tests\Fixtures\PageKind;
use Gcego\Blueprints\Tests\Fixtures\PagesDeriver;
use Gcego\Blueprints\Tests\Fixtures\PlainTable;
use Gcego\Blueprints\Tests\Fixtures\ReportsBlueprint;
use Gcego\Blueprints\Tests\Fixtures\SelfDeriver;
use Gcego\Blueprints\Tests\Fixtures\TableContract;
use Gcego\Blueprints\Validation\ReferenceRules;
use Gcego\Blueprints\Validation\Rules\ImplementsContract;
use Gcego\Core\Gcego;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryConfig;
use Gcego\Registry\RegistryServiceProvider;

it('registers the blueprint kind and its discovery root automatically', function (): void {
    $roots = app(RegistryConfig::class)->roots;

    expect(app(DefinitionKinds::class)->get('blueprint'))->toBeInstanceOf(BlueprintKind::class)
        ->and(app(DefinitionKinds::class)->has('module'))->toBeFalse()
        ->and(array_map(static fn ($root): array => [$root->path, $root->kind], $roots))->toBe([[$this->workspace->path('Modules'), 'blueprint']])
        ->and(app(Gcego::class)->hasCapability('blueprints'))->toBeTrue()
        ->and(app(Gcego::class)->package('gcego/blueprints')->requires)->toBe(['gcego/core', 'gcego/registry']);
});

it('keeps a discovery root the registry configuration already lists', function (): void {
    $this->rebuildApplication(['gcego-registry.discovery.roots' => [['path' => $this->workspace->path('Modules'), 'kind' => 'blueprint', 'entrypoint' => 'file']]]);

    expect(array_map(static fn ($root): string => $root->entrypoint->strategy, app(RegistryConfig::class)->roots))->toBe(['file']);
});

it('adds no discovery roots when blueprint discovery is off', function (): void {
    $this->rebuildApplication(['gcego-blueprints.discovery.enabled' => false]);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    expect(app(RegistryConfig::class)->roots)->toBe([])
        ->and(app(Registry::class)->names())->toBe([]);
});

it('discovers nested class-based blueprint modules and ignores their supporting files', function (): void {
    $subjects = 'Modules/Academics/Subjects';
    $this->workspace->blueprintModule($subjects, "Name::make('subjects'), Title::make('Subject Management'), Table::make(\\{ns}\\Tables\\SubjectsTable::class), Pages::make([\\{ns}\\Pages\\ListSubjects::class, \\{ns}\\Pages\\EditSubject::class])");
    $this->workspace->supportingClass($subjects.'/Tables/SubjectsTable');
    $this->workspace->supportingClass($subjects.'/Pages/ListSubjects');
    $this->workspace->supportingClass($subjects.'/Pages/EditSubject');
    $this->workspace->blueprintModule('Modules/Academics/Topics', "Name::make('topics')", returnsClass: false);
    $this->workspace->blueprintModule('Modules/Finance/Invoices', "Name::make('invoices'), Description::make('Billing')");

    $registry = app(Registry::class);
    $blueprint = $registry->get('blueprint:subjects', Blueprint::class);

    expect($registry->names('blueprint'))->toBe(['subjects', 'topics', 'invoices'])
        ->and($blueprint->pages())->toBe([
            $this->workspace->className($subjects.'/Pages/ListSubjects'),
            $this->workspace->className($subjects.'/Pages/EditSubject'),
        ])
        ->and($registry->descriptor('blueprint:subjects')->metadata)->toMatchArray([
            'title' => 'Subject Management',
            'table' => $this->workspace->className($subjects.'/Tables/SubjectsTable'),
            'definitions' => 'name,title,table,pages',
        ])
        ->and($registry->descriptor('blueprint:invoices')->metadata['description'])->toBe('Billing');
});

it('rejects two modules that declare the same blueprint name, naming both files', function (): void {
    $first = $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");
    $second = $this->workspace->blueprintModule('Modules/Archive/Courses', "Name::make('subjects')");

    expect(fn () => app(Registry::class)->names())->toThrow(DuplicateDefinitionException::class, $second)
        ->and(fn () => app(Registry::class)->names())->toThrow(DuplicateDefinitionException::class, $first);
});

it('registers a class-based provider listed in the registry configuration', function (): void {
    $this->rebuildApplication(['gcego-registry.providers' => [ReportsBlueprint::class], 'app.name' => 'GCEGO Studio']);

    $blueprint = app(Registry::class)->get('blueprint:reports', Blueprint::class);

    expect($blueprint->title())->toBe('Reports of GCEGO Studio')
        ->and($blueprint->table())->toBe(PlainTable::class);
});

it('reloads blueprint modules after refresh without redeclaring their classes', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");
    $registry = app(Registry::class);
    $first = $registry->get('blueprint:subjects', Blueprint::class);

    $registry->refresh();

    expect($registry->get('blueprint:subjects', Blueprint::class)->name())->toBe($first->name());
});

it('resolves blueprints from the cache and rediscovers them when a supporting file changes', function (): void {
    $config = ['gcego-registry.cache.enabled' => true, 'gcego-registry.cache.verify' => true];
    $subjects = 'Modules/Academics/Subjects';
    $this->workspace->blueprintModule($subjects, "Name::make('subjects'), Table::make(\\{ns}\\Tables\\SubjectsTable::class)");
    $this->workspace->supportingClass($subjects.'/Tables/SubjectsTable');
    $this->rebuildApplication($config);
    $this->artisan('gcego:registry-cache')->assertSuccessful();

    $this->rebuildApplication($config);
    expect(app(Registry::class)->origin())->toBe('cache')
        ->and(app(Registry::class)->get('blueprint:subjects', Blueprint::class)->name())->toBe('subjects');

    $this->workspace->file($subjects.'/Tables/Notes.php', '<?php // a new supporting file');
    $this->rebuildApplication($config);

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->origin())->toBe('discovery');
});

it('asks for a process restart when a loaded blueprint entrypoint changes', function (): void {
    $path = $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");
    app(Registry::class)->names();
    file_put_contents($path, str_replace("'subjects'", "'courses'", (string) file_get_contents($path)));
    touch($path, time() + 5);

    app(Registry::class)->refresh();

    expect(fn () => app(Registry::class)->names())->toThrow(DefinitionLoadException::class, 'Restart the process');
});

it('rejects a discovered blueprint whose reference cannot be autoloaded', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\{ns}\\Tables\\MissingTable::class)");

    expect(fn () => app(Registry::class)->names())->toThrow(
        InvalidDefinitionException::class,
        'MissingTable does not exist or cannot be autoloaded.',
    );
});

it('reports a reference whose class extends a missing feature package class', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\{ns}\\Tables\\SubjectsTable::class)");
    $this->workspace->file('Modules/Academics/Subjects/Tables/SubjectsTable.php', sprintf(
        "<?php\n\nnamespace %s;\n\nfinal class SubjectsTable extends \\Gcego\\Tables\\Table {}\n",
        $this->workspace->className('Modules/Academics/Subjects/Tables'),
    ));

    expect(fn () => app(Registry::class)->names())->toThrow(
        InvalidDefinitionException::class,
        'SubjectsTable cannot be autoloaded: Class "Gcego\\Tables\\Table" not found',
    );
});

it('skips a broken blueprint and records the failure outside strict mode', function (): void {
    $this->rebuildApplication(['gcego-registry.strict' => false]);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\{ns}\\MissingTable::class)");
    $this->workspace->blueprintModule('Modules/Academics/Topics', "Name::make('topics')");

    $registry = app(Registry::class);

    expect($registry->names())->toBe(['topics'])
        ->and($registry->failures())->toHaveCount(1);
});

it('lets reference validation be turned off in the testing environment', function (): void {
    $this->rebuildApplication(['gcego-blueprints.validate_references' => false]);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\{ns}\\MissingTable::class)");

    expect(app(Registry::class)->names())->toBe(['subjects']);
});

it('validates references in production even when the configuration turns validation off', function (string $environment): void {
    $this->rebuildApplication(['gcego-blueprints.validate_references' => false, 'env' => $environment]);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\{ns}\\MissingTable::class)");

    expect(fn () => app(Registry::class)->names())->toThrow(InvalidDefinitionException::class, 'MissingTable does not exist');
})->with(['production', 'staging']);

it('reports a module whose definitions are invalid as a load failure naming the file', function (): void {
    $path = $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Table::make(\\stdClass::class), Table::make(\\ArrayObject::class)");

    expect(fn () => app(Registry::class)->names())->toThrow(DefinitionLoadException::class, $path);
});

it('enforces contracts that an installed feature package adds', function (): void {
    app(ReferenceRules::class)->add(new ImplementsContract(Table::Key, TableContract::class));
    $registry = app(Registry::class);

    expect($registry->register(Blueprint::define(Name::make('subjects'), Table::make(ContractTable::class)))->key())->toBe('blueprint:subjects')
        ->and(fn () => $registry->register(Blueprint::define(Name::make('topics'), Table::make(PlainTable::class))))
        ->toThrow(InvalidDefinitionException::class, 'must implement '.TableContract::class);
});

it('refuses reference rules added after blueprints were validated', function (): void {
    app(Registry::class)->register(Blueprint::define(Name::make('subjects')));

    expect(fn () => app(ReferenceRules::class)->add(new ImplementsContract(Table::Key, TableContract::class)))
        ->toThrow(LogicException::class, 'blueprints were already validated with these reference rules');
});

it('derives no definitions unless the blueprint requests them', function (): void {
    app(DefinitionKinds::class)->register(new PageKind);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Pages::make([\\".PlainTable::class.'::class])');

    expect(app(Registry::class)->names())->toBe(['subjects']);
});

it('registers the definitions of explicitly requested derivers', function (): void {
    app(DefinitionKinds::class)->register(new PageKind);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', 'Name::make(\'subjects\'), Pages::make([\\'.ContractTable::class.'::class, \\'.PlainTable::class.'::class]), Derive::make([\\'.PagesDeriver::class.'::class])');

    $registry = app(Registry::class);

    expect($registry->names('blueprint'))->toBe(['subjects'])
        ->and($registry->names('page'))->toBe(['subjects-contract-table', 'subjects-plain-table'])
        ->and($registry->descriptor('page:subjects-plain-table')->metadata['class'])->toBe(PlainTable::class);
});

it('refuses a deriver that derives a blueprint', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects'), Derive::make([\\".SelfDeriver::class.'::class])');

    expect(fn () => app(Registry::class)->names())->toThrow(DefinitionLoadException::class, 'returned a blueprint; derived definitions must be of other kinds.');
});

it('registers the kind when the blueprints provider is loaded before the registry provider', function (): void {
    $this->rebuildApplication(providers: [BlueprintsServiceProvider::class, RegistryServiceProvider::class, GcegoServiceProvider::class]);
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    expect(app(Registry::class)->names('blueprint'))->toBe(['subjects']);
});
