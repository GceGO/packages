<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Wizard;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StatePath;
use Gcego\Forms\Tests\Fixtures\Models\Category;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Forms\Tests\Fixtures\Policies\NoAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'gcego');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function errorsOf(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('hydrates only what the browser may see', function (): void {
    $state = forms()->hydrate(subjectsForm(), $this->world['subject']);

    expect($state['name'])->toBe('Algebra')
        ->and($state['category_id'])->toBe($this->world['categories']['science']->id)
        ->and($state['tags'])->toBe([$this->world['tags']['core']->id])
        ->and(array_values($state['objectives']))->toBe([['title' => 'Equations', 'details' => 'Linear']])
        ->and(StatePath::isItemKey(array_key_first($state['objectives'])))->toBeTrue()
        ->and($state['password'])->toBeNull()
        ->and($state)->toHaveKey('password_confirmation')
        ->and($state)->not->toHaveKey('secret_notes')
        ->and($state)->not->toHaveKey('origin')
        ->and((string) json_encode($state))->not->toContain('TOP SECRET')
        ->and((string) json_encode($state))->not->toContain('hashed-value');
});

it('hydrates defaults for new records', function (): void {
    $state = forms()->hydrate(subjectsForm());

    expect($state['name'])->toBeNull()
        ->and($state['is_published'])->toBeFalse()
        ->and($state['objectives'])->toBe([]);
});

it('returns only validated, visible, editable and submitted values', function (): void {
    $data = forms()->process(subjectsForm(), valid(['description' => '  Living things  ', 'is_published' => '1', 'level' => '3']));

    expect($data)->toBe([
        'name' => 'Biology',
        'code' => 'BIO101',
        'description' => 'Living things',
        'kind' => 'core',
        'level' => 3,
        'credits' => 3,
        'is_published' => true,
        'origin' => 'studio',
    ]);
});

it('translates field properties and rules into validation errors', function (array $input, array $fields): void {
    expect(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid($input)))))->toBe($fields);
})->with([
    'required' => [['name' => '  '], ['name']],
    'max length' => [['name' => str_repeat('a', 121)], ['name']],
    'pattern' => [['code' => 'bio1'], ['code']],
    'custom rule' => [['code' => 'abc123'], ['code']],
    'email' => [['email' => 'not-an-email'], ['email']],
    'integer' => [['level' => '2.5'], ['level']],
    'not converted to zero' => [['level' => 'abc'], ['level']],
    'min' => [['level' => '0'], ['level']],
    'max' => [['level' => '9'], ['level']],
    'enum option' => [['kind' => 'other'], ['kind']],
    'date format' => [['starts_on' => '01/02/2026'], ['starts_on']],
    'date min' => [['starts_on' => '2019-12-31'], ['starts_on']],
    'time' => [['lesson_time' => '25:00'], ['lesson_time']],
    'datetime' => [['published_at' => '2026-02-30T10:00'], ['published_at']],
    'checkbox garbage' => [['agreed' => 'maybe'], ['agreed']],
    'conditional required' => [['credits' => ''], ['credits']],
    'unique' => [['code' => 'MTH101'], ['code']],
    'password confirmation' => [['password' => 'secret123', 'password_confirmation' => 'other'], ['password']],
]);

it('ignores the unique rule for the record being edited', function (): void {
    expect(forms()->process(subjectsForm(), valid(['code' => 'MTH101']), $this->world['subject'])['code'])->toBe('MTH101');
});

it('validates nested repeater items with their own paths', function (): void {
    $errors = errorsOf(fn () => forms()->process(subjectsForm(), valid(['objectives' => ['iaaaaaaaaaa' => ['title' => 'Cells'], 'ibbbbbbbbbb' => ['title' => '']]])));

    expect(array_keys($errors))->toBe(['objectives.ibbbbbbbbbb.title']);

    $tooMany = [];

    for ($i = 0; $i < 6; $i++) {
        $tooMany['i'.str_repeat((string) $i, 10)] = ['title' => 'T'.$i];
    }

    expect(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['objectives' => $tooMany])))))->toBe(['objectives']);
});

it('dehydrates repeaters as ordered lists and drops hidden item fields', function (): void {
    $data = forms()->process(subjectsForm(), valid(['objectives' => ['ibbbbbbbbbb' => ['title' => 'Second', 'details' => 'B'], 'iaaaaaaaaaa' => ['title' => 'First']]]));

    expect($data['objectives'])->toBe([['title' => 'Second', 'details' => 'B'], ['title' => 'First']]);
});

it('runs cross-field validation hooks', function (): void {
    expect(errorsOf(fn () => forms()->process(subjectsForm(), valid(['kind' => 'elective', 'track' => 'arts', 'level' => '6']))))->toBe(['level' => ['Electives stop at level 5.']]);
});

it('only accepts conditional fields while they are visible', function (): void {
    expect(forms()->process(subjectsForm(), valid(['track' => 'arts'])))->not->toHaveKey('track')
        ->and(forms()->process(subjectsForm(), valid(['kind' => 'elective', 'track' => 'arts']))['track'])->toBe('arts')
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['kind' => 'elective', 'track' => 'music'])))))->toBe(['track']);
});

it('ignores disabled, read-only and hidden fields, and refuses unknown keys', function (): void {
    $data = forms()->process(subjectsForm(), valid(['internal_flag' => 'x', 'locked_code' => 'hacked', 'origin' => 'browser']));

    expect($data)->not->toHaveKey('internal_flag')
        ->and($data)->not->toHaveKey('locked_code')
        ->and($data['origin'])->toBe('studio')
        ->and(forms()->process(subjectsForm(), valid(['kind' => 'elective', 'track' => 'arts', 'internal_flag' => 'ok']))['internal_flag'])->toBe('ok')
        ->and(fn () => forms()->process(subjectsForm(), valid(['institution_id' => 2])))->toThrow(InvalidStateException::class, '"institution_id" is not a field of this form.')
        ->and(fn () => forms()->process(subjectsForm(), valid(['objectives' => ['0' => ['title' => 'x']]])))->toThrow(InvalidStateException::class)
        ->and(fn () => forms()->process(subjectsForm(), valid(['objectives' => ['iaaaaaaaaaa' => ['title' => 'x', 'is_admin' => true]]])))->toThrow(InvalidStateException::class);
});

it('enforces field-level authorization on reads and writes', function (): void {
    $without = forms()->process(subjectsForm(), valid(['secret_notes' => 'mine']), $this->world['subject']);
    Gate::define('subjects.secrets', fn ($user): bool => $user->name === 'Editor');
    app()->forgetScopedInstances();
    actAs($this->world['editor']);
    $with = forms()->process(subjectsForm(), valid(['secret_notes' => 'mine']), $this->world['subject']);

    expect($without)->not->toHaveKey('secret_notes')
        ->and($with['secret_notes'])->toBe('mine')
        ->and(forms()->hydrate(subjectsForm(), $this->world['subject'])['secret_notes'])->toBe('TOP SECRET');
});

it('never writes empty passwords and never puts them in messages', function (): void {
    $errors = errorsOf(fn () => forms()->process(subjectsForm(), valid(['password' => 'MySecret!1', 'password_confirmation' => 'nope'])));

    expect(forms()->process(subjectsForm(), valid(['password' => '', 'password_confirmation' => ''])))->not->toHaveKey('password')
        ->and(forms()->process(subjectsForm(), valid(['password' => 'MySecret!1', 'password_confirmation' => 'MySecret!1']))['password'])->toBe('MySecret!1')
        ->and((string) json_encode($errors))->not->toContain('MySecret!1')
        ->and(forms()->process(subjectsForm(), valid(['password' => 'MySecret!1', 'password_confirmation' => 'MySecret!1'])))->not->toHaveKey('password_confirmation');
});

it('sanitizes rich text before it is returned', function (): void {
    $data = forms()->process(subjectsForm(), valid(['body' => '<p>Hello<script>alert(1)</script></p><a href="javascript:x" onclick="y">link</a>']));

    expect($data['body'])->toBe('<p>Hello</p><a rel="noopener noreferrer">link</a>');
});

it('offers and accepts only scoped, permitted relationship values', function (): void {
    $categories = $this->world['categories'];
    $options = forms()->options(choiceField('category_id'), new FormState, Subject::class);

    expect(array_map(fn ($option) => $option->label, $options->options))->toBe(['Arts', 'Science'])
        ->and(forms()->process(subjectsForm(), valid(['category_id' => (string) $categories['arts']->id]), model: Subject::class)['category_id'])->toBe((string) $categories['arts']->id)
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['category_id' => (string) $categories['south']->id]), model: Subject::class))))->toBe(['category_id'])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['category_id' => (string) $categories['retired']->id]), model: Subject::class))))->toBe(['category_id'])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['category_id' => '9999']), model: Subject::class))))->toBe(['category_id'])
        ->and(forms()->process(subjectsForm(), valid(['tags' => [(string) $this->world['tags']['lab']->id]]), model: Subject::class)['tags'])->toBe([(string) $this->world['tags']['lab']->id])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['tags' => ['9999']]), model: Subject::class))))->toBe(['tags']);
});

it('denies relationship options to users who may not list the related records', function (): void {
    Gate::policy(Category::class, NoAccess::class);
    app()->forgetScopedInstances();
    actAs($this->world['editor']);

    expect(forms()->options(choiceField('category_id'), new FormState, Subject::class)->count())->toBe(0)
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['category_id' => (string) $this->world['categories']['arts']->id]), model: Subject::class))))->toBe(['category_id']);
});

it('validates and stores uploads privately, never trusting paths', function (): void {
    Storage::fake('local');
    $pdf = UploadedFile::fake()->createWithContent('syllabus.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $data = forms()->process(subjectsForm(), valid(['syllabus' => $pdf]));

    Storage::disk('local')->assertExists($data['syllabus']);
    expect($data['syllabus'])->toStartWith('gcego-uploads/')
        ->and($data['syllabus'])->not->toContain('syllabus.pdf')
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['syllabus' => UploadedFile::fake()->create('big.pdf', 500)])))))->toBe(['syllabus'])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['syllabus' => realUpload('disguised.pdf', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))])))))->toBe(['syllabus'])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['syllabus' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')])))))->toBe(['syllabus'])
        ->and(array_keys(errorsOf(fn () => forms()->process(subjectsForm(), valid(['syllabus' => '../../.env'])))))->toBe(['syllabus']);

    $this->world['subject']->update(['syllabus' => 'gcego-uploads/current.pdf']);

    expect(forms()->process(subjectsForm(), valid(['syllabus' => 'gcego-uploads/current.pdf', 'code' => 'MTH101']), $this->world['subject'])['syllabus'])->toBe('gcego-uploads/current.pdf');
});

it('validates single wizard steps', function (): void {
    $form = new Form(new Wizard(
        new Step('One', new TextInput('name', new Property(TextInput::Required, true))),
        new Step('Two', new TextInput('code', new Property(TextInput::Required, true))),
    ));

    expect(array_keys(errorsOf(fn () => forms()->validateStep($form, ['name' => '', 'code' => ''], 0))))->toBe(['name'])
        ->and(errorsOf(fn () => forms()->validateStep($form, ['name' => 'A', 'code' => ''], 0)))->toBe([])
        ->and(array_keys(errorsOf(fn () => forms()->process($form, ['name' => 'A', 'code' => '']))))->toBe(['code']);
});
