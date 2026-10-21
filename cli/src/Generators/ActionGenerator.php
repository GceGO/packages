<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Illuminate\Support\Str;

/**
 * gcego:make-action PublishSubject [--ability=] [--module=]: a gcego/actions
 * ActionHandler class, and with --module an Action definition in the
 * module's Actions/ folder for a Resource to require. The action requires a
 * policy ability (default: the camelCase name, e.g. "publishSubject") and a
 * confirmation; it is denied until the policy has that method.
 */
final class ActionGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'action';
    }

    public function description(): string
    {
        return 'A record action (gcego/actions): handler class and definition';
    }

    public function options(): array
    {
        return ['ability' => 'Policy ability that authorizes it (default: camelCase name)', 'module' => 'Also write the Action definition into this module', 'title' => 'Label'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $class = $names->className($context->name);
        $name = $names->slug($context->name);
        $ability = $context->string('ability') ?? $names->camel($context->name);

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,99}$/', $ability) !== 1) {
            throw GenerationException::invalid(sprintf('--ability "%s" is not a valid ability.', $ability));
        }

        $directory = $this->kit->paths->directory('support').'/Actions';
        $namespace = $this->kit->namespaces->forDirectory($directory);
        $handler = $namespace.'\\'.$class;
        $files = [$this->file($context, $directory.'/'.$class.'.php', 'action', [
            'namespace' => StubValue::identifier($namespace),
            'class' => StubValue::identifier($class),
            'name' => StubValue::string($name),
            'ability' => StubValue::string($ability),
        ], 'Action handler '.$class, $namespace, $handler)];

        $this->next(sprintf('Authorize it: add public function %s(User $user, Model $record): bool to the model\'s policy (denied until then).', Str::camel($ability)));

        if (($module = $context->string('module')) !== null) {
            $moduleDirectory = $this->moduleDirectory($module);

            if ($class === 'Actions') {
                throw GenerationException::invalid('"Actions" cannot be an action name.');
            }

            $relative = 'Actions/'.$class.'.php';
            $files[] = $this->file($context, $moduleDirectory.'/'.$relative, 'action-definition', [
                'imports' => $this->imports(['Gcego\\Actions\\Action', 'Gcego\\Actions\\Configuration\\Handler', 'Gcego\\Components\\Properties\\Property', $handler]),
                'name' => StubValue::string($name),
                'title' => StubValue::string($context->string('title') ?? $names->title($context->name)),
                'ability' => StubValue::string($ability),
                'handler' => StubValue::identifier($class),
                'relative' => StubValue::string($relative),
            ], 'Action definition "'.$name.'"');
            $this->next(sprintf("Add it to a Resource of module \"%s\": require dirname(__DIR__).'/%s',", $module, $relative));
        } else {
            $this->next(sprintf("Declare it on a Resource or Blueprint: new Action('%s', '%s', new Property(Action::Ability, '%s'), new Property(Action::Record, true), new Property(Action::Confirmation, true), new Handler(%s::class)),", $name, $names->title($context->name), $ability, $class));
        }

        return $files;
    }
}
