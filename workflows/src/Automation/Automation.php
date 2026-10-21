<?php

declare(strict_types=1);

namespace Gcego\Workflows\Automation;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Definitions\WorkflowNode;

/**
 * Trusted automation on entering a state or after a transition:
 *
 *     new Automation('notify-registrar', NotifyRegistrar::class)
 *     new Automation('score', ScoreApplication::class, queue: false)   // after commit, in the request
 *
 * Only handler classes named by definitions run; never names from data or requests.
 */
final class Automation extends WorkflowNode
{
    public const string Handler = 'handler';

    public const string Queue = 'queue';

    /**
     * @return class-string<WorkflowHandler>
     */
    public function handler(): string
    {
        $handler = $this->get(self::Handler);

        return is_string($handler) && is_subclass_of($handler, WorkflowHandler::class) ? $handler : throw new \LogicException('Invalid automation handler.');
    }

    public function queued(): bool
    {
        return $this->get(self::Queue) !== false;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'handler' => self::Handler, 'queue' => self::Queue];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.automation')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name'),
                new PropertyDefinition(self::Handler, Type::string(), required: true, rule: static fn (mixed $value): bool => self::implements($value, WorkflowHandler::class), ruleDescription: 'a class implementing '.WorkflowHandler::class),
                new PropertyDefinition(self::Queue, Type::bool(), default: true),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Handler];
    }
}
