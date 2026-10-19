<?php

declare(strict_types=1);

use Gcego\Media\Fields\MediaUpload;
use Gcego\Media\MediaCollection;
use Gcego\Media\Models\MediaRecord;
use Symfony\Component\HttpFoundation\Response;

/**
 * Run a README example as written, with its variables defined.
 *
 * @param  array<string, mixed>  $variables
 */
function readmeExample(string $name, array $variables = []): mixed
{
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = "use Gcego\\Components\\Property;\nuse Gcego\\Media\\Definitions\\Fit;\nuse Gcego\\Media\\Definitions\\Variant;\nuse Gcego\\Media\\Fields\\MediaUpload;\nuse Gcego\\Media\\MediaCollection;\nuse Gcego\\Media\\MediaManager;\n".($match[1] ?? 'throw new RuntimeException("missing example");');

    return (static function () use ($code, $variables): mixed {
        extract($variables);

        return eval($code);
    })();
}

it('builds the documented fields and collection', function (): void {
    $fields = readmeExample('fields');
    $collection = readmeExample('collection');

    expect($fields)->toHaveCount(4)
        ->and(array_filter($fields, static fn (MediaUpload $field): bool => $field->validate()->errors() !== []))->toBe([])
        ->and($fields[1]->restrictions()->fileLimit())->toBe(10)
        ->and($fields[2]->restrictions()->types())->toBe(['application/pdf'])
        ->and($fields[3]->isRequired())->toBeTrue()
        ->and($collection)->toBeInstanceOf(MediaCollection::class)
        ->and($collection->fileLimit())->toBe(20)
        ->and($collection->variantDefinitions()[0]->variantName())->toBe('thumb');
});

it('attaches and downloads as documented', function (): void {
    $world = world();
    actAs($world['admin']);

    $response = readmeExample('attach', ['lesson' => $world['subjects'][0], 'uploadedFile' => fixtureFile('pdf', 'lesson.pdf')]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename=lesson.pdf')
        ->and(MediaRecord::query()->value('collection'))->toBe('lesson-resources');
});

it('answers range requests on local disks (seeking in audio and video)', function (): void {
    $world = world();
    actAs($world['admin']);
    $media = media()->attach($world['subjects'][0], fixtureFile('pdf', 'ranged.pdf'), 'files');
    $response = asMember($world['admin'])->withHeaders(['Range' => 'bytes=0-9'])->get(media()->url($media, download: true));

    expect($response->getStatusCode())->toBe(206)
        ->and($response->headers->get('Content-Range'))->toStartWith('bytes 0-9/');
});
