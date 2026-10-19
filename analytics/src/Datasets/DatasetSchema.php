<?php

declare(strict_types=1);

namespace Gcego\Analytics\Datasets;

use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;

/**
 * What one viewer may use of a dataset: the declared fields and dimensions
 * whose requirements they satisfy. Never derived from the database
 * schema: undeclared columns do not exist for analytics.
 */
final readonly class DatasetSchema
{
    /**
     * @param  array<string, Field>  $fields
     * @param  array<string, Dimension>  $dimensions
     */
    public function __construct(public Dataset $dataset, public array $fields, public array $dimensions) {}

    public static function for(Dataset $dataset, AnalyticsAuthorizer $authorizer, AnalyticsContext $context): self
    {
        $fields = [];
        $dimensions = [];

        foreach ($dataset->fields() as $field) {
            if ($authorizer->field($field, $context)) {
                $fields[$field->nodeName()] = $field;
            }
        }

        foreach ($dataset->dimensions() as $dimension) {
            if ($authorizer->dimension($dimension, $context)) {
                $dimensions[$dimension->nodeName()] = $dimension;
            }
        }

        return new self($dataset, $fields, $dimensions);
    }

    /**
     * @return array{dataset: string, fields: list<array{name: string, label: string, type: string, aggregates: list<string>, sensitive: bool, filterable: bool}>, dimensions: list<array{name: string, label: string, type: string, filterable: bool}>}
     */
    public function toArray(): array
    {
        return [
            'dataset' => $this->dataset->nodeName(),
            'fields' => array_values(array_map(static fn (Field $field): array => ['name' => $field->nodeName(), 'label' => $field->label(), 'type' => $field->fieldType()->value, 'aggregates' => array_map(static fn ($aggregate): string => $aggregate->value, $field->aggregates()), 'sensitive' => $field->isSensitive(), 'filterable' => $field->isFilterable()], $this->fields)),
            'dimensions' => array_values(array_map(static fn (Dimension $dimension): array => ['name' => $dimension->nodeName(), 'label' => $dimension->label(), 'type' => $dimension->dimensionType(), 'filterable' => $dimension->isFilterable()], $this->dimensions)),
        ];
    }
}
