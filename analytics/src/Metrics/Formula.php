<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Gcego\Analytics\Exceptions\InvalidDefinitionException;

/**
 * Arithmetic over other metrics for derived metrics, from trusted
 * definitions only:
 *
 *     {completed_attempts} / {started_attempts} * 100
 *     ({passed} + {merit}) / {sat}
 *
 * Grammar: numbers, {metric-name} references, + - * /, unary minus and
 * parentheses. Parsed into postfix once; nothing is ever evaluated as PHP.
 * Any operand without a value, or a division by zero, yields null.
 */
final readonly class Formula
{
    private const array Precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'neg' => 3];

    /**
     * @param  list<array{type: string, value: float|string}>  $postfix
     */
    private function __construct(public string $source, private array $postfix) {}

    public static function parse(string $source): self
    {
        if (strlen($source) > 500) {
            throw InvalidDefinitionException::invalid('Formula', 'it is longer than 500 characters.');
        }

        preg_match_all('/\s*(?:(\d+(?:\.\d+)?)|\{([a-z][a-z0-9]*(?:[-_][a-z0-9]+)*)\}|([-+*\/()]))\s*|(.)/', $source, $matches, PREG_SET_ORDER);
        $output = [];
        $stack = [];
        $expectOperand = true;

        foreach ($matches as $match) {
            if (isset($match[4]) && $match[4] !== '' && trim($match[4]) !== '') {
                throw InvalidDefinitionException::invalid('Formula "'.$source.'"', sprintf('"%s" is not allowed (use numbers, {metric} references, + - * / and parentheses).', $match[4]));
            }

            if (($match[1] ?? '') !== '') {
                $output[] = ['type' => 'number', 'value' => (float) $match[1]];
                $expectOperand = false;
            } elseif (($match[2] ?? '') !== '') {
                $output[] = ['type' => 'ref', 'value' => $match[2]];
                $expectOperand = false;
            } elseif (($token = $match[3] ?? '') !== '') {
                if ($token === '(') {
                    $stack[] = '(';
                    $expectOperand = true;
                } elseif ($token === ')') {
                    while ($stack !== [] && end($stack) !== '(') {
                        $output[] = ['type' => 'op', 'value' => (string) array_pop($stack)];
                    }

                    if (array_pop($stack) !== '(') {
                        throw InvalidDefinitionException::invalid('Formula "'.$source.'"', 'its parentheses do not match.');
                    }

                    $expectOperand = false;
                } else {
                    $operator = $expectOperand && $token === '-' ? 'neg' : $token;

                    if ($expectOperand && $operator !== 'neg') {
                        throw InvalidDefinitionException::invalid('Formula "'.$source.'"', sprintf('"%s" is missing an operand.', $token));
                    }

                    while ($stack !== [] && end($stack) !== '(' && $operator !== 'neg' && self::Precedence[end($stack)] >= self::Precedence[$operator]) {
                        $output[] = ['type' => 'op', 'value' => (string) array_pop($stack)];
                    }

                    $stack[] = $operator;
                    $expectOperand = true;
                }
            }
        }

        if ($expectOperand) {
            throw InvalidDefinitionException::invalid('Formula "'.$source.'"', 'it is incomplete.');
        }

        while ($stack !== []) {
            $operator = (string) array_pop($stack);

            if ($operator === '(') {
                throw InvalidDefinitionException::invalid('Formula "'.$source.'"', 'its parentheses do not match.');
            }

            $output[] = ['type' => 'op', 'value' => $operator];
        }

        return new self($source, $output);
    }

    /**
     * Metric names the formula uses.
     *
     * @return list<string>
     */
    public function references(): array
    {
        $names = [];

        foreach ($this->postfix as $token) {
            if ($token['type'] === 'ref' && is_string($token['value'])) {
                $names[] = $token['value'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, int|float|null>  $values  Metric name => value.
     */
    public function evaluate(array $values): ?float
    {
        /** @var list<float> $stack */
        $stack = [];

        foreach ($this->postfix as $token) {
            $value = $token['value'];

            if ($token['type'] === 'number') {
                $stack[] = (float) $value;

                continue;
            }

            if ($token['type'] === 'ref') {
                $operand = $values[(string) $value] ?? null;

                if ($operand === null) {
                    return null;
                }

                $stack[] = (float) $operand;

                continue;
            }

            if ($value === 'neg') {
                $stack[] = -(array_pop($stack) ?? 0.0);

                continue;
            }

            $right = array_pop($stack) ?? 0.0;
            $left = array_pop($stack) ?? 0.0;

            if ($value === '/' && $right === 0.0) {
                return null;
            }

            $stack[] = match ($value) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                default => $left / $right,
            };
        }

        $result = $stack[0] ?? null;

        return $result !== null && is_finite($result) ? $result : null;
    }
}
