<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Pages\Context\PageContext;
use Gcego\Pages\PageManager;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * A gcego/pages page ("subjects.edit" or "page:subjects-edit"): its
 * registration and route, and opening it as the acting user through
 * PageManager::open(), which runs the production lifecycle (authorization,
 * record lookup in scope) up to Prepared.
 */
final class PageTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $page)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function open(string|int|Model|null $record = null, array $parameters = []): PageContext
    {
        return $this->manager()->open($this->page, $this->key($record), $parameters);
    }

    public function assertRegistered(): self
    {
        return $this->check($this->manager()->has($this->page), 'assertRegistered()', sprintf('Expected the page [%s] to be registered.', $this->page), ['registered' => true], ['registered' => false], 'Pages are discovered from app/GCEGo/Pages or registered in the registry.');
    }

    public function assertRouteName(string $name): self
    {
        $actual = $this->container->make(PageRouteResolver::class)->routeName($this->manager()->identity($this->page));

        return $this->check($actual === $name, sprintf('assertRouteName(%s)', $this->export($name)), 'Expected the page route name.', ['route' => $name], ['route' => $actual]);
    }

    /**
     * @param  array<string, string|int>  $parameters
     */
    public function assertUrl(string $url, string|int|Model|null $record = null, array $parameters = []): self
    {
        $actual = $this->manager()->url($this->page, $this->key($record), $parameters);

        return $this->check($actual === $url, sprintf('assertUrl(%s)', $this->export($url)), 'Expected the page URL.', ['url' => $url], ['url' => $actual]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function assertOpens(string|int|Model|null $record = null, array $parameters = []): self
    {
        $exception = $this->caught(fn () => $this->open($record, $parameters));

        return $this->check($exception === null, 'assertOpens('.$this->export($this->key($record)).')', 'Expected the acting user to open the page.', ['status' => 200], ['status' => $this->statusOf($exception), 'exception' => $this->describe($exception)]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function assertForbidden(string|int|Model|null $record = null, array $parameters = []): self
    {
        return $this->assertStatus(403, $record, $parameters);
    }

    /**
     * Unknown page, missing or out-of-scope record, or concealed denial.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function assertNotFound(string|int|Model|null $record = null, array $parameters = []): self
    {
        return $this->assertStatus(404, $record, $parameters);
    }

    public function assertRequiresAuthentication(): self
    {
        return $this->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function html(string|int|Model|null $record = null, array $parameters = []): HtmlTestable
    {
        return new HtmlTestable($this->verifier, $this->manager()->render($this->page, $this->key($record), $parameters)->toHtml(), 'page');
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->page));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the page inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'page';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['Page' => $this->page, 'User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function assertStatus(int $status, string|int|Model|null $record = null, array $parameters = []): self
    {
        $exception = $this->caught(fn () => $this->open($record, $parameters));
        $actual = $this->statusOf($exception);

        return $this->check($actual === $status, sprintf('assertStatus(%d, %s)', $status, $this->export($this->key($record))), sprintf('Expected opening the page to answer %d.', $status), ['status' => $status], ['status' => $actual, 'exception' => $this->describe($exception)]);
    }

    private function statusOf(?Throwable $exception): int
    {
        return match (true) {
            $exception === null => 200,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    private function key(string|int|Model|null $record): string|int|null
    {
        $key = $record instanceof Model ? $record->getKey() : $record;

        return is_int($key) || is_string($key) ? $key : null;
    }

    private function manager(): PageManager
    {
        return $this->container->make(PageManager::class);
    }
}
