<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\Exceptions\InvalidNameException;
use Illuminate\Support\Str;

/**
 * Validates names given on the command line and derives their forms with
 * Laravel's Str helpers (no own inflection):
 *
 *     $names->className('subject')   // "Subject"
 *     $names->slug('StudentStats')   // "student-stats"
 *     $names->plural('Subject')      // "Subjects"
 */
final class NameNormalizer
{
    /** PHP keywords and names that cannot be class names. */
    private const array Reserved = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false',
        'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private',
        'protected', 'public', 'readonly', 'require', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use',
        'var', 'void', 'while', 'xor', 'yield',
    ];

    /**
     * A class name (StudlyCase) from "subject", "student_stats", "student-stats" or "StudentStats".
     */
    public function className(string $name, string $subject = 'name'): string
    {
        $name = trim($name);

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1 || strlen($name) > 64) {
            throw InvalidNameException::because($subject, $name, 'use letters, digits, "-" and "_", starting with a letter (at most 64 characters).');
        }

        $class = Str::studly($name);

        if (in_array(strtolower($class), self::Reserved, true)) {
            throw InvalidNameException::because($subject, $name, 'it is a reserved PHP word.');
        }

        return $class;
    }

    /**
     * A kebab-case identifier ("student-stats"), as definition names are written.
     */
    public function slug(string $name, string $subject = 'name'): string
    {
        $slug = Str::kebab($this->className($name, $subject));

        return (string) preg_replace('/-+/', '-', str_replace('_', '-', $slug));
    }

    public function snake(string $name): string
    {
        return Str::snake($this->className($name));
    }

    public function camel(string $name): string
    {
        return Str::camel($this->className($name));
    }

    public function plural(string $name): string
    {
        return Str::pluralStudly($this->className($name));
    }

    public function singular(string $name): string
    {
        return Str::singular($this->className($name));
    }

    public function title(string $name): string
    {
        return Str::headline($this->className($name));
    }

    /**
     * Validates an existing definition name ("academics", "admin", "content-approval").
     */
    public function identifier(string $value, string $subject): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 64) {
            throw InvalidNameException::because($subject, $value, 'use lowercase letters, digits and dashes (for example "academics").');
        }

        return $value;
    }

    /**
     * Validates a fully qualified class name given as an option (it is not loaded here).
     */
    public function qualifiedClass(string $value, string $subject): string
    {
        $value = ltrim(trim($value), '\\');

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value) !== 1 || strlen($value) > 255) {
            throw InvalidNameException::because($subject, $value, 'expected a class name such as App\\Models\\Subject.');
        }

        foreach (explode('\\', $value) as $segment) {
            if (in_array(strtolower($segment), self::Reserved, true)) {
                throw InvalidNameException::because($subject, $value, sprintf('"%s" is a reserved PHP word.', $segment));
            }
        }

        return $value;
    }
}
