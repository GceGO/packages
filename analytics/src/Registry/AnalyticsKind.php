<?php

declare(strict_types=1);

namespace Gcego\Analytics\Registry;

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Support\DefinitionHash;
use Gcego\Components\Validation\ValidationError;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The gcego/registry kinds of analytics: "dataset", "metric", "report"
 * and "analytics-dashboard" (identities such as "metric:total_students").
 * Each definition is validated on its own when registered; references
 * between definitions are checked by AnalyticsDefinitionValidator.
 */
final readonly class AnalyticsKind implements DefinitionKind
{
    public const string Dataset = 'dataset';

    public const string Metric = 'metric';

    public const string Report = 'report';

    public const string Dashboard = 'analytics-dashboard';

    /** Kind => definition class, and the discovery sub-folder. */
    public const array Kinds = [
        self::Dataset => [Dataset::class, 'Datasets'],
        self::Metric => [Metric::class, 'Metrics'],
        self::Report => [Report::class, 'Reports'],
        self::Dashboard => [Dashboard::class, 'Dashboards'],
    ];

    public function __construct(private string $kind) {}

    public function id(): string
    {
        return $this->kind;
    }

    public function contract(): string
    {
        return self::Kinds[$this->kind][0] ?? throw new InvalidArgumentException('Unknown analytics kind.');
    }

    public function name(object $definition): string
    {
        $name = $this->definition($definition)->nodeName();

        if (! AnalyticsNode::validName($name)) {
            throw new InvalidArgumentException(sprintf('A %s needs a lowercase Name.', $this->kind));
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        return array_map(static fn (ValidationError $error): string => $error->format(), $this->definition($definition)->validate()->errors());
    }

    public function metadata(object $definition): array
    {
        $definition = $this->definition($definition);

        return [
            'label' => $definition->label(),
            'requirements' => count($definition->requirements()),
            'hash' => DefinitionHash::of($definition),
        ];
    }

    private function definition(object $definition): AnalyticsNode
    {
        $class = $this->contract();

        return $definition instanceof $class && $definition instanceof AnalyticsNode ? $definition : throw new InvalidArgumentException(sprintf('Not a %s.', $this->kind));
    }
}
