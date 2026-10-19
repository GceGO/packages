# Blueprints and the registry

## Blueprints

<!-- example:blueprint -->
```php
use Gcego\Blueprints\Blueprint;
use Gcego\Testing\GcegoTest;

$subjects = app(Gcego\Registry\Registry::class)->blueprint('subjects');

GcegoTest::blueprint($subjects)
    ->assertValid()
    ->assertName('subjects')
    ->assertHasComponent('source')
    ->assertHasComponent('table')
    ->assertMissingComponent('navigation')
    ->assertStableIdentity('subjects')
    ->part('table')->assertHasChild('name');

GcegoTest::blueprint(new Blueprint('Not A Name'))->assertInvalid('component.invalid_value');
```

- **Validity**: `assertValid()` validates components and composition together (`BlueprintValidator`).
- **Error codes**: `assertInvalid()` takes a code, e.g. `blueprint.missing_required_role` or `component.invalid_value`, and optionally a role.
- **Roles and parts**: `assertHasComponent($role)`, `assertPartCount($role, $n)` and `assertRoles([...])` read the composition.
- **Parts**: `part($role)` returns a component testable for the part.

## Registry

<!-- example:registry -->
```php
use Gcego\Blueprints\Blueprint;
use Gcego\Testing\GcegoTest;

GcegoTest::registry()
    ->assertHas('blueprint:subjects')
    ->assertKind('blueprint:subjects', 'blueprint')
    ->assertResolves('blueprint:subjects', Blueprint::class)
    ->assertNotFound('blueprint:subjectz', 'blueprint:subjects')   // suggests the near match
    ->assertRejectsDuplicate(new Blueprint('subjects'))
    ->assertRejectsInvalid(new Blueprint('Not Valid'))
    ->assertNoFailures()
    ->assertHealthy();
```

- **Registering**: `register($definition)` registers at runtime, exactly as an application would. It is the only method that changes the registry.
- **Refused registrations**: `assertRejectsDuplicate()` and `assertRejectsInvalid()` leave the registry unchanged when they pass.

## Registry fixtures

To test discovery, write definition files into a temporary workspace and point discovery at it. The application's `app/GCEGo` directories are never read or changed:

<!-- example:registry-fixture -->
```php
use Gcego\Blueprints\Blueprint;
use Gcego\Testing\GcegoTest;

$fixture = GcegoTest::fixtures()->registry()
    ->definition('Definitions/teachers.php', "new Blueprint('teachers')", [Blueprint::class])
    ->root('Definitions', 'blueprint', 'file');

$fixture->apply(app());

GcegoTest::registry()->assertHas('blueprint:teachers')->assertMissing('blueprint:subjects')->assertOrigin('discovery');
```

- **What `apply()` does**:
  - It sets the `gcego-registry` configuration: discovery on, roots in the workspace, no definition providers, cache off.
  - It rebuilds the registry services.
- **Services resolved earlier** keep the previous registry. Apply the fixture before using them, or return `$fixture->config()` from `gcegoConfiguration()`.
- **Cleanup**: the files are deleted by `GcegoTest::fixtures()->cleanup()` and by `GcegoTest::reset()`.
