<?php

declare(strict_types=1);

namespace Gcego\Widgets\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * How a stat compares with its previous value (from an Aggregate with
 * compare: true, or a provider's WidgetDataResult::value($value, previous: …)):
 *
 *     new Trend()                                       // an increase is good
 *     new Trend(increase: 'negative')                   // failed assessments: an increase is bad
 *     new Trend(increase: 'neutral', show: 'absolute')
 *
 * The meaning is always spelled out for screen readers ("Up 12% from the
 * previous period, an improvement"), so it never depends on color or arrows.
 */
final readonly class Trend implements ConfigurationNode
{
    public const array Meanings = ['positive', 'negative', 'neutral'];

    public const array Shows = ['percent', 'absolute', 'both'];

    public function __construct(public string $increase = 'positive', public string $show = 'percent', public ?string $label = null)
    {
        if (! in_array($increase, self::Meanings, true) || ! in_array($show, self::Shows, true)) {
            throw InvalidWidgetException::invalid('Trend', 'increase must be positive, negative or neutral; show must be percent, absolute or both.');
        }
    }

    /**
     * @return array{increase: string, show: string}
     */
    public function normalize(): array
    {
        return ['increase' => $this->increase, 'show' => $this->show];
    }
}
