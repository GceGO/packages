<?php

declare(strict_types=1);

namespace Gcego\Testing\Integrations;

use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Testing\GcegoTest;
use Pest\Expectation;

/**
 * Optional Pest expectations over the same testables:
 *
 *   // tests/Pest.php
 *   Gcego\Testing\Integrations\PestIntegration::register();
 *
 *   expect($component)->toBeValidGcegoComponent();
 *   expect($blueprint)->toBeValidGcegoBlueprint();
 *   expect($html)->toBeAccessibleMarkup();
 *   expect($result)->toBeSuccessfulGcegoAction();
 *
 * Pest is not required by the toolkit; calling register() without it is a
 * no-op.
 */
final class PestIntegration
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || ! function_exists('expect') || ! class_exists(Expectation::class)) {
            return;
        }

        self::$registered = true;

        expect()->extend('toBeValidGcegoComponent', function (): Expectation {
            /** @var Expectation<mixed> $this */
            if (! $this->value instanceof ComponentInterface) {
                expect($this->value)->toBeInstanceOf(ComponentInterface::class);
            } else {
                GcegoTest::component($this->value)->assertValid();
            }

            return $this;
        });

        expect()->extend('toBeValidGcegoBlueprint', function (): Expectation {
            /** @var Expectation<mixed> $this */
            if (! $this->value instanceof BlueprintInterface) {
                expect($this->value)->toBeInstanceOf(BlueprintInterface::class);
            } else {
                GcegoTest::blueprint($this->value)->assertValid();
            }

            return $this;
        });

        expect()->extend('toBeAccessibleMarkup', function (array $except = []): Expectation {
            /** @var Expectation<mixed> $this */
            GcegoTest::accessibility(is_string($this->value) ? $this->value : (string) json_encode($this->value))->assertAccessible(array_values(array_filter($except, is_string(...))));

            return $this;
        });

        expect()->extend('toBeSuccessfulGcegoAction', function (): Expectation {
            /** @var Expectation<mixed> $this */
            expect($this->value)->toBeInstanceOf(ActionResult::class);

            if ($this->value instanceof ActionResult) {
                expect($this->value->status)->toBe(ActionStatus::Success, sprintf('The action ended with [%s]: %s', $this->value->status->value, $this->value->message));
            }

            return $this;
        });
    }
}
