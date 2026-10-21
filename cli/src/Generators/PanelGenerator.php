<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Gcego\Components\Component;
use Gcego\Panels\Panel;
use Illuminate\Routing\Router;

/**
 * gcego:make-panel Staff [--path=] [--domain=] [--guard=] [--tenant=]
 * [--layout=] [--theme=] [--ability=] [--module=*] [--page=*]: a gcego/panels
 * panel. It requires the Gate ability "access-<name>" (or --ability), which
 * this command never defines: nobody can enter until the application does.
 */
final class PanelGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'panel';
    }

    public function description(): string
    {
        return 'A panel (gcego/panels), deny-by-default behind a Gate ability';
    }

    public function options(): array
    {
        return ['title' => 'Title', 'path' => 'URL path (default: the name)', 'domain' => 'Domain', 'guard' => 'Authentication guard', 'tenant' => 'optional or required', 'layout' => 'sidebar, topbar or compact', 'theme' => 'gcego/chroma theme', 'ability' => 'Gate ability to enter (default: access-<name>)', 'module' => 'Modules to list (comma-separated)', 'page' => 'Pages to list (comma-separated)'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $name = $names->slug($context->name);
        $path = trim($context->string('path') ?? $name, '/');

        $this->assertAvailable('panel:'.$name, $this->kit->paths->directory('panel').'/'.$name.'.php');

        if (preg_match(Panel::PathPattern, $path) !== 1) {
            throw GenerationException::invalid(sprintf('--path "%s" is not a valid panel path.', $path));
        }

        $this->assertFreePath($path, $context->string('domain'));
        $properties = '';

        if (($domain = $context->string('domain')) !== null) {
            if (preg_match(Panel::DomainPattern, $domain) !== 1) {
                throw GenerationException::invalid(sprintf('--domain "%s" is not a valid domain.', $domain));
            }

            $properties .= sprintf("\n    new Property(Panel::Domain, '%s'),", $domain);
        }

        if (($guard = $context->string('guard')) !== null) {
            if (! is_array($this->kit->config->get('auth.guards.'.$guard))) {
                throw GenerationException::missing(sprintf('The guard "%s" is not defined in config/auth.php.', $guard));
            }

            $properties .= sprintf("\n    new Property(Panel::Guard, '%s'),", $guard);
        }

        if (($tenant = $context->string('tenant')) !== null) {
            if (! in_array($tenant, ['optional', 'required'], true)) {
                throw GenerationException::invalid('--tenant must be "optional" or "required".');
            }

            $properties .= sprintf("\n    new Property(Panel::Tenant, '%s'),", $tenant);
        }

        if (($layout = $context->string('layout')) !== null) {
            if (! in_array($layout, Panel::Layouts, true)) {
                throw GenerationException::invalid(sprintf('--layout must be one of %s.', implode(', ', Panel::Layouts)));
            }

            $properties .= sprintf("\n    new Property(Panel::Layout, '%s'),", $layout);
        }

        if (($theme = $context->string('theme')) !== null) {
            if (! $this->kit->container->make(ThemeRegistry::class)->has($theme)) {
                throw GenerationException::missing(sprintf('The theme "%s" is not registered (gcego/chroma).', $theme));
            }

            $properties .= sprintf("\n    new Property(Panel::Theme, '%s'),", $theme);
        }

        $ability = $context->string('ability') ?? 'access-'.$name;
        $requirement = $this->requirement(new GenerationContext($context->name, ['ability' => $ability]));
        $imports = ['Gcego\\Components\\Properties\\Property', 'Gcego\\Panels\\Branding\\Branding', 'Gcego\\Panels\\Panel', $requirement['import']];
        $references = '';

        foreach ($context->list('module') as $module) {
            $this->moduleDirectory($module);
            $imports[] = 'Gcego\\Panels\\Definitions\\ModuleReference';
            $references .= sprintf("\n    new ModuleReference('%s'),", $module);
        }

        foreach ($context->list('page') as $page) {
            if (! $this->kit->catalog->has('page:'.$names->identifier($page, 'page'))) {
                throw GenerationException::missing(sprintf('The page "%s" does not exist.', $page));
            }

            $imports[] = 'Gcego\\Panels\\Definitions\\PageReference';
            $references .= sprintf("\n    new PageReference('%s'),", $page);
        }

        $this->next(sprintf("Decide who may enter: Gate::define('%s', fn (\$user) => ...) — nobody can until you do. No users or roles were created.", $ability));
        $this->next(sprintf('Open /%s after signing in; check it with php artisan gcego:inspect panel:%s.', $path, $name));

        return [$this->file($context, $this->kit->paths->directory('panel').'/'.$name.'.php', 'panel', [
            'imports' => $this->imports($imports),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $names->title($context->name)),
            'path' => StubValue::string($path),
            'properties' => StubValue::code($properties),
            'requirement' => StubValue::code($requirement['code']),
            'references' => StubValue::code($references),
            'access_note' => StubValue::code(sprintf('whoever the "%s" Gate ability allows (deny by default).', $ability)),
        ], 'Panel "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['panel', $this->kit->names->slug($context->name)];
    }

    private function assertFreePath(string $path, ?string $domain): void
    {
        foreach ($this->kit->catalog->descriptors('panel') as $descriptor) {
            $panel = $this->kit->catalog->definition($descriptor->key());

            $existing = $panel instanceof Component ? $panel->get(Panel::Path) ?? $panel->name() : null;

            if ($panel instanceof Component && is_string($existing) && trim($existing, '/') === $path && $panel->get(Panel::Domain) === $domain) {
                throw GenerationException::invalid(sprintf('The path "/%s" is already used by panel "%s".', $path, $descriptor->name()));
            }
        }

        foreach ($this->kit->container->make(Router::class)->getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'gcego.') && ($route->uri() === $path || str_starts_with($route->uri(), $path.'/')) && ($domain === null || $route->getDomain() === $domain)) {
                throw GenerationException::invalid(sprintf('The application route "/%s" already uses the path "/%s".', $route->uri(), $path));
            }
        }
    }
}
