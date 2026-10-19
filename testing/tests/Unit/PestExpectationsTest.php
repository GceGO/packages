<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Forms\Fields\TextInput;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;
use PHPUnit\Framework\AssertionFailedError;

it('offers Pest expectations over the same assertions', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['admin'], 1);
    $result = app(ActionManager::class)->execute(new ActionRequest('subjects', 'publish', $world['draft']->id));

    expect(new TextInput('name', 'Name'))->toBeValidGcegoComponent()
        ->and(Definitions::subjects())->toBeValidGcegoBlueprint()
        ->and('<label for="a">A</label><input id="a">')->toBeAccessibleMarkup()
        ->and($result)->toBeSuccessfulGcegoAction();
});

it('fails Pest expectations with the toolkit message', function (): void {
    expect(fn () => expect('<input>')->toBeAccessibleMarkup())->toThrow(AssertionFailedError::class, 'form-label');
});
