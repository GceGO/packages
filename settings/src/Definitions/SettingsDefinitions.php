<?php

declare(strict_types=1);

namespace Gcego\Settings\Definitions;

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingNotFoundException;
use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Registry\SettingsKind;
use Gcego\Settings\Registry\SettingsPageKind;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\SettingsPage;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingType;
use Throwable;

/**
 * The registered settings, groups and pages (from gcego/registry), indexed
 * once per process on first use (never while the application registers
 * its services). Definitions are immutable code, so the index is safe to
 * keep in long-running workers; flush() rebuilds it after a runtime
 * registration.
 *
 * Every group without an explicit SettingsPage gets a default page named
 * after it, editing the first scope its settings allow (global, tenant,
 * panel, then user).
 */
final class SettingsDefinitions
{
    /** @var array<string, SettingsGroup>|null */
    private ?array $groups = null;

    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    /** @var array<string, SettingsPage>|null */
    private ?array $pages = null;

    /** @var list<string>|null */
    private ?array $problems = null;

    public function __construct(private readonly RegistryInterface $registry, private readonly SettingsConfig $config) {}

    /**
     * Groups by navigation order (Sort, then name).
     *
     * @return list<SettingsGroup>
     */
    public function groups(): array
    {
        return array_values($this->index()['groups']);
    }

    public function hasGroup(string $name): bool
    {
        return isset($this->index()['groups'][$name]);
    }

    /**
     * @throws SettingNotFoundException
     */
    public function group(string $name): SettingsGroup
    {
        return $this->index()['groups'][$name] ?? throw SettingNotFoundException::group($name);
    }

    public function has(string $key): bool
    {
        return Setting::validKey($key) && isset($this->index()['definitions'][$key]);
    }

    /**
     * @throws SettingNotFoundException
     */
    public function definition(string $key): SettingDefinition
    {
        return ($this->has($key) ? $this->index()['definitions'][$key] : null) ?? throw SettingNotFoundException::setting($key);
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return $this->index()['definitions'];
    }

    /**
     * The settings of a group, in declaration order.
     *
     * @return list<SettingDefinition>
     */
    public function inGroup(string $group): array
    {
        $keys = $this->group($group)->keys();

        return array_values(array_map(fn (string $key): SettingDefinition => $this->index()['definitions'][$key], $keys));
    }

    /**
     * Explicit pages and the default pages of groups without one, by navigation order.
     *
     * @return list<SettingsPage>
     */
    public function pages(): array
    {
        return array_values($this->index()['pages']);
    }

    public function hasPage(string $name): bool
    {
        return SettingsPage::validName($name) && isset($this->index()['pages'][$name]);
    }

    /**
     * @throws SettingNotFoundException
     */
    public function page(string $name): SettingsPage
    {
        return ($this->hasPage($name) ? $this->index()['pages'][$name] : null) ?? throw SettingNotFoundException::page($name);
    }

    /**
     * Problems across groups and pages (empty when the definitions are consistent). Never throws.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if ($this->problems === null) {
            try {
                $this->build();
            } catch (Throwable $exception) {
                $this->problems = [$exception->getMessage()];
            }
        }

        return $this->problems ?? [];
    }

    /**
     * Register a group or page at runtime (tests, packages); discovery is the usual way.
     */
    public function register(SettingsGroup|SettingsPage $definition, ?string $scope = null): DefinitionDescriptor
    {
        $descriptor = $this->registry->register($definition, $scope);
        $this->flush();

        return $descriptor;
    }

    public function flush(): void
    {
        $this->groups = $this->definitions = $this->pages = $this->problems = null;
    }

    /**
     * @return array{groups: array<string, SettingsGroup>, definitions: array<string, SettingDefinition>, pages: array<string, SettingsPage>}
     *
     * @throws InvalidSettingException when the definitions are inconsistent
     */
    private function index(): array
    {
        if ($this->groups === null || $this->definitions === null || $this->pages === null) {
            $this->build();
        }

        SettingsDefinitionValidator::assertNone($this->problems ?? []);

        return ['groups' => $this->groups ?? [], 'definitions' => $this->definitions ?? [], 'pages' => $this->pages ?? []];
    }

    private function build(): void
    {
        $groups = [];

        foreach ($this->registry->descriptors(SettingsKind::Id) as $descriptor) {
            $group = $this->registry->get($descriptor->key(), SettingsGroup::class);

            if ($group instanceof SettingsGroup) {
                $groups[$group->groupName()] = $group;
            }
        }

        uasort($groups, static fn (SettingsGroup $a, SettingsGroup $b): int => [$a->sort(), $a->groupName()] <=> [$b->sort(), $b->groupName()]);

        $definitions = [];

        foreach ($groups as $group) {
            foreach ($group->children() as $child) {
                if ($child instanceof Setting) {
                    $definitions[$child->key()] ??= $this->normalize($child, $group, null);
                } elseif ($child instanceof SettingsSection) {
                    foreach ($child->settings() as $setting) {
                        $definitions[$setting->key()] ??= $this->normalize($setting, $group, $child);
                    }
                }
            }
        }

        $explicit = [];

        foreach ($this->registry->descriptors(SettingsPageKind::Id) as $descriptor) {
            $page = $this->registry->get($descriptor->key(), SettingsPage::class);

            // A page of a scope the configuration disables is not shown (no error).
            if ($page instanceof SettingsPage && $this->config->enables($page->scope())) {
                $explicit[] = $page;
            }
        }

        $this->problems = SettingsDefinitionValidator::problemsAcross(array_values($groups), $explicit, $definitions);
        $pages = [];

        foreach ($explicit as $page) {
            $pages[$page->pageName()] = $page;
        }

        $shown = array_map(static fn (SettingsPage $page): string => $page->group(), $explicit);

        foreach ($groups as $group) {
            if (in_array($group->groupName(), $shown, true) || $group->isHidden() || isset($pages[$group->groupName()])) {
                continue;
            }

            $scope = $this->defaultScope(array_filter($definitions, static fn (SettingDefinition $definition): bool => $definition->group === $group->groupName()));

            if ($scope !== null) {
                $pages[$group->groupName()] = new SettingsPage(name: $group->groupName(), title: $group->title(), description: $group->description(), group: $group->groupName(), scope: $scope, icon: $group->icon(), sort: $group->sort());
            }
        }

        uasort($pages, static fn (SettingsPage $a, SettingsPage $b): int => [$a->sort(), $a->title()] <=> [$b->sort(), $b->title()]);

        [$this->groups, $this->definitions, $this->pages] = [$groups, $definitions, $pages];
    }

    private function normalize(Setting $setting, SettingsGroup $group, ?SettingsSection $section): SettingDefinition
    {
        $type = $setting->settingType();
        $scopes = array_values(array_filter($setting->declaredScopes() ?? $group->scopes(), fn (SettingScope $scope): bool => $this->config->enables($scope)));
        $order = $setting->declaredPrecedence() ?? $this->config->precedence;
        $precedence = array_values(array_filter($order, static fn (SettingScope $scope): bool => in_array($scope, $scopes, true)));
        $default = null;

        if ($setting->hasDefault() && $setting->rawDefault() !== null) {
            try {
                $default = (new SettingCaster)->cast($type, $setting->rawDefault(), $setting->isNullable(), $setting->enumClass());
            } catch (InvalidSettingException) {
                $default = null;
            }
        } elseif (! $setting->hasDefault() && $type === SettingType::Boolean && ! $setting->isNullable()) {
            $default = false;
        }

        return new SettingDefinition(
            setting: $setting,
            key: $setting->key(),
            group: $group->groupName(),
            section: $section?->nodeName(),
            type: $type,
            nullable: $setting->isNullable(),
            required: $setting->isRequired(),
            hasDefault: $setting->hasDefault(),
            default: $default,
            enum: $setting->enumClass(),
            options: $setting->options(),
            rules: $setting->rules(),
            scopes: $scopes,
            precedence: $precedence,
            sensitive: $setting->isSensitive(),
            field: $setting->field(),
            approval: $setting->approval() ?? $group->approval(),
            requirements: [...$group->requirements(), ...($section?->requirements() ?? []), ...$setting->requirements()],
            hidden: $setting->isHidden(),
        );
    }

    /**
     * @param  array<string, SettingDefinition>  $definitions
     */
    private function defaultScope(array $definitions): ?SettingScope
    {
        foreach ([SettingScope::Global, SettingScope::Tenant, SettingScope::Panel, SettingScope::User] as $scope) {
            foreach ($definitions as $definition) {
                if ($definition->allows($scope) && ! $definition->hidden) {
                    return $scope;
                }
            }
        }

        return null;
    }
}
