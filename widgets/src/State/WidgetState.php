<?php

declare(strict_types=1);

namespace Gcego\Widgets\State;

use Gcego\Widgets\Data\Period;
use Gcego\Widgets\Exceptions\InvalidWidgetStateException;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * Validated widget state: the selected period (when the widget has a
 * DateRange) and the feed page. Built from untrusted input on every request;
 * browser-visible state is only these small values.
 */
final readonly class WidgetState
{
    public const int MaxPage = 100;

    public function __construct(public ?Period $period = null, public int $page = 1) {}

    /**
     * @param  array<array-key, mixed>  $input  Keys: range, from, to, page.
     *
     * @throws InvalidWidgetStateException
     */
    public static function from(Widget $widget, array $input, WidgetsConfig $config): self
    {
        $range = $widget->dateRange();
        $zone = $config->timezone ?? (is_string($app = config('app.timezone')) ? $app : 'UTC');
        $period = null;

        if ($range !== null) {
            $preset = self::string($input['range'] ?? null, 'range');
            $period = $range->resolve($preset === '' ? null : $preset, self::string($input['from'] ?? null, 'from'), self::string($input['to'] ?? null, 'to'), $zone, $config->maxRangeDays);
        }

        $page = $input['page'] ?? 1;

        if (! is_int($page) && ! (is_string($page) && preg_match('/^\d{1,3}$/', $page) === 1)) {
            throw InvalidWidgetStateException::invalid('page', 'must be a page number.');
        }

        $page = (int) $page;

        if ($page < 1 || $page > self::MaxPage) {
            throw InvalidWidgetStateException::invalid('page', sprintf('must be between 1 and %d.', self::MaxPage));
        }

        return new self($period, $page);
    }

    private static function string(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || strlen($value) > 32) {
            throw InvalidWidgetStateException::invalid($field, 'must be a short text.');
        }

        return $value;
    }
}
