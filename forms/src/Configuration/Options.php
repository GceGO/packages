<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Forms\Contracts\OptionsProvider;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\State\StatePath;

/**
 * Options of a choice field, declared in code:
 *
 *     new Options(['science' => 'Science', 'arts' => 'Arts'])
 *     new Options(Category::class)                                  // a backed enum
 *     new Options(fn (FormState $state) => Topic::forLevel($state->get('level')), dependsOn: ['level'])
 *     new Options(DepartmentOptions::class)                        // an OptionsProvider
 *
 * Submitted values are accepted only when they are among these options.
 */
final readonly class Options implements ConfigurationNode
{
    /**
     * @param  array<array-key, mixed>|Closure|class-string  $source
     * @param  list<string>  $dependsOn
     */
    public function __construct(public array|Closure|string $source, public array $dependsOn = [])
    {
        if (is_string($source) && ! enum_exists($source) && ! is_a($source, OptionsProvider::class, true)) {
            throw InvalidFieldException::invalid('Options', sprintf('%s is neither a backed enum nor an %s.', $source, OptionsProvider::class));
        }

        foreach ($dependsOn as $field) {
            StatePath::assertName($field);
        }
    }

    /**
     * @return array{options: string, depends_on: list<string>}
     */
    public function normalize(): array
    {
        return ['options' => match (true) {
            is_array($this->source) => 'static',
            $this->source instanceof Closure => 'closure',
            default => $this->source,
        }, 'depends_on' => $this->dependsOn];
    }
}
