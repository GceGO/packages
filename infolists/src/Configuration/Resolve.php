<?php

declare(strict_types=1);

namespace Gcego\Infolists\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Infolists\Contracts\ValueResolver;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Support\RecordEvaluation;

/**
 * A trusted computed value instead of reading the entry's path:
 *
 *     new Number('average', 'Average', new Resolve(fn (Student $student) => $student->results->avg('score')))
 *     new Text('summary', 'Summary', new Resolve(StudentSummary::class))
 *
 * It receives the authorized record (with the relations the infolist eager-loads).
 */
final readonly class Resolve implements ConfigurationNode
{
    /**
     * @param  Closure|class-string<ValueResolver>  $resolver  fn (mixed $record): mixed
     * @param  bool  $injected  Inject closure parameters by name and type ($record, $user, Subject $subject) instead of passing ($record).
     */
    public function __construct(public Closure|string $resolver, public bool $injected = false)
    {
        if (is_string($resolver) && ! is_subclass_of($resolver, ValueResolver::class)) {
            throw InvalidEntryException::invalid('Resolve', sprintf('"%s" must implement %s.', mb_substr($resolver, 0, 120), ValueResolver::class));
        }
    }

    public function value(mixed $record): mixed
    {
        if ($this->resolver instanceof Closure) {
            return $this->injected ? RecordEvaluation::call($this->resolver, $record, subject: 'an infolist state() closure') : ($this->resolver)($record);
        }

        $resolver = app($this->resolver);

        return $resolver instanceof ValueResolver ? $resolver->resolve($record) : null;
    }

    /**
     * @return array{resolver: string}
     */
    public function normalize(): array
    {
        return ['resolver' => $this->resolver instanceof Closure ? 'closure' : $this->resolver];
    }
}
