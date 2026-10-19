<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Support;

use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\Presets\EducationPreset;
use Gcego\Luminaries\Presets\FrameworkPreset;
use Illuminate\Support\Str;

/**
 * Writes the typed icon enums of Gcego\Luminaries\Icons from what is installed:
 * one enum per Blade Icons provider (case values "provider:icon") and one for
 * the semantic aliases of the bundled presets (case values "group.name").
 *
 * The output is deterministic (sorted, no timestamps), so regenerating after an
 * icon set upgrade yields a reviewable diff and `gcego:icons:enums --check`
 * can fail CI when the enums and the installed sets drift apart.
 */
final readonly class IconEnumGenerator
{
    public const string Namespace = 'Gcego\\Luminaries\\Icons';

    /**
     * Enum => [provider, description of the set].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array Providers = [
        'Heroicon' => ['heroicon', 'Heroicons, outline (24px, stroke)'],
        'HeroiconSolid' => ['heroicon-solid', 'Heroicons, solid (24px)'],
        'HeroiconMini' => ['heroicon-mini', 'Heroicons, mini (20px)'],
        'HeroiconMicro' => ['heroicon-micro', 'Heroicons, micro (16px)'],
        'Lucide' => ['lucide', 'Lucide (24px, stroke)'],
    ];

    /** The enum of the bundled presets' semantic aliases. */
    public const string Aliases = 'Alias';

    public function __construct(private Luminaries $luminaries) {}

    /**
     * Every enum: short class name => PHP source.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $sources = [];

        foreach (self::Providers as $enum => [$provider, $description]) {
            $sources[$enum] = $this->provider($enum, $provider, $description);
        }

        $sources[self::Aliases] = $this->aliases();

        return $sources;
    }

    public function provider(string $enum, string $provider, string $description): string
    {
        if (! $this->luminaries->hasProvider($provider)) {
            throw InvalidConfigurationException::invalid('icon-enums', sprintf('The "%s" provider is not registered, so %s cannot be generated.', $provider, $enum));
        }

        $cases = [];

        foreach ($this->luminaries->icons($provider) as $icon) {
            $cases[$icon] = IconName::canonical($provider, $icon);
        }

        ksort($cases);

        return $this->source($enum, [
            sprintf('%s, as typed references for icon properties and components:', $description),
            '',
            sprintf('    new Property(Resource::Icon, %s::%s)', $enum, self::caseName(array_key_first($cases) ?? 'icon')),
            sprintf('    <x-gcego-icon :name="\\%s\\%s::%s" />', self::Namespace, $enum, self::caseName(array_key_first($cases) ?? 'icon')),
            '',
            sprintf('%d icons of the "%s" provider.', count($cases), $provider),
        ], $cases);
    }

    public function aliases(): string
    {
        $cases = [];

        foreach ([...array_keys(FrameworkPreset::Aliases), ...array_keys(EducationPreset::Aliases)] as $alias) {
            $cases[$alias] = $alias;
        }

        return $this->source(self::Aliases, [
            'The semantic aliases of the bundled presets. A case names a meaning, not',
            'artwork: it resolves through Luminaries like the string, so',
            'config(\'luminaries.aliases\') still decides the icon.',
            '',
            '    new Property(Resource::Icon, Alias::NavigationStudents)    // "navigation.students"',
            '',
            sprintf('%d framework aliases; the %d education aliases need presets => [\'framework\', \'education\'].', count(FrameworkPreset::Aliases), count(EducationPreset::Aliases)),
        ], $cases);
    }

    /**
     * The typed reference of a bundled icon or alias ("heroicon:home" -> "Heroicon::Home",
     * "actions.edit" -> "Alias::ActionsEdit"), or null when no generated enum has it.
     */
    public static function reference(string $name): ?string
    {
        if (! IconName::isValid($name)) {
            return null;
        }

        $parsed = IconName::parse($name);

        if ($parsed->isCanonical()) {
            foreach (self::Providers as $enum => [$provider]) {
                if ($provider === $parsed->provider) {
                    $case = self::caseName($parsed->icon);

                    return defined(self::Namespace.'\\'.$enum.'::'.$case) ? $enum.'::'.$case : null;
                }
            }

            return null;
        }

        $case = self::caseName($name);

        return $parsed->isAlias() && defined(self::Namespace.'\\'.self::Aliases.'::'.$case) ? self::Aliases.'::'.$case : null;
    }

    /**
     * "arrow-down-tray" -> "ArrowDownTray", "navigation.students" -> "NavigationStudents".
     */
    public static function caseName(string $name): string
    {
        $case = Str::studly(str_replace('.', '-', $name));

        if ($case === '' || ctype_digit($case[0])) {
            $case = 'Icon'.$case;
        }

        return in_array(strtolower($case), ['class', 'cases', 'from', 'tryfrom'], true) ? $case.'Icon' : $case;
    }

    /**
     * @param  list<string>  $description
     * @param  array<string, string>  $cases  name => value
     */
    private function source(string $enum, array $description, array $cases): string
    {
        $lines = [];
        $seen = [];

        foreach ($cases as $name => $value) {
            $case = self::caseName((string) $name);

            if (isset($seen[$case])) {
                throw InvalidConfigurationException::invalid('icon-enums', sprintf('"%s" and "%s" both become %s::%s.', $seen[$case], $name, $enum, $case));
            }

            $seen[$case] = $name;
            $lines[] = sprintf("    case %s = '%s';", $case, $value);
        }

        $docblock = implode("\n", array_map(static fn (string $line): string => rtrim(' * '.$line), $description));

        return sprintf(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace %s;

            use Gcego\Luminaries\Contracts\IconReference;

            /**
            %s
             *
             * Generated by `php artisan gcego:icons:enums`: do not edit by hand.
             */
            enum %s: string implements IconReference
            {
            %s
            }

            PHP, self::Namespace, $docblock, $enum, implode("\n", $lines));
    }
}
