<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dimensions;

use Gcego\Analytics\Contracts\DimensionLabels;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Security\ContextScope;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Source;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Labels of dimension keys: static options, a trusted DimensionLabels
 * resolver, or a label model read through gcego/data as the viewer (one
 * whereIn query per dimension; keys of records the viewer may not see, or
 * of other tenants, get no label). Null keys read "Not set".
 */
final readonly class DimensionLabelResolver
{
    public function __construct(private DataManager $data, private ContextScope $scope, private Container $container) {}

    /**
     * @param  list<string|null>  $keys
     * @return array<string, string>
     */
    public function labels(Dimension $dimension, array $keys, AnalyticsContext $context): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn (?string $key): bool => $key !== null)));

        if ($keys === []) {
            return [];
        }

        if ($dimension->options() !== []) {
            return array_intersect_key($dimension->options(), array_flip($keys));
        }

        if ($dimension->dimensionType() === 'boolean') {
            return array_intersect_key(['1' => (string) __('gcego-analytics::messages.yes'), '0' => (string) __('gcego-analytics::messages.no')], array_flip($keys));
        }

        try {
            if (($resolver = $dimension->labelResolver()) !== null) {
                $instance = $this->container->make($resolver);

                return $instance instanceof DimensionLabels ? array_map('strval', array_intersect_key($instance->labels($keys, $context), array_flip($keys))) : [];
            }

            $model = $dimension->labelModel();

            if ($model !== null) {
                return $this->scope->run($context, function () use ($model, $dimension, $keys, $context): array {
                    $prototype = new $model;
                    $query = $this->data->scopedQuery(new Source($model), [], QueryContext::for($context->user));
                    $keyColumn = $prototype instanceof Model ? $prototype->getKeyName() : 'id';
                    $labels = [];

                    foreach ($query->whereIn($keyColumn, array_slice($keys, 0, 1000))->pluck($dimension->labelColumn(), $keyColumn) as $key => $label) {
                        if (is_scalar($label)) {
                            $labels[(string) $key] = (string) $label;
                        }
                    }

                    return $labels;
                });
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    public static function notSet(): string
    {
        return (string) __('gcego-analytics::messages.not_set');
    }
}
