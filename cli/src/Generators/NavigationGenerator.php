<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Illuminate\Routing\Router;

/**
 * gcego:make-navigation AcademicNavigation [--item="Subjects=gcego.admin.subjects.index"]*
 * [--permission=]: a gcego/navigation menu in app/GCEGo/Navigation. Items
 * link to route names (checked) or paths ("/admin"); gcego/navigation shows
 * them to signed-in users, and --permission adds a permission per item.
 */
final class NavigationGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'navigation';
    }

    public function description(): string
    {
        return 'A navigation menu (gcego/navigation) with a group of items';
    }

    public function options(): array
    {
        return ['item' => 'Label=route.name or Label=/path (repeatable, comma-separated)', 'permission' => 'Permission required to see each item', 'title' => 'Group label'];
    }

    protected function files(GenerationContext $context): array
    {
        $name = $this->kit->names->slug($context->name);
        $items = $context->list('item') ?: ['Home=/'];
        $permission = $context->string('permission');
        $code = '';

        $this->assertAvailable('navigation:'.$name, $this->kit->paths->directory('navigation').'/'.$name.'.php');

        if ($permission !== null && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $permission) !== 1) {
            throw GenerationException::invalid(sprintf('--permission "%s" is not a valid permission name.', $permission));
        }

        $router = $this->kit->container->make(Router::class);

        foreach ($items as $item) {
            if (preg_match('#^([^=]{1,80})=(/[A-Za-z0-9/_.{}-]*|[A-Za-z][A-Za-z0-9_.-]*)$#', $item, $match) !== 1) {
                throw GenerationException::invalid(sprintf('--item "%s" must be Label=route.name or Label=/path.', $item));
            }

            [, $label, $link] = $match;

            if (! str_starts_with($link, '/') && ! $router->has($link)) {
                $this->warn(sprintf('The route "%s" does not exist (yet); the item will not render until it does.', $link));
            }

            $code .= sprintf("\n        new Item('%s', '%s'%s),", StubValue::string(trim($label))->rendered, $link, $permission === null ? '' : sprintf(", new Property(Item::Permission, '%s')", $permission));
        }

        $imports = ['Gcego\\Navigation\\Components\\Group', 'Gcego\\Navigation\\Components\\Item', 'Gcego\\Navigation\\Navigation'];

        if ($permission !== null) {
            $imports[] = 'Gcego\\Components\\Properties\\Property';
        }

        $this->next(sprintf("Use it in a panel: new Property(Panel::Navigation, '%s'), or render navigation:%s.", $name, $name));

        return [$this->file($context, $this->kit->paths->directory('navigation').'/'.$name.'.php', 'navigation', [
            'imports' => $this->imports($imports, $code),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $this->kit->names->title($context->name)),
            'items' => StubValue::code($code),
        ], 'Navigation "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['component', 'navigation:'.$this->kit->names->slug($context->name)];
    }
}
