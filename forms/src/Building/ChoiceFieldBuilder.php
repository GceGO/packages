<?php

declare(strict_types=1);

namespace Gcego\Forms\Building;

use Closure;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Relationship;

/**
 * The fluent form of a choice field (Select, Radio, CheckboxList):
 *
 *     Select::define('gender', 'Gender')->required()->options(Gender::class)
 *     Select::define('student_id', 'Student')->searchable()->relationship('student', title: 'full_name')
 */
class ChoiceFieldBuilder extends FieldBuilder
{
    /**
     * The choices: a value => label array, a backed enum class, an options
     * provider class, or a closure (optionally depending on other fields).
     *
     * @param  array<array-key, mixed>|Closure|string  $source
     * @param  list<string>  $dependsOn
     */
    public function options(array|Closure|string $source, array $dependsOn = []): static
    {
        return $this->replaceConfiguration(new Options($source, $dependsOn));
    }

    /**
     * Choose among the records of a relationship of the form's model.
     */
    public function relationship(string $name, string $title = 'name', ?Closure $query = null): static
    {
        return $this->replaceConfiguration(new Relationship($name, $title, $query));
    }
}
