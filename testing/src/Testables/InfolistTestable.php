<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\InfolistManager;
use Gcego\Infolists\Resolution\ResolvedInfolist;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * A registered gcego/infolists infolist for one record, as the acting user
 * sees it: the record is looked up in scope and authorized, then each
 * entry's access level decides whether its value is read at all.
 */
final class InfolistTestable extends Testable
{
    private string|int|null $key = null;

    private mixed $loaded = null;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $definition)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);
        $this->loaded = null;

        return $this;
    }

    public function record(string|int|Model $record): self
    {
        $key = $record instanceof Model ? $record->getKey() : $record;
        $this->key = is_int($key) || is_string($key) ? $key : null;
        $this->loaded = null;

        return $this;
    }

    public function assertHasEntry(string $path): self
    {
        $paths = $this->resolved()->schema->paths();

        return $this->check(in_array($path, $paths, true), sprintf('assertHasEntry(%s)', $this->export($path)), sprintf('Expected the infolist to declare the entry [%s].', $path), ['entry' => $path], ['entries' => $paths]);
    }

    public function assertEntryLabel(string $path, string $label): self
    {
        $actual = $this->entry($path)?->label();

        return $this->check($actual === $label, sprintf('assertEntryLabel(%s, %s)', $this->export($path), $this->export($label)), sprintf('Expected the entry [%s] to be labelled [%s].', $path, $label), ['label' => $label], ['label' => $actual]);
    }

    /**
     * The formatted text the acting user is shown for the entry. Fails when
     * the entry is hidden or redacted for them.
     */
    public function assertValue(string $path, ?string $text): self
    {
        $level = $this->level($path);
        $actual = $level->canRead() && $level !== FieldAccess::Redacted ? $this->text($path) : '('.strtolower($level->name).')';

        return $this->check($actual === $text, sprintf('assertValue(%s, %s)', $this->export($path), $this->export($text)), sprintf('Expected the entry [%s] to show the value.', $path), ['text' => $text], ['text' => $actual]);
    }

    /**
     * Shown as a mask: the value is never read.
     */
    public function assertRedacted(string $path): self
    {
        return $this->assertLevel($path, FieldAccess::Redacted);
    }

    /**
     * Not rendered at all for the acting user.
     */
    public function assertEntryHidden(string $path): self
    {
        return $this->assertLevel($path, FieldAccess::Hidden);
    }

    public function assertReadable(string $path): self
    {
        $level = $this->level($path);

        return $this->check($level->canRead() && $level !== FieldAccess::Redacted, sprintf('assertReadable(%s)', $this->export($path)), sprintf('Expected the entry [%s] to be readable.', $path), ['access' => 'Readable'], ['access' => $level->name]);
    }

    /**
     * The record is outside the acting user's scope, forged or missing: 404.
     */
    public function assertRecordNotFound(string|int $key): self
    {
        $exception = $this->caught(fn () => $this->manager()->record($this->resolved(), $key));

        return $this->check($this->statusOf($exception) === 404, sprintf('assertRecordNotFound(%s)', $this->export($key)), sprintf('Expected the record [%s] to be reported as not found.', $key), ['status' => 404], ['status' => $this->statusOf($exception) ?? 200, 'exception' => $this->describe($exception)]);
    }

    public function assertForbidden(): self
    {
        $exception = $this->caught(fn () => $this->load());

        return $this->check($this->statusOf($exception) === 403, 'assertForbidden()', 'Expected the acting user to be refused the infolist.', ['status' => 403], ['status' => $this->statusOf($exception) ?? 200, 'exception' => $this->describe($exception)]);
    }

    public function html(): HtmlTestable
    {
        return new HtmlTestable($this->verifier, $this->manager()->render($this->definition, $this->requireKey())->toHtml(), 'infolist');
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->definition));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the infolist inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'infolist';
    }

    protected function context(): array
    {
        return ['Infolist' => $this->definition, 'Record' => $this->key ?? '(none)', 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function assertLevel(string $path, FieldAccess $expected): self
    {
        $level = $this->level($path);

        return $this->check($level === $expected, sprintf('assert%s(%s)', $expected === FieldAccess::Hidden ? 'EntryHidden' : $expected->name, $this->export($path)), sprintf('Expected the entry [%s] to be %s for the acting user.', $path, strtolower($expected->name)), ['access' => $expected->name], ['access' => $level->name]);
    }

    private function level(string $path): FieldAccess
    {
        return $this->manager()->access($this->resolved()->schema, $this->load())->level($path);
    }

    private function text(string $path): ?string
    {
        $entry = $this->entry($path);

        if ($entry === null) {
            return '(no such entry)';
        }

        $record = $this->load();

        return $entry->text($entry->value($record), $record, $this->manager()->config());
    }

    private function entry(string $path): ?Entry
    {
        return $this->resolved()->schema->entries[$path] ?? null;
    }

    private function load(): mixed
    {
        if ($this->loaded === null) {
            $resolved = $this->resolved();
            $this->manager()->authorize($resolved);
            $this->loaded = $this->manager()->record($resolved, $this->requireKey());
        }

        return $this->loaded;
    }

    private function requireKey(): string|int
    {
        return $this->key ?? throw new LogicException('Call record() before asserting on values.');
    }

    private function resolved(): ResolvedInfolist
    {
        return $this->manager()->resolve($this->definition);
    }

    private function statusOf(?Throwable $exception): ?int
    {
        return match (true) {
            $exception === null => null,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    private function manager(): InfolistManager
    {
        return $this->container->make(InfolistManager::class);
    }
}
