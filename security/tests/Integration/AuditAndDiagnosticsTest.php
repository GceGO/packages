<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Data\Source;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Registry;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Security\Audit\SecurityEvent;
use Gcego\Security\Contracts\AuditReporter;
use Gcego\Security\Diagnostics\SecurityDiagnostic;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\Tests\Fixtures\Models\Report;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RecordingAuditReporter implements AuditReporter
{
    /**
     * @var list<SecurityEvent>
     */
    public array $events = [];

    public function report(SecurityEvent $event): void
    {
        $this->events[] = $event;
    }
}

beforeEach(function (): void {
    $this->world = world();
});

it('reports denials, tenant denials and sensitive grants when enabled', function (): void {
    $this->rebuildApplication(['gcego-security.audit' => ['enabled' => true, 'sensitive' => ['subjects.export']]]);
    $world = world();
    $reporter = new RecordingAuditReporter;
    app()->instance(AuditReporter::class, $reporter);
    Gate::define('subjects.export', fn (): bool => true);
    $this->actingAs($world['teacher']);

    security()->permission('members.invite');
    security()->authorize('subjects.export');
    try {
        security()->enterTenant((string) $world['south']->id);
    } catch (TenantAccessException) {
    }

    expect(array_map(fn (SecurityEvent $event): string => $event->type->value, $reporter->events))->toBe(['security.denied', 'security.sensitive_allowed', 'security.tenant_denied'])
        ->and($reporter->events[0]->toArray())->toMatchArray(['ability' => 'members.invite', 'reason' => 'missing_permission', 'user' => $world['teacher']->id])
        ->and((string) json_encode(array_map(fn (SecurityEvent $event): array => $event->toArray(), $reporter->events)))->not->toContain('Teacher');
});

it('writes structured events to the configured log channel without personal data', function (): void {
    $this->rebuildApplication(['gcego-security.audit' => ['enabled' => true]]);
    $world = world();
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'security.denied' && $context['user'] === $world['teacher']->id && ! in_array('Teacher', $context, true));
    $this->actingAs($world['teacher']);

    security()->permission('members.invite');
});

it('reports nothing when auditing is disabled', function (): void {
    $reporter = new RecordingAuditReporter;
    app()->instance(AuditReporter::class, $reporter);
    $this->actingAs($this->world['teacher']);
    security()->permission('members.invite');

    expect($reporter->events)->toBe([]);
});

it('finds unprotected definitions, unguarded sources and Gate::before rules', function (): void {
    app(Registry::class)->register(new Blueprint('protected', new Permission('subjects.view'), new Source(Subject::class)));
    app(Registry::class)->register(new Blueprint('forgotten'));
    app(Registry::class)->register(new Blueprint('reports', new PublicAccess, new Source(Report::class)));
    Gate::before(fn (): ?bool => null);

    $report = security()->inspect();
    $messages = array_map(fn (SecurityDiagnostic $diagnostic): string => $diagnostic->level.': '.$diagnostic->message, $report->diagnostics);

    expect($report->isHealthy())->toBeFalse()
        ->and($messages)->toContain('error: blueprint:forgotten declares no authorization requirement: it is denied to everyone. Add a Permission, ResourceAccess, Ability or PublicAccess node.')
        ->and($messages)->toContain('ok: blueprint:protected is protected by: permission subjects.view.')
        ->and($messages)->toContain('warning: The Report source in blueprint:reports has no policy and no requirement: every read is denied.')
        ->and(implode(' ', $messages))->toContain('Gate::before rule(s) are registered')
        ->and($report->check('integration'))->each(fn ($diagnostic) => $diagnostic->level->toBe('ok'))
        ->and($report->toArray())->toHaveKeys(['healthy', 'diagnostics']);
});

it('warns when another authorizer replaces the security integration', function (): void {
    app()->instance(RenderAuthorizer::class, new class implements RenderAuthorizer
    {
        public function authorize(DefinitionDescriptor $descriptor): void {}
    });

    expect(security()->inspect()->check('integration')[0]->level)->toBe('warning');
});
