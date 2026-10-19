<?php

declare(strict_types=1);

namespace Gcego\Settings\Validation;

use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\MediaManager;
use Gcego\Settings\Contracts\SettingValidator;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingType;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The default SettingValidator:
 *
 * 1. cast each value strictly to its type (SettingCaster);
 * 2. Laravel validation of the typed values: required or nullable, the
 *    type rule, the field's implied rule (email, url), Options, the enum,
 *    and the definition's own rules (rule strings and ValidationRule
 *    classes, resolved from the container);
 * 3. media references: the file must exist in the current tenant and be
 *    viewable by the signed-in user (gcego/media's authorization);
 * 4. dependencies, against the values as they would be after the change
 *    (stored effective values at the same target, overlaid with the
 *    submitted ones): RequiredWhen and Requires.
 *
 * Errors are translated (attribute names are the setting labels).
 */
final readonly class SettingsValidator implements SettingValidator
{
    public function __construct(
        private Container $container,
        private ValidationFactory $validation,
        private SettingsDefinitions $definitions,
        private SettingsReader $reader,
        private SettingCaster $caster = new SettingCaster,
    ) {}

    public function validate(array $values, array $definitions, ScopeContext $context): SettingValidationResult
    {
        $typed = [];
        $errors = [];

        foreach ($values as $key => $value) {
            $definition = $definitions[$key];

            try {
                $typed[$key] = $this->caster->cast($definition->type, is_string($value) && $value === '' && $definition->type !== SettingType::String ? null : $value, $definition->nullable, $definition->enum);
            } catch (InvalidSettingException $exception) {
                $errors[$key][] = (string) __('gcego-settings::messages.cast.'.($exception->reason ?? 'json'), ['attribute' => $definition->label()]);
            }
        }

        $rules = [];
        $attributes = [];
        $data = [];

        foreach ($typed as $key => $value) {
            $definition = $definitions[$key];
            $rules[$key] = $this->rulesFor($definition);
            $attributes[$key] = $definition->label();
            $data[$key] = $this->caster->plain($definition->type, $value);
        }

        if ($rules !== []) {
            $validator = $this->validation->make($data, $rules, [], $attributes);

            foreach ($validator->errors()->toArray() as $key => $messages) {
                foreach ((array) $messages as $message) {
                    if (is_string($message)) {
                        $errors[(string) $key][] = $message;
                    }
                }
            }
        }

        foreach ($typed as $key => $value) {
            if ($definitions[$key]->type === SettingType::Media && is_string($value) && ! isset($errors[$key]) && ! $this->mediaAvailable($value)) {
                $errors[$key][] = (string) __('gcego-settings::messages.validation.media', ['attribute' => $definitions[$key]->label()]);
            }
        }

        foreach ($this->dependencyErrors($typed, $context) as $key => $messages) {
            $errors[$key] = [...($errors[$key] ?? []), ...$messages];
        }

        return new SettingValidationResult(array_diff_key($typed, $errors), array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors));
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(SettingDefinition $definition): array
    {
        $rules = [$definition->required ? 'required' : 'nullable'];
        $rules = [...$rules, ...match ($definition->type) {
            SettingType::String, SettingType::Media => ['string'],
            SettingType::Integer => ['integer'],
            SettingType::Float => ['numeric'],
            SettingType::Boolean => ['boolean'],
            SettingType::Array => ['array'],
            SettingType::Date => ['date_format:Y-m-d'],
            SettingType::DateTime => ['date'],
            SettingType::Enum => $definition->enum === null ? [] : [Rule::enum($definition->enum)],
            SettingType::Json => [],
        }];

        if ($definition->field === 'email') {
            $rules[] = 'email';
        }

        if ($definition->field === 'url') {
            $rules[] = 'url:http,https';
        }

        if ($definition->options !== null) {
            $rules[] = Rule::in(array_map(static fn (int|string $value): string => (string) $value, array_keys($definition->options)));
        }

        foreach ($definition->rules as $rule) {
            $rules[] = class_exists($rule) && is_subclass_of($rule, ValidationRule::class) ? $this->container->make($rule) : $rule;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $typed
     * @return array<string, list<string>>
     */
    private function dependencyErrors(array $typed, ScopeContext $context): array
    {
        $all = $this->definitions->definitions();
        $involved = [];

        foreach ($all as $key => $definition) {
            $dependencies = [...$definition->setting->requires(), ...array_keys($definition->setting->requiredWhen())];

            if ($dependencies !== [] && (array_key_exists($key, $typed) || array_intersect($dependencies, array_keys($typed)) !== [])) {
                $involved[$key] = $definition;
            }
        }

        if ($involved === []) {
            return [];
        }

        $keys = [];

        foreach ($involved as $key => $definition) {
            $keys[$key] = true;

            foreach ([...$definition->setting->requires(), ...array_keys($definition->setting->requiredWhen())] as $dependency) {
                $keys[$dependency] = true;
            }
        }

        $current = $this->reader->resolveMany(array_values(array_intersect_key($all, $keys)), $context);
        $after = static fn (string $key): mixed => array_key_exists($key, $typed) ? $typed[$key] : ($current[$key] ?? null)?->value;
        $errors = [];

        foreach ($involved as $key => $definition) {
            $conditions = $definition->setting->requiredWhen();

            if ($conditions !== [] && self::isEmpty($after($key))) {
                $matches = true;

                foreach ($conditions as $other => $expected) {
                    $matches = $matches && isset($all[$other]) && $this->caster->plain($all[$other]->type, $after($other)) === $expected;
                }

                if ($matches) {
                    $errors[$key][] = (string) __('gcego-settings::messages.validation.required_when', ['attribute' => $definition->label(), 'other' => implode(', ', array_map(static fn (string $other): string => isset($all[$other]) ? $all[$other]->label() : $other, array_keys($conditions)))]);
                }
            }

            if (! self::isEmpty($after($key)) && $after($key) !== false) {
                foreach ($definition->setting->requires() as $required) {
                    $value = $after($required);

                    if (isset($all[$required]) && (self::isEmpty($value) || $value === false)) {
                        $target = array_key_exists($key, $typed) ? $key : $required;
                        $errors[$target][] = (string) __('gcego-settings::messages.validation.requires', ['attribute' => $definition->label(), 'other' => $all[$required]->label()]);
                    }
                }
            }
        }

        return $errors;
    }

    private function mediaAvailable(string $ulid): bool
    {
        if (! $this->container->bound(MediaManager::class)) {
            return false;
        }

        try {
            $media = $this->container->make(MediaManager::class);

            return $media->allows(MediaAbility::View, $media->find($ulid));
        } catch (Throwable) {
            return false;
        }
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
