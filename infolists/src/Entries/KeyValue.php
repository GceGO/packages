<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use ArrayAccess;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\ValueResolver;
use JsonException;
use Traversable;

/**
 * Selected keys of structured data (a JSON column, an array cast, metadata):
 *
 *     new KeyValue('settings', 'Settings', new Property(KeyValue::Keys, [
 *         'grading.scale' => 'Grading scale',
 *         'term' => 'Term',
 *         'languages' => 'Languages',          // lists are joined
 *     ]))
 *
 * Only the declared keys are shown (dot paths reach nested values): a JSON
 * column is never dumped as a whole. Keys that look secret (password,
 * secret, token, api key) are refused unless AllowSensitive is set.
 */
class KeyValue extends Entry
{
    /** key path => label */
    public const string Keys = 'keys';

    public const string AllowSensitive = 'allow_sensitive';

    public const string SensitivePattern = '/(password|passwd|secret|token|api[_-]?key|private[_-]?key)/i';

    public function view(): string
    {
        return 'gcego-infolists::entries.key-value';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $data = self::data($value);
        $pairs = [];

        if ($data !== null) {
            foreach ($this->mapProperty(self::Keys) as $key => $label) {
                $text = Formats::text(ValueResolver::get($data, $key));
                $pairs[] = ['key' => $key, 'label' => Formats::translate(is_string($label) ? $label : $key), 'text' => $text];

                if (count($pairs) >= $config->maxListItems) {
                    break;
                }
            }
        }

        $filled = array_filter($pairs, static fn (array $pair): bool => $pair['text'] !== null);

        return ['pairs' => $pairs, 'text' => $filled === [] ? null : (string) count($filled), 'title' => null, 'copy' => false];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function data(mixed $value): ?array
    {
        if (is_string($value) && $value !== '') {
            try {
                $value = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        return match (true) {
            is_array($value) => $value,
            $value instanceof Traversable => iterator_to_array($value),
            $value instanceof ArrayAccess, is_object($value) => get_object_vars($value) ?: (method_exists($value, 'toArray') && is_array($array = $value->toArray()) ? $array : null),
            default => null,
        };
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Keys, Type::array(), required: true, rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $label, mixed $key): bool => ! is_string($key) || preg_match('/^[A-Za-z0-9_-]{1,64}(?:\.[A-Za-z0-9_-]{1,64}){0,4}$/', $key) !== 1 || ! is_string($label), ARRAY_FILTER_USE_BOTH) === [], ruleDescription: 'a non-empty map of key paths to labels'),
                new PropertyDefinition(self::AllowSensitive, Type::bool(), default: false),
            )
            ->withConstraint(static function (ComponentInterface $entry): ?string {
                $keys = $entry->get(self::Keys);

                if ($entry->get(self::AllowSensitive) === true || ! is_array($keys)) {
                    return null;
                }

                foreach (array_keys($keys) as $key) {
                    if (preg_match(self::SensitivePattern, (string) $key) === 1) {
                        return sprintf('key "%s" looks sensitive; set AllowSensitive (and an Access rule) to show it.', $key);
                    }
                }

                return null;
            });
    }
}
