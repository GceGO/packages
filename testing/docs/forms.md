# Forms

`GcegoTest::form($form, $model)` processes the form with `FormManager::process()`, exactly as a submission is processed:

1. Normalize the input.
2. Refuse undeclared paths.
3. Apply the acting user's field access.
4. Validate.
5. Dehydrate.

Nothing is persisted.

<!-- example:forms -->
```php
use Gcego\Forms\Form;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

$form = app(Gcego\Registry\Registry::class)->blueprint('subjects')->part('form');
assert($form instanceof Form);

GcegoTest::form($form, Subject::class)
    ->actingAs($admin, 1)
    ->fill(['name' => '  Mathematics  '])
    ->assertValid()
    ->assertState('name', 'Mathematics');

GcegoTest::form($form, Subject::class)->actingAs($admin, 1)->fill(['name' => ''])->assertInvalid(['name' => 'required']);
GcegoTest::form($form, Subject::class)->actingAs($admin, 1)->fill(['name' => 'X', 'institution_id' => 2])->assertRejectsUnknownField('institution_id');
```

| Assertion | Meaning |
| --- | --- |
| `assertValid()` | processing succeeded |
| `assertInvalid(['path', 'path' => 'text'])` | validation failed, with an error for each path (containing the text) |
| `assertState($path, $value)` | the processed value (after trimming, casting, dehydration) |
| `assertDropped($path)` | the submitted value was ignored: read-only, disabled, hidden by a condition, or not writable by the acting user |
| `assertRejectsUnknownField($path)` | the submission was refused for an undeclared path (a forged field) |
| `assertHydrated($path, $value)` / `assertNotHydrated($path)` | what `hydrate()` loads for `record()`; unreadable fields and passwords are not loaded |
| `assertHasField`, `assertMissingField`, `assertFieldLabel`, `assertFieldRequired`, `assertFieldDisabled`, `assertFieldDefault` | the declared fields |
| `assertFieldVisible($path, $visible)` | `VisibleWhen` conditions evaluated against the filled state |
| `assertHealthy()` | the form inspector reports no errors |

Paths use dot notation. Repeater items use their item keys (`objectives.iaaaaaaaaaa.title`), as in the error bag.

To test a form inside its Livewire host (`FormHost`), see [livewire.md](livewire.md). To test it through a Create or Edit action, use [`GcegoTest::action()`](actions.md).
