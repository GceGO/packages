<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Accessibility\Apca;
use Gcego\Swatches\Accessibility\ColorVision;
use Gcego\Swatches\Accessibility\ColorVisionSimulator;
use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\Exceptions\SwatchesException;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Tokens\ContrastRequirement;
use Gcego\Swatches\Tokens\SemanticTokens;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:contrast "#111827" white
 * php artisan gcego:swatches:contrast primary-foreground primary --theme=ocean --dark
 */
final class ContrastCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:contrast
        {foreground : A color or a token name}
        {background : A color or a token name}
        {--theme= : Theme used to resolve token names}
        {--dark : Resolve token names in dark mode}
        {--format=text : Output format: text or json}';

    protected $description = 'Compute the WCAG contrast ratio of two colors or tokens';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $themeOption = $this->option('theme');
        $dark = $this->option('dark') === true;

        try {
            $theme = $swatches->resolve(is_string($themeOption) && $themeOption !== '' ? $themeOption : null);
            $color = static fn (string $value): Color => SemanticTokens::has($value) ? $theme->color($value, $dark) : Color::parse($value);
            $foreground = $color((string) $this->argument('foreground'));
            $background = $color((string) $this->argument('background'));
            $backdrop = $theme->color('background', $dark);
        } catch (SwatchesException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $ratio = Contrast::ratio($foreground, $background, $backdrop);
        $checks = [];

        foreach (ContrastRequirement::cases() as $requirement) {
            $checks[$requirement->value] = $ratio >= $requirement->minimum();
        }

        $checks['aaa-text'] = $ratio >= 7.0;
        $suggestion = $ratio < 4.5 ? Contrast::suggest($foreground, $background, 4.5, $backdrop) : null;
        $apca = Apca::contrast($foreground, $background, $backdrop);
        $simulated = [];

        foreach (ColorVision::cases() as $vision) {
            $front = ColorVisionSimulator::simulate($foreground->over($background->over($backdrop)), $vision);
            $behind = ColorVisionSimulator::simulate($background->over($backdrop), $vision);
            $simulated[$vision->value] = ['foreground' => $front->toHex(), 'background' => $behind->toHex(), 'ratio' => floor(Contrast::ratio($front, $behind) * 100) / 100];
        }

        if ($this->json()) {
            $this->emit([
                'foreground' => $foreground->toHex(),
                'background' => $background->toHex(),
                'ratio' => floor($ratio * 100) / 100,
                'passes' => $checks,
                'suggestion' => $suggestion?->toHex(),
                'apca' => ['lc' => round($apca, 1), 'text' => abs($apca) >= Apca::minimum(ContrastRequirement::Text), 'large-text' => abs($apca) >= Apca::minimum(ContrastRequirement::LargeText), 'non-text' => abs($apca) >= Apca::minimum(ContrastRequirement::NonText)],
                'color_vision' => $simulated,
            ]);

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Foreground', $foreground->toHex());
        $this->components->twoColumnDetail('Background', $background->toHex());
        $this->components->twoColumnDetail('Contrast ratio', Contrast::format($ratio));

        foreach ([ContrastRequirement::Text, ContrastRequirement::LargeText, ContrastRequirement::NonText] as $requirement) {
            $this->components->twoColumnDetail('AA '.$requirement->label(), $checks[$requirement->value] ? '<fg=green>pass</>' : '<fg=red>fail</>');
        }

        $this->components->twoColumnDetail('AAA text (7:1)', $checks['aaa-text'] ? '<fg=green>pass</>' : '<fg=red>fail</>');

        if ($suggestion !== null) {
            $this->components->twoColumnDetail('Nearest passing foreground (4.5:1)', $suggestion->toHex());
        }

        $this->components->twoColumnDetail('APCA (advisory)', sprintf('%s · text %s, large text %s, non-text %s', Apca::format($apca), ...array_map(
            static fn (ContrastRequirement $requirement): string => abs($apca) >= Apca::minimum($requirement) ? '<fg=green>pass</>' : '<fg=yellow>below</>',
            [ContrastRequirement::Text, ContrastRequirement::LargeText, ContrastRequirement::NonText],
        )));

        foreach ($simulated as $vision => $result) {
            $this->components->twoColumnDetail('Simulated '.$vision, sprintf('%s on %s · %s:1', $result['foreground'], $result['background'], number_format($result['ratio'], 2)));
        }

        return self::SUCCESS;
    }
}
