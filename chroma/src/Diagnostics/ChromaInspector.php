<?php

declare(strict_types=1);

namespace Gcego\Chroma\Diagnostics;

use Gcego\Chroma\ChromaConfig;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Swatches\Accessibility\ContrastResult;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Validation\Issue;
use Throwable;

/**
 * Explains styling: themes and their contrast, presets, slot registrations,
 * CSS entry points and, for a component tree, unknown slots and the
 * effective classes of every slot. Never throws.
 */
final readonly class ChromaInspector
{
    public function __construct(
        private ThemeRegistry $themes,
        private PresetRegistry $presets,
        private SlotRegistry $slots,
        private SlotResolver $resolver,
        private ChromaConfig $config,
        private SwatchesManager $swatches,
    ) {}

    public function inspect(?ComponentInterface $target = null, ?string $theme = null): ChromaReport
    {
        $diagnostics = [
            new ChromaDiagnostic('config', ChromaDiagnostic::Ok, 'Styling configuration loaded.', [
                'theme' => $this->config->theme,
                'appearance' => $this->swatches->appearance()->value,
                'allowSwitching' => $this->swatches->allowsSwitching(),
                'strict' => $this->config->strict,
            ]),
            new ChromaDiagnostic('presets', ChromaDiagnostic::Ok, sprintf('%d preset(s) registered.', count($this->presets->ids())), ['presets' => $this->presets->ids()]),
            new ChromaDiagnostic('slots', ChromaDiagnostic::Ok, sprintf('%d component slot set(s) registered.', count($this->slots->registrations())), ['targets' => array_keys($this->slots->registrations())]),
        ];

        $diagnostics[] = $this->themes->has($this->config->theme)
            ? new ChromaDiagnostic('theme', ChromaDiagnostic::Ok, sprintf('Default theme "%s" is registered.', $this->config->theme))
            : new ChromaDiagnostic('theme', ChromaDiagnostic::Error, sprintf('Default theme "%s" is not registered.', $this->config->theme));

        foreach ($this->themes->names() as $name) {
            array_push($diagnostics, ...$this->contrast($name));
        }

        foreach (['gcego.css', 'tokens.css', 'theme.css'] as $file) {
            $exists = is_file(dirname(__DIR__, 2).'/resources/css/'.$file);
            $diagnostics[] = new ChromaDiagnostic('css', $exists ? ChromaDiagnostic::Ok : ChromaDiagnostic::Error, sprintf('resources/css/%s %s.', $file, $exists ? 'is present' : 'is missing'));
        }

        $resolved = [];

        if ($target !== null) {
            [$targetDiagnostics, $resolved] = $this->inspectTarget($target, $theme);
            array_push($diagnostics, ...$targetDiagnostics);
        }

        return new ChromaReport($diagnostics, $resolved);
    }

    /**
     * WCAG 2.2 AA contrast of a theme's color pairs in both modes, checked by gcego/swatches.
     *
     * @return list<ChromaDiagnostic>
     */
    public function contrast(string $themeName): array
    {
        if (! $this->swatches->hasTheme($themeName)) {
            return [new ChromaDiagnostic('contrast', ChromaDiagnostic::Ok, sprintf('Theme "%s" has no gcego/swatches colors to check.', $themeName), ['theme' => $themeName])];
        }

        $report = $this->swatches->validate($themeName);
        $diagnostics = [];

        foreach (['light', 'dark'] as $mode) {
            $failures = array_map(
                static fn (Issue $issue): string => $issue->message,
                array_values(array_filter($report->errors(), static fn (Issue $issue): bool => $issue->mode === $mode || $issue->mode === null)),
            );
            $checked = count(array_filter($report->contrast, static fn (ContrastResult $result): bool => $result->mode() === $mode));

            $diagnostics[] = $failures === []
                ? new ChromaDiagnostic('contrast', ChromaDiagnostic::Ok, sprintf('Theme "%s" (%s) meets WCAG AA contrast for all %d checked pairs.', $themeName, $mode, $checked), ['theme' => $themeName, 'mode' => $mode, 'checked' => $checked])
                : new ChromaDiagnostic('contrast', ChromaDiagnostic::Error, sprintf('Theme "%s" (%s): %s', $themeName, $mode, implode(' ', $failures)), ['theme' => $themeName, 'mode' => $mode, 'checked' => $checked]);
        }

        return $diagnostics;
    }

    /**
     * @return array{0: list<ChromaDiagnostic>, 1: list<array{path: string, class: string, slots: array<string, string>}>}
     */
    private function inspectTarget(ComponentInterface $target, ?string $theme): array
    {
        $diagnostics = [];
        $resolved = [];

        try {
            foreach ((new ComponentWalker)->preOrder($target) as $node) {
                $component = $node->component;
                $definitions = $this->slots->for($component);
                $path = $node->path->toString();

                if ($definitions === null) {
                    $diagnostics[] = new ChromaDiagnostic('target.slots', ChromaDiagnostic::Warning, sprintf('%s at "%s" declares no slots; only its Tailwind configuration applies.', $component::class, $path), ['path' => $path]);

                    continue;
                }

                foreach ($component->configurations()->ofClass(Tailwind::class) as $tailwind) {
                    foreach (array_diff($tailwind->slotNames(), array_keys($definitions)) as $unknown) {
                        $diagnostics[] = new ChromaDiagnostic('target.unknown-slot', ChromaDiagnostic::Error, sprintf('%s at "%s" has no "%s" slot.', $component::class, $path, $unknown), ['path' => $path, 'slot' => $unknown]);
                    }
                }

                $slots = [];

                foreach (array_keys($definitions) as $slot) {
                    try {
                        $slots[$slot] = $this->resolver->resolve($component, $slot, $theme)->toString();
                    } catch (Throwable $exception) {
                        $slots[$slot] = '';
                        $diagnostics[] = new ChromaDiagnostic('target.resolve', ChromaDiagnostic::Error, $exception->getMessage(), ['path' => $path, 'slot' => $slot]);
                    }
                }

                $resolved[] = ['path' => $path, 'class' => $component::class, 'slots' => $slots];
            }
        } catch (Throwable $exception) {
            $diagnostics[] = new ChromaDiagnostic('target', ChromaDiagnostic::Error, $exception->getMessage(), ['exception' => $exception::class]);
        }

        return [$diagnostics, $resolved];
    }
}
