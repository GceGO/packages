<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Testing\Assertions\Verifier;
use UnitEnum;

/**
 * Assertions about a gcego/blueprints Blueprint: validity (components and
 * composition together), the roles it fills, its parts and its identity.
 */
final class BlueprintTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly BlueprintInterface $blueprint)
    {
        parent::__construct($verifier);
    }

    public function blueprint(): BlueprintInterface
    {
        return $this->blueprint;
    }

    public function assertValid(): self
    {
        $errors = (new BlueprintValidator)->validate($this->blueprint)->errors();

        return $this->check($errors === [], 'assertValid()', 'Expected the blueprint to be valid.', ['errors' => 0], ['errors' => array_map(static fn (BlueprintValidationError $error): string => $error->format(), $errors)]);
    }

    /**
     * At least one error; narrow it with a code such as
     * "blueprint.missing_required_role" or "component.invalid_value".
     */
    public function assertInvalid(UnitEnum|string|null $code = null, ?string $role = null): self
    {
        $code = $code instanceof \BackedEnum ? (string) $code->value : ($code instanceof UnitEnum ? $code->name : $code);
        $errors = (new BlueprintValidator)->validate($this->blueprint)->errors();
        $matching = array_filter($errors, static fn (BlueprintValidationError $error): bool => ($code === null || $error->code === $code) && ($role === null || $error->role === $role));

        return $this->check($matching !== [], sprintf('assertInvalid(%s, %s)', $this->export($code), $this->export($role)), 'Expected the blueprint to report a validation error'.($code !== null ? " [$code]" : '').($role !== null ? " for the role [$role]" : '').'.', ['code' => $code, 'role' => $role], ['errors' => array_map(static fn (BlueprintValidationError $error): string => $error->code.($error->role !== null ? ' ('.$error->role.')' : ''), $errors)]);
    }

    /**
     * The blueprint has a part in the role ("table", "form", "source"…).
     */
    public function assertHasComponent(string $role): self
    {
        return $this->check($this->blueprint->hasPart($role), sprintf('assertHasComponent(%s)', $this->export($role)), sprintf('Expected the blueprint to have a [%s] part.', $role), ['role' => $role], ['roles' => $this->blueprint->roles()]);
    }

    public function assertMissingComponent(string $role): self
    {
        return $this->check(! $this->blueprint->hasPart($role), sprintf('assertMissingComponent(%s)', $this->export($role)), sprintf('Expected the blueprint not to have a [%s] part.', $role), ['role' => 'absent'], ['roles' => $this->blueprint->roles()]);
    }

    public function assertPartCount(string $role, int $count): self
    {
        $actual = count($this->blueprint->parts($role));

        return $this->check($actual === $count, sprintf('assertPartCount(%s, %d)', $this->export($role), $count), sprintf('Expected %d [%s] part(s).', $count, $role), ['parts' => $count], ['parts' => $actual]);
    }

    /**
     * @param  list<string>  $roles
     */
    public function assertRoles(array $roles): self
    {
        $actual = $this->blueprint->roles();
        sort($roles);
        sort($actual);

        return $this->check($actual === $roles, 'assertRoles('.$this->export($roles).')', 'Expected the blueprint to fill exactly these roles.', ['roles' => $roles], ['roles' => $actual]);
    }

    public function assertName(string $name): self
    {
        return $this->check($this->blueprint->name() === $name, sprintf('assertName(%s)', $this->export($name)), sprintf('Expected the blueprint name [%s].', $name), ['name' => $name], ['name' => $this->blueprint->name()]);
    }

    public function assertTitle(string $title): self
    {
        $actual = $this->blueprint->metadata()->title;

        return $this->check($actual === $title, sprintf('assertTitle(%s)', $this->export($title)), sprintf('Expected the blueprint title [%s].', $title), ['title' => $title], ['title' => $actual]);
    }

    /**
     * The identity is derived from the definition alone: the same key every
     * time, and equal to $key when given (e.g. "blueprint:subjects").
     */
    public function assertStableIdentity(?string $key = null): self
    {
        $exception = $this->caught(fn () => $this->blueprint->identity());

        if ($exception instanceof InvalidBlueprintException) {
            return $this->check(false, 'assertStableIdentity()', 'Expected the blueprint to have an identity.', [], ['exception' => $this->describe($exception)], 'Give the blueprint a valid name.');
        }

        $first = $this->blueprint->identity();
        $second = $this->blueprint->identity();

        return $this->check($first->equals($second) && ($key === null || $first->key() === $key), sprintf('assertStableIdentity(%s)', $this->export($key)), 'Expected a stable blueprint identity.', ['key' => $key ?? $first->key()], ['key' => $first->key(), 'again' => $second->key()]);
    }

    /**
     * A ComponentTestable for the single part in a role.
     */
    public function part(string $role): ComponentTestable
    {
        $this->assertHasComponent($role);

        return new ComponentTestable($this->verifier, $this->blueprint->parts($role)->first() ?? $this->blueprint);
    }

    public function component(): ComponentTestable
    {
        return new ComponentTestable($this->verifier, $this->blueprint);
    }

    protected function subject(): string
    {
        return 'blueprint';
    }

    protected function context(): array
    {
        return ['Blueprint' => $this->blueprint->name(), 'Class' => $this->blueprint::class];
    }
}
