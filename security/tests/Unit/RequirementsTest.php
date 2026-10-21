<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Operations\TenantPath;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Permissions\PermissionName;
use Gcego\Security\Permissions\Permissions;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\Requirements\TenantMember;
use Gcego\Security\Requirements\VerifiedEmail;
use Gcego\Security\Resources\ResourceAbility;
use Gcego\Security\Resources\ResourceAccess;
use Gcego\Security\Security;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tests\Fixtures\InstitutionTenantResolver;
use Gcego\Security\Tests\Fixtures\Models\Subject;

it('validates permission and ability names', function (string $name, bool $valid): void {
    expect(PermissionName::isValid($name))->toBe($valid);
})->with([
    ['subjects.view', true],
    ['admin-panel.access', true],
    ['institution.members.*', true],
    ['viewAny', true],
    ['', false],
    ['subjects view', false],
    ['subjects.', false],
    ['*', false],
    ["subjects.view'--", false],
]);

it('declares single, multiple and tenant permissions', function (): void {
    expect((new Permission('subjects.view'))->normalize())->toBe(['permission' => 'subjects.view', 'tenant' => false])
        ->and(Permission::inTenant('grades.update')->tenant)->toBeTrue()
        ->and((new Permissions('subjects.view', 'subjects.update'))->names)->toBe(['subjects.view', 'subjects.update'])
        ->and(Permissions::any('a.b', 'c.d')->requiresAny())->toBeTrue()
        ->and((new Permissions('a.b'))->inTenant()->isTenant())->toBeTrue()
        ->and((new Permissions('a.b'))->isTenant())->toBeFalse()
        ->and(fn () => new Permission('not valid'))->toThrow(InvalidPermissionException::class, 'is not a valid permission')
        ->and(fn () => new Permissions)->toThrow(InvalidPermissionException::class, 'at least one')
        ->and(fn () => new Ability('drop table'))->toThrow(InvalidPermissionException::class);
});

it('accepts the Property syntax', function (): void {
    $security = new Security(
        new Property(Security::Permission, 'subjects.view'),
        new Property(Security::AnyPermission, ['reports.view', 'reports.export']),
        new Property(Security::Tenant, true),
        new Property(Security::Verified, true),
        new Property(Security::Guard, 'web'),
        new Property(Security::Ability, 'viewAny'),
        new Property(Security::Model, Subject::class),
    );

    expect($security->normalize()['requirements'])->toBe([
        'authenticated on guard web',
        'tenant member',
        'verified email',
        'tenant permission subjects.view',
        'tenant permissions: any of reports.view, reports.export',
        'ability viewAny on Subject',
    ])
        ->and((new Security(new Property(Security::Public, true)))->requirements[0])->toBeInstanceOf(PublicAccess::class);
});

it('rejects invalid Property syntax', function (array $items, string $message): void {
    expect(fn () => new Security(...$items))->toThrow(InvalidPermissionException::class, $message);
})->with([
    'unknown key' => [[new Property('role', 'admin')], 'unknown property "role"'],
    'nothing' => [[], 'at least one requirement'],
    'duplicate' => [[new Property(Security::Permission, 'a.b'), new Property(Security::Permission, 'c.d')], 'declared twice'],
    'bad flag' => [[new Property(Security::Public, 'yes')], 'public must be true or false'],
    'bad list' => [[new Property(Security::Permissions, [1])], 'permission name or a list'],
    'model without ability' => [[new Property(Security::Model, Subject::class)], 'only meaningful with ability'],
]);

it('describes every requirement safely', function (): void {
    expect((new Authenticated('admin'))->describe())->toBe('authenticated on guard admin')
        ->and((new TenantMember('administrator'))->describe())->toBe('tenant member with role administrator')
        ->and((new VerifiedEmail)->describe())->toBe('verified email')
        ->and((new ResourceAccess(Subject::class, permission: 'subjects'))->describe())->toBe('resource Subject (permissions subjects.*)');
});

it('maps conventional resource abilities', function (): void {
    expect(ResourceAbility::ViewAny->permissionSuffix())->toBe('view_any')
        ->and(ResourceAbility::ForceDelete->permissionSuffix())->toBe('force_delete')
        ->and(ResourceAbility::Update->needsRecord())->toBeTrue()
        ->and(ResourceAbility::Create->needsRecord())->toBeFalse()
        ->and(fn () => new ResourceAccess(stdClass::class))->toThrow(InvalidPermissionException::class, 'not an Eloquent model'); // @phpstan-ignore argument.type
});

it('builds tenant-separated storage paths that cannot escape', function (): void {
    expect(TenantPath::for('institution:7', 'exports/a.csv'))->toBe('gcego/tenants/institution/7/exports/a.csv')
        ->and(TenantPath::for(null, 'a.csv'))->toBe('gcego/shared/a.csv')
        ->and(TenantPath::contains('institution:7', 'gcego/tenants/institution/7/x.csv'))->toBeTrue()
        ->and(TenantPath::contains('institution:7', 'gcego/tenants/institution/8/x.csv'))->toBeFalse()
        ->and(TenantPath::contains('institution:7', 'gcego/tenants/institution/7/../8/x.csv'))->toBeFalse()
        ->and(TenantPath::for('institution:../../etc', 'x'))->toStartWith('gcego/tenants/institution/')
        ->and(TenantPath::for('institution:../../etc', 'x'))->not->toContain('..')
        ->and(fn () => TenantPath::for('institution:7', '../8/a.csv'))->toThrow(InvalidPermissionException::class)
        ->and(fn () => TenantPath::for('institution:7', '/etc/passwd'))->toThrow(InvalidPermissionException::class);
});

it('validates configuration and refuses allow-by-default', function (array $config, string $message): void {
    expect(fn () => SecurityConfig::fromArray($config))->toThrow(MissingAuthorizationContextException::class, $message);
})->with([
    'allow by default' => [['authorization' => ['default' => 'allow']], 'must be "deny"'],
    'status' => [['authorization' => ['denial_status' => 500]], 'must be 403 or 404'],
    'tenancy without resolver' => [['tenancy' => ['enabled' => true]], 'tenancy.resolver must name'],
    'bad resolver' => [['tenancy' => ['enabled' => true, 'resolver' => stdClass::class]], 'does not implement'],
    'bad provider' => [['permissions' => ['provider' => 'acl']], 'permissions.provider must be'],
    'bad models' => [['tenancy' => ['models' => ['App\Missing' => 'institution_id']]], 'tenancy.models'],
    'bad column' => [['tenancy' => ['models' => [Subject::class => 'institution id']]], 'tenancy.models'],
]);

it('reads the shipped configuration with secure defaults', function (): void {
    $config = SecurityConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-security.php');

    expect($config->denialStrategy->value)->toBe(403)
        ->and($config->tenancy)->toBeFalse()
        ->and($config->tenantDenialStrategy->value)->toBe(404)
        ->and($config->audit)->toBeFalse()
        ->and($config->permissionProvider)->toBe('gate')
        ->and(SecurityConfig::fromArray(['tenancy' => ['enabled' => true, 'resolver' => InstitutionTenantResolver::class]])->tenancy)->toBeTrue();
});

it('validates field rules', function (): void {
    expect((new FieldRule('national_id', read: new Permission('students.identity.view'), mask: true))->normalize())->toBe(['field' => 'national_id', 'read' => 'permission students.identity.view', 'write' => null, 'mask' => true])
        ->and(fn () => new FieldRule('name; drop'))->toThrow(InvalidPermissionException::class);
});
