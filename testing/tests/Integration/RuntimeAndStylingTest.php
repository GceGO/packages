<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Rendering\Exceptions\RendererNotFoundException;
use Gcego\Rendering\Renderers\ContainerRenderer;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Illuminate\Auth\Access\AuthorizationException;

final class UnrenderableWidget extends Component {}

it('renders through the runtime and asserts the markup', function (): void {
    GcegoTest::runtime()
        ->render(new Component(new Property(Component::Label, 'Summary')))
        ->assertSeeText('Summary')
        ->assertAccessible();
});

it('renders a registered definition through the authorized path', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['admin'], 1);

    GcegoTest::runtime()->renderAuthorized('blueprint:subjects')->assertElement('section');
    GcegoTest::runtime()->assertHealthy('blueprint:subjects');
});

it('refuses the authorized render to a guest', function (): void {
    expect(fn () => GcegoTest::runtime()->renderAuthorized('blueprint:subjects'))->toThrow(AuthorizationException::class);
});

it('asserts renderer resolution and missing renderers', function (): void {
    GcegoTest::runtime()
        ->assertRendererFor(new Component, ContainerRenderer::class)
        ->assertMissingRenderer(new Component(new UnrenderableWidget), UnrenderableWidget::class)
        ->assertRenderFails(new UnrenderableWidget, RendererNotFoundException::class, 'No renderer is registered');
});

it('asserts the classes styling resolves, independent of order', function (): void {
    $card = new Component(new Property(Component::Label, 'Card'), new Tailwind('p-8', new Slot('title', 'text-2xl')));

    GcegoTest::styling($card)
        ->assertHasClasses('root', 'p-8')
        ->assertLacksClasses('root', 'p-4')
        ->assertHasClasses('title', ['text-2xl'])
        ->assertHealthy();

    expect(failure(fn () => GcegoTest::styling($card)->assertHasClasses('root', 'rounded-none bg-red-500')))->toContain('missing:');
});

it('lets instance classes replace conflicting preset classes', function (): void {
    $plain = GcegoTest::styling(new Component)->classes('root');
    $override = new Component(new Tailwind(Slot::replace('root', 'grid gap-2')));

    GcegoTest::styling($override)->assertClasses('root', 'grid gap-2');
    expect($plain)->not->toBe(['grid', 'gap-2']);
});

it('refuses unknown slots under strict styling', function (): void {
    GcegoTest::styling(Definitions::subjects())->assertUnknownSlot('no-such-slot');
});

it('builds a source from a model for styling-free data tests', function (): void {
    expect(GcegoTest::fixtures()->definitions()->source(Subject::class))->toBeInstanceOf(Source::class);
});
