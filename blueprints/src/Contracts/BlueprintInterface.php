<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

use Gcego\Blueprints\Metadata\BlueprintMetadata;
use Gcego\Blueprints\Support\BlueprintIdentity;

/**
 * The read-only contract registries, runtimes and feature engines consume.
 *
 * Single-reference accessors return a class name or null when the
 * definition is absent; collection accessors return a list of class names
 * in declaration order, empty when absent.
 */
interface BlueprintInterface
{
    public function name(): string;

    /**
     * The Title definition, else the humanized name ("academic-subjects" -> "Academic Subjects").
     */
    public function title(): string;

    public function description(): ?string;

    public function icon(): ?string;

    /**
     * The slugified name ("academic_subjects" -> "academic-subjects").
     */
    public function slug(): string;

    /**
     * @return class-string|null
     */
    public function model(): ?string;

    /**
     * @return class-string|null
     */
    public function policy(): ?string;

    /**
     * @return class-string|null
     */
    public function table(): ?string;

    /**
     * @return class-string|null
     */
    public function form(): ?string;

    /**
     * @return class-string|null
     */
    public function infolist(): ?string;

    /**
     * @return list<class-string>
     */
    public function pages(): array;

    /**
     * @return list<class-string>
     */
    public function actions(): array;

    /**
     * @return list<class-string>
     */
    public function bulkActions(): array;

    /**
     * @return list<class-string>
     */
    public function relations(): array;

    /**
     * @return list<class-string>
     */
    public function widgets(): array;

    public function identity(): BlueprintIdentity;

    public function metadata(): BlueprintMetadata;

    public function has(string $key): bool;

    public function definition(string $key): ?BlueprintDefinition;

    /**
     * The definition of the given class, typed for extension packages.
     *
     * @template T of BlueprintDefinition
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function find(string $class): ?BlueprintDefinition;

    /**
     * The normalized value of a definition, or null when it is absent.
     */
    public function get(string $key): mixed;

    /**
     * @return list<string> Definition keys in declaration order.
     */
    public function keys(): array;

    /**
     * @return list<BlueprintDefinition> In declaration order.
     */
    public function definitions(): array;

    /**
     * Every class reference, by definition key, in declaration order.
     *
     * @return array<string, list<class-string>>
     */
    public function references(): array;

    /**
     * @return array{name: string, title: string, slug: string, definitions: array<string, mixed>}
     */
    public function toArray(): array;
}
