<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use DateTimeInterface;
use Gcego\Forms\FormsConfig;

/**
 * A time of day (native <input type="time">), stored as "H:i", never time-zone converted.
 */
class TimePicker extends DatePicker
{
    public function inputType(): string
    {
        return 'time';
    }

    public function format(): string
    {
        return 'H:i';
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        return is_string($value) && preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $value, $match) === 1 ? $match[1] : $value;
    }

    protected static function boundFormat(): string
    {
        return 'H:i';
    }
}
