<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Registry\Diagnostics\DiagnosticLevel;
use Gcego\Registry\Diagnostics\RegistryDiagnostic;
use Gcego\Registry\Diagnostics\RegistryInspector;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Registration\RegistrationFailure;
use Gcego\Registry\Registry;
use Gcego\Testing\Assertions\Verifier;
use Illuminate\Contracts\Container\Container;

/**
 * Assertions about the application's gcego/registry: what is registered
 * and discovered, how identifiers resolve, and what is reported.
 *
 * Only register() changes the registry, and only when you call it.
 */
final class RegistryTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container)
    {
        parent::__construct($verifier);
    }

    public function registry(): Registry
    {
        return $this->container->make(Registry::class);
    }

    /**
     * Registers a definition at runtime, exactly as an application would.
     */
    public function register(object $definition, ?string $scope = null): self
    {
        $this->registry()->register($definition, $scope);

        return $this;
    }

    public function assertHas(string $identifier): self
    {
        return $this->check($this->registry()->has($identifier), sprintf('assertHas(%s)', $this->export($identifier)), sprintf('Expected the registry to contain [%s].', $identifier), ['identifier' => $identifier], ['similar' => $this->similar($identifier)], 'Register the definition, or check the discovery roots in gcego-registry.discovery.roots.');
    }

    public function assertMissing(string $identifier): self
    {
        return $this->check(! $this->registry()->has($identifier), sprintf('assertMissing(%s)', $this->export($identifier)), sprintf('Expected the registry not to contain [%s].', $identifier), ['identifier' => 'absent'], ['identifier' => 'registered']);
    }

    /**
     * The identifier resolves to an object (of $class when given), with the
     * kind's validation applied, exactly as production code resolves it.
     *
     * @param  class-string|null  $class
     */
    public function assertResolves(string $identifier, ?string $class = null): self
    {
        $resolved = null;
        $exception = $this->caught(function () use ($identifier, $class, &$resolved): void {
            $resolved = $this->registry()->get($identifier, $class);
        });

        return $this->check($exception === null && is_object($resolved), sprintf('assertResolves(%s, %s)', $this->export($identifier), $this->export($class)), sprintf('Expected [%s] to resolve%s.', $identifier, $class !== null ? " to $class" : ''), ['resolves' => $class ?? 'an object'], ['exception' => $this->describe($exception)]);
    }

    /**
     * Resolving fails with DefinitionNotFoundException; with $suggestion,
     * the message also suggests that identity.
     */
    public function assertNotFound(string $identifier, ?string $suggestion = null): self
    {
        $exception = $this->caught(fn () => $this->registry()->descriptor($identifier));
        $passed = $exception instanceof DefinitionNotFoundException && ($suggestion === null || str_contains($exception->getMessage(), $suggestion));

        return $this->check($passed, sprintf('assertNotFound(%s, %s)', $this->export($identifier), $this->export($suggestion)), sprintf('Expected [%s] not to be found%s.', $identifier, $suggestion !== null ? " (suggesting $suggestion)" : ''), ['exception' => DefinitionNotFoundException::class], ['exception' => $this->describe($exception)]);
    }

    public function assertKind(string $identifier, string $kind): self
    {
        $descriptor = $this->descriptor($identifier);

        return $this->check($descriptor?->kind() === $kind, sprintf('assertKind(%s, %s)', $this->export($identifier), $this->export($kind)), sprintf('Expected [%s] to be of the kind [%s].', $identifier, $kind), ['kind' => $kind], ['kind' => $descriptor?->kind()]);
    }

    public function assertCount(int $count, ?string $kind = null): self
    {
        $actual = $this->registry()->count($kind);

        return $this->check($actual === $count, sprintf('assertCount(%d, %s)', $count, $this->export($kind)), sprintf('Expected %d definition(s)%s.', $count, $kind !== null ? " of the kind $kind" : ''), ['count' => $count], ['count' => $actual, 'names' => $this->registry()->names($kind)]);
    }

    /**
     * Registering $definition is refused because its identity is taken by a
     * different object. The registry is unchanged when it passes.
     */
    public function assertRejectsDuplicate(object $definition, ?string $scope = null): self
    {
        $exception = $this->caught(fn () => $this->registry()->register($definition, $scope));

        return $this->check($exception instanceof DuplicateDefinitionException, 'assertRejectsDuplicate('.$definition::class.')', 'Expected the registry to refuse a second definition with the same identity.', ['exception' => DuplicateDefinitionException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * Registering $definition is refused by the kind's validation. With
     * $message, one of the reported errors contains it.
     */
    public function assertRejectsInvalid(object $definition, ?string $message = null): self
    {
        $exception = $this->caught(fn () => $this->registry()->register($definition));
        $errors = $exception instanceof InvalidDefinitionException ? $exception->errors() : [];
        $passed = $exception instanceof InvalidDefinitionException && ($message === null || str_contains($exception->getMessage().' '.implode(' ', array_map(static fn (mixed $error): string => is_scalar($error) ? (string) $error : '', $errors)), $message));

        return $this->check($passed, 'assertRejectsInvalid('.$definition::class.')', 'Expected the registry to refuse an invalid definition.', ['exception' => InvalidDefinitionException::class, 'message' => $message], ['exception' => $this->describe($exception)]);
    }

    /**
     * Discovery and registration recorded no failures (non-strict mode
     * collects them instead of throwing).
     */
    public function assertNoFailures(): self
    {
        $failures = array_map(static fn (RegistrationFailure $failure): string => $failure->source.': '.$failure->exception->getMessage(), $this->registry()->failures());

        return $this->check($failures === [], 'assertNoFailures()', 'Expected registration to succeed for every discovered definition.', ['failures' => []], ['failures' => $failures]);
    }

    /**
     * The RegistryInspector reports no error-level diagnostic.
     */
    public function assertHealthy(): self
    {
        $report = $this->container->make(RegistryInspector::class)->inspect();
        $errors = array_values(array_map(static fn (RegistryDiagnostic $diagnostic): string => $diagnostic->check.': '.$diagnostic->message, array_filter($report->diagnostics, static fn (RegistryDiagnostic $diagnostic): bool => $diagnostic->level === DiagnosticLevel::Error)));

        return $this->check($report->isHealthy(), 'assertHealthy()', 'Expected the registry inspector to report no errors.', ['errors' => []], ['errors' => $errors]);
    }

    public function assertOrigin(string $origin): self
    {
        $actual = $this->registry()->origin();

        return $this->check($actual === $origin, sprintf('assertOrigin(%s)', $this->export($origin)), sprintf('Expected the index to come from [%s].', $origin), ['origin' => $origin], ['origin' => $actual]);
    }

    protected function subject(): string
    {
        return 'registry';
    }

    protected function context(): array
    {
        return ['Definitions' => $this->registry()->count()];
    }

    private function descriptor(string $identifier): ?DefinitionDescriptor
    {
        try {
            return $this->registry()->descriptor($identifier);
        } catch (DefinitionNotFoundException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function similar(string $identifier): array
    {
        $name = str_contains($identifier, ':') ? substr($identifier, (int) strpos($identifier, ':') + 1) : $identifier;

        return array_values(array_filter(array_map(static fn (DefinitionDescriptor $descriptor): string => $descriptor->key(), $this->registry()->descriptors()), static fn (string $key): bool => levenshtein($name, substr($key, (int) strpos($key, ':') + 1)) <= 2));
    }
}
