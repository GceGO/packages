<?php

declare(strict_types=1);

namespace Gcego\Forms\Options;

use BackedEnum;
use Closure;
use Gcego\Data\DataManager;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\Query;
use Gcego\Data\Relationships\RelationPathValidator;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Relationship;
use Gcego\Forms\Contracts\OptionsProvider;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\State\FormState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Resolves the trusted options of choice fields and checks submitted values
 * against them. Relationship options are read through gcego/data as the
 * current user, so policies, tenant and security scopes apply; a user who
 * may not list the related records gets no options and can submit none.
 *
 * Request-scoped: options are memoized for the current request only.
 */
final class OptionsResolver
{
    /**
     * @var array<string, OptionCollection>
     */
    private array $memo = [];

    public function __construct(
        private readonly Container $container,
        private readonly DataManager $data,
        private readonly FormsConfig $config,
    ) {}

    /**
     * @param  class-string<Model>|null  $model  The form's model (needed by Relationship).
     */
    public function options(ChoiceField $field, FormState $state, ?string $model = null): OptionCollection
    {
        $node = $field->options();
        $relationship = $field->relationship();
        $key = spl_object_id($field).'|'.$model.'|'.md5((string) json_encode(array_map(static fn (string $path): mixed => $state->get($path), $node->dependsOn ?? [])));

        return $this->memo[$key] ??= match (true) {
            $relationship !== null => $this->relationshipOptions($field, $relationship, $model, null),
            $node === null => new OptionCollection,
            is_array($node->source) => OptionCollection::from($node->source),
            $node->source instanceof Closure => OptionCollection::from((array) ($node->source)($state)),
            enum_exists($node->source) && is_a($node->source, BackedEnum::class, true) => OptionCollection::from($node->source::cases()),
            default => $this->provider($node->source)->options($state, $model),
        };
    }

    /**
     * @param  list<string|int>  $values
     * @param  class-string<Model>|null  $model
     */
    public function allows(ChoiceField $field, array $values, FormState $state, ?string $model = null): bool
    {
        if ($values === []) {
            return true;
        }

        $relationship = $field->relationship();

        if ($relationship !== null) {
            return count($this->relationshipOptions($field, $relationship, $model, $values)) === count(array_unique(array_map(strval(...), $values)));
        }

        $node = $field->options();

        if ($node !== null && is_string($node->source) && ! enum_exists($node->source)) {
            return $this->provider($node->source)->allows($values, $state, $model);
        }

        $options = $this->options($field, $state, $model);

        foreach ($values as $value) {
            if (! $options->contains($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The related model class and whether the relationship holds many records.
     *
     * @param  class-string<Model>  $model
     * @return array{0: class-string<Model>, 1: bool}
     */
    public static function related(string $model, Relationship $relationship): array
    {
        $related = RelationPathValidator::assert($model, $model, $relationship->name);
        $relation = (new $model)->{$relationship->name}();

        if (! $relation instanceof BelongsTo && ! $relation instanceof BelongsToMany) {
            throw InvalidFieldException::invalid('Relationship "'.$relationship->name.'"', 'only BelongsTo and BelongsToMany relationships provide options.');
        }

        return [$related, $relation instanceof BelongsToMany];
    }

    /**
     * @param  class-string<Model>|null  $model
     * @param  list<string|int>|null  $only  Restrict to these keys (validation).
     */
    private function relationshipOptions(ChoiceField $field, Relationship $relationship, ?string $model, ?array $only): OptionCollection
    {
        if ($model === null) {
            throw InvalidFieldException::invalid(sprintf('Field "%s"', $field->fieldName()), 'relationship options need a form with a Source model.');
        }

        [$related] = self::related($model, $relationship);
        $nodes = [new Sort($relationship->title), new Paginate($only === null ? $this->config->optionsLimit : max(1, min(100, count($only))))];

        if ($relationship->query !== null) {
            $nodes[] = new Query($relationship->query);
        }

        if ($only !== null) {
            $nodes[] = new Query(static fn (Builder $query) => $query->whereKey($only));
        }

        try {
            $result = $this->data->query(new Source($related, ...$nodes));
        } catch (AuthorizationException) {
            return new OptionCollection;
        }

        $options = [];

        foreach ($result->items() as $record) {
            if ($record instanceof Model && (is_int($record->getKey()) || is_string($record->getKey()))) {
                $title = $record->getAttribute($relationship->title);
                $options[] = new Option($record->getKey(), is_scalar($title) ? (string) $title : (string) $record->getKey());
            }
        }

        return new OptionCollection($options);
    }

    private function provider(string $class): OptionsProvider
    {
        $provider = $this->container->make($class);

        return $provider instanceof OptionsProvider ? $provider : throw InvalidFieldException::invalid('Options', sprintf('%s is not an OptionsProvider.', $class));
    }
}
