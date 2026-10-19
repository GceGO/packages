<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Unit;

use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Testing\Tests\Fixtures\Definitions;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Testing\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The PHPUnit API: the same assertions as a class-based test with data
 * providers, no Pest involved.
 */
final class PhpUnitStyleTest extends TestCase
{
    public function test_component_and_blueprint_assertions(): void
    {
        $this->assertGcegoComponentValid(new TextInput('name', 'Name'));
        $this->assertGcegoBlueprintValid(Definitions::subjects());
        $this->assertGcegoBlueprintHas(Definitions::subjects(), 'table');
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function abilities(): iterable
    {
        yield 'view' => ['view', true];
        yield 'delete' => ['delete', false];
    }

    #[DataProvider('abilities')]
    public function test_security_assertions_with_a_data_provider(string $ability, bool $allowed): void
    {
        $world = \world();
        $this->actingAsGcego($world['student'], 1);

        $allowed ? $this->assertGcegoCan($ability, $world['algebra']) : $this->assertGcegoCannot($ability, $world['algebra']);
    }

    public function test_tenant_isolation_assertions(): void
    {
        $world = \world();
        $this->actingAsGcego($world['admin'], 1);

        $this->assertGcegoCannotAccessTenant(2);
        $this->assertGcegoTenantIsolated(new Source(Subject::class), [$world['southern']]);
    }

    public function test_failures_are_phpunit_failures(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('GCEGO COMPONENT ASSERTION FAILED');

        $this->assertGcegoComponentInvalid(new TextInput('name', 'Name'));
    }

    public function test_fixtures_and_accessibility_helpers(): void
    {
        $user = $this->createGcegoUser(['name' => 'Fixture']);

        $this->assertSame('Fixture', $user->getAttribute('name'));
        $this->assertTrue(is_dir($this->gcegoWorkspace()->root));
        $this->assertGcegoAccessible('<label for="a">A</label><input id="a">');
        $this->assertInstanceOf(Form::class, $this->gcegoDefinitions()->form());
    }
}
