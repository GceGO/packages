<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintCollection;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Blueprints\Normalization\BlueprintNormalizer;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleStyle;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;

/**
 * The future developer experience, with fixtures standing in for gcego/data, tables, forms and actions.
 */
function subjectsBlueprint(): Blueprint
{
    return new Blueprint(
        'subjects',

        new ExampleSource(FakeModel::class),

        new ExampleTable(
            'table',
            new ExampleColumn('name', 'Subject Name'),
            new ExampleColumn('code', 'Code'),
        ),

        new ExampleForm(
            'form',
            new ExampleColumn('name', 'Subject Name'),
            new ExampleColumn('code', 'Subject Code'),
        ),

        new ExampleAction('create'),
        new ExampleAction('edit'),

        new ExampleStyle(['rounded-2xl', 'border', 'bg-white', 'shadow-sm']),
    );
}

it('composes the full feature definition with minimal code', function (): void {
    $blueprint = subjectsBlueprint()->assertValid();

    expect($blueprint->title())->toBe('Subjects')
        ->and($blueprint->roles())->toBe(['source', 'table', 'form', 'action'])
        ->and($blueprint->requirePart('source')->get(ExampleSource::Model))->toBe(FakeModel::class)
        ->and($blueprint->requirePart('table')->childCount())->toBe(2)
        ->and($blueprint->parts('action')->count())->toBe(2)
        ->and($blueprint->configurations()->count())->toBe(1);
});

it('lets a registry consume blueprints through stable contracts only', function (): void {
    $resolver = new BlueprintCollection(subjectsBlueprint(), new Blueprint('dashboard'));
    $blueprint = $resolver->resolve('subjects');
    $normalized = (new BlueprintNormalizer)->normalize($blueprint);

    expect($blueprint->identity()->key())->toBe('subjects')
        ->and($blueprint->validate()->isValid())->toBeTrue()
        ->and(array_keys($normalized['roles']))->toBe(['action', 'form', 'source', 'table'])
        ->and($normalized['component']['path'])->toBe('subjects');
});

it('accepts parts from packages it has never heard of', function (): void {
    $calendar = new class('calendar') extends Component implements BlueprintPart
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withType('scheduling.calendar')->withoutChildren();
        }

        protected static function shorthand(): array
        {
            return [self::Name];
        }

        public function blueprintRole(): BlueprintRole
        {
            return BlueprintRole::single('scheduling.calendar');
        }
    };

    $blueprint = subjectsBlueprint()->withPart($calendar);

    expect($blueprint->part('scheduling.calendar'))->toBe($calendar)
        ->and($blueprint->validate()->isValid())->toBeTrue();
});

it('builds hundreds of blueprints quickly enough to stay invisible', function (): void {
    $start = hrtime(true);
    $blueprints = [];

    for ($index = 0; $index < 300; $index++) {
        $blueprint = new Blueprint('feature-'.$index, new ExampleTable('table', new ExampleColumn('name')), new ExampleAction('create'));
        $blueprint->part('table');
        $blueprints[] = $blueprint;
    }

    $collection = new BlueprintCollection(...$blueprints);
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    // Generous regression guard (not a benchmark): see benchmarks/run.php for measurements.
    expect($collection->count())->toBe(300)
        ->and($elapsedMs)->toBeLessThan(2000.0);
});
