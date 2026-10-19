<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

/**
 * The GCEGO semantic color tokens: the single source of truth for colors.
 *
 * resources/css/tokens.css is generated from this catalog (gcego:swatches:generate
 * --package; a test fails when they disagree). "{name}" values reference another
 * token and follow it through themes and modes. Values marked "kept" are the
 * original gcego/chroma colors, unchanged so existing screens look the same.
 */
final class SemanticTokens
{
    /**
     * name => [group, light, dark (null: same as light), description]
     *
     * @var array<string, array{TokenGroup, string, string|null, string}>
     */
    public const array Definitions = [
        // Surfaces and text (kept).
        'background' => [TokenGroup::Surface, '#f6f7f9', '#0a0d12', 'Page background behind surfaces.'],
        'foreground' => [TokenGroup::Surface, '#0f1729', '#e8ebf0', 'Default text and icons.'],
        'surface' => [TokenGroup::Surface, '#ffffff', '#11151c', 'Content surfaces: panels, sections, tables.'],
        'surface-foreground' => [TokenGroup::Surface, '{foreground}', null, 'Text on surface.'],
        'surface-muted' => [TokenGroup::Surface, '#f1f3f6', '#181d26', 'Recessed surfaces: table headers, wells, code.'],
        'surface-muted-foreground' => [TokenGroup::Surface, '{muted-foreground}', null, 'Secondary text on surface-muted.'],
        'card' => [TokenGroup::Surface, '{surface}', null, 'Cards.'],
        'card-foreground' => [TokenGroup::Surface, '{foreground}', null, 'Text on cards.'],
        'popover' => [TokenGroup::Surface, '{surface}', null, 'Menus, dropdowns, tooltips, dialogs.'],
        'popover-foreground' => [TokenGroup::Surface, '{foreground}', null, 'Text in popovers.'],
        'muted' => [TokenGroup::Surface, '{surface-muted}', null, 'Muted backgrounds.'],
        'muted-foreground' => [TokenGroup::Surface, '#4a5468', '#9aa3b2', 'Secondary text: descriptions, help text, captions.'],
        'overlay' => [TokenGroup::Surface, 'rgb(15 23 41 / 0.5)', 'rgb(0 0 0 / 0.6)', 'Backdrop behind modals and drawers.'],

        // Borders and focus (kept).
        'border' => [TokenGroup::Border, '#e3e6ec', '#232a35', 'Dividers and decorative borders.'],
        'border-strong' => [TokenGroup::Border, '#868fa0', '#6c7586', 'Borders that identify a control (3:1).'],
        'input' => [TokenGroup::Border, '{border-strong}', null, 'Form control borders (3:1).'],
        'ring' => [TokenGroup::Border, '{primary}', null, 'Focus indicators (3:1).'],

        // Brand (primary kept).
        'primary' => [TokenGroup::Brand, '#0b7570', '#3ec2b5', 'Primary actions, active navigation, links.'],
        'primary-hover' => [TokenGroup::Brand, '#085c58', '#6ad5ca', 'Primary hover.'],
        'primary-active' => [TokenGroup::Brand, '{primary-hover}', null, 'Primary pressed.'],
        'primary-foreground' => [TokenGroup::Brand, '#ffffff', '#04201e', 'Text on primary.'],
        'primary-soft' => [TokenGroup::Brand, '#e7f3f2', '#12302d', 'Tinted primary background: selected rows, active items.'],
        'secondary' => [TokenGroup::Brand, '#e9edf2', '#232a35', 'Secondary actions.'],
        'secondary-hover' => [TokenGroup::Brand, '#dde2e9', '#2c3442', 'Secondary hover.'],
        'secondary-foreground' => [TokenGroup::Brand, '{foreground}', null, 'Text on secondary.'],
        'accent' => [TokenGroup::Brand, '{surface-muted}', null, 'Subtle highlight: hovered menu items and rows.'],
        'accent-foreground' => [TokenGroup::Brand, '{foreground}', null, 'Text on accent.'],
        'link' => [TokenGroup::Brand, '{primary}', null, 'Inline links.'],
        'link-hover' => [TokenGroup::Brand, '{primary-hover}', null, 'Hovered links.'],

        // Status (base colors kept).
        'success' => [TokenGroup::Status, '#127a3e', '#4ade80', 'Success text, icons and fills.'],
        'success-foreground' => [TokenGroup::Status, '#ffffff', '#052e16', 'Text on success.'],
        'success-soft' => [TokenGroup::Status, '#e8f5ec', '#0f2a1a', 'Success badges and alerts.'],
        'warning' => [TokenGroup::Status, '#a65308', '#fbbf24', 'Warning text, icons and fills.'],
        'warning-foreground' => [TokenGroup::Status, '#ffffff', '#2a1a03', 'Text on warning.'],
        'warning-soft' => [TokenGroup::Status, '#fdf3e6', '#2c2108', 'Warning badges and alerts.'],
        'danger' => [TokenGroup::Status, '#c02a2f', '#f87171', 'Errors and destructive actions.'],
        'danger-hover' => [TokenGroup::Status, '#9f2227', '#fca5a5', 'Destructive hover.'],
        'danger-foreground' => [TokenGroup::Status, '#ffffff', '#1f0507', 'Text on danger.'],
        'danger-soft' => [TokenGroup::Status, '#fcecec', '#2e1315', 'Error badges and alerts.'],
        'info' => [TokenGroup::Status, '#1f5fc4', '#6aa8f7', 'Informational text, icons and fills.'],
        'info-foreground' => [TokenGroup::Status, '#ffffff', '#071a33', 'Text on info.'],
        'info-soft' => [TokenGroup::Status, '#eaf1fc', '#112239', 'Info badges and alerts.'],

        // Interaction states.
        'selected' => [TokenGroup::State, '{primary-soft}', null, 'Selected rows, options and items.'],
        'selected-foreground' => [TokenGroup::State, '{foreground}', null, 'Text on selected.'],
        'disabled' => [TokenGroup::State, '{surface-muted}', null, 'Disabled control background.'],
        'disabled-foreground' => [TokenGroup::State, '#868fa0', '#6c7586', 'Disabled text (exempt from WCAG contrast).'],
        'invalid' => [TokenGroup::State, '{danger}', null, 'Invalid control borders and messages.'],
        'readonly' => [TokenGroup::State, '{surface-muted}', null, 'Read-only control background.'],

        // Panel chrome.
        'sidebar' => [TokenGroup::Navigation, '{surface}', null, 'Panel sidebar.'],
        'sidebar-foreground' => [TokenGroup::Navigation, '{foreground}', null, 'Sidebar text.'],
        'sidebar-muted-foreground' => [TokenGroup::Navigation, '{muted-foreground}', null, 'Sidebar group labels and inactive items.'],
        'sidebar-border' => [TokenGroup::Navigation, '{border}', null, 'Sidebar edge and dividers.'],
        'sidebar-active' => [TokenGroup::Navigation, '{primary-soft}', null, 'Active navigation item.'],
        'sidebar-active-foreground' => [TokenGroup::Navigation, '{primary}', null, 'Active navigation text.'],
        'topbar' => [TokenGroup::Navigation, '{surface}', null, 'Panel top bar.'],
        'topbar-foreground' => [TokenGroup::Navigation, '{foreground}', null, 'Top bar text.'],
        'topbar-border' => [TokenGroup::Navigation, '{border}', null, 'Top bar edge.'],

        // Data visualization.
        'chart-1' => [TokenGroup::Chart, '#0b7570', '#3ec2b5', 'Categorical series 1.'],
        'chart-2' => [TokenGroup::Chart, '#2f62d4', '#6aa8f7', 'Categorical series 2.'],
        'chart-3' => [TokenGroup::Chart, '#c2410c', '#fb923c', 'Categorical series 3.'],
        'chart-4' => [TokenGroup::Chart, '#7c3aed', '#b197fc', 'Categorical series 4.'],
        'chart-5' => [TokenGroup::Chart, '#be185d', '#f472b6', 'Categorical series 5.'],
        'chart-6' => [TokenGroup::Chart, '#8a6100', '#facc15', 'Categorical series 6.'],
        'chart-7' => [TokenGroup::Chart, '#4d7c0f', '#a3e635', 'Categorical series 7.'],
        'chart-8' => [TokenGroup::Chart, '#5b6577', '#a3acbb', 'Categorical series 8.'],
        'chart-positive' => [TokenGroup::Chart, '{success}', null, 'Gains and positive trends.'],
        'chart-negative' => [TokenGroup::Chart, '{danger}', null, 'Losses and negative trends.'],
        'chart-neutral' => [TokenGroup::Chart, '{muted-foreground}', null, 'Neutral or comparison series.'],
        'chart-grid' => [TokenGroup::Chart, '{border}', null, 'Grid lines.'],
        'chart-axis' => [TokenGroup::Chart, '{muted-foreground}', null, 'Axis labels and ticks.'],

        // Original gcego/chroma names, kept as aliases (text-gcego-text, text-gcego-text-muted).
        // Prefer foreground and muted-foreground in new code.
        'text' => [TokenGroup::Legacy, '{foreground}', null, 'Alias of foreground.'],
        'text-muted' => [TokenGroup::Legacy, '{muted-foreground}', null, 'Alias of muted-foreground.'],
    ];

    /**
     * Pairs every theme must pass in light and dark mode: [foreground, background, requirement, purpose].
     *
     * @var list<array{string, string, ContrastRequirement, string}>
     */
    public const array ContrastPairs = [
        ['foreground', 'background', ContrastRequirement::Text, 'Body text on the page'],
        ['foreground', 'surface', ContrastRequirement::Text, 'Body text on surfaces'],
        ['foreground', 'surface-muted', ContrastRequirement::Text, 'Text on recessed surfaces'],
        ['muted-foreground', 'background', ContrastRequirement::Text, 'Secondary text on the page'],
        ['muted-foreground', 'surface', ContrastRequirement::Text, 'Help text and descriptions'],
        ['muted-foreground', 'surface-muted', ContrastRequirement::Text, 'Secondary text on recessed surfaces'],
        ['card-foreground', 'card', ContrastRequirement::Text, 'Card text'],
        ['popover-foreground', 'popover', ContrastRequirement::Text, 'Menu and dialog text'],
        ['primary-foreground', 'primary', ContrastRequirement::Text, 'Primary button label'],
        ['primary-foreground', 'primary-hover', ContrastRequirement::Text, 'Hovered primary button label'],
        ['primary-foreground', 'primary-active', ContrastRequirement::Text, 'Pressed primary button label'],
        ['primary', 'surface', ContrastRequirement::Text, 'Primary-colored text and links'],
        ['primary', 'background', ContrastRequirement::Text, 'Links on the page'],
        ['primary', 'primary-soft', ContrastRequirement::Text, 'Active item text on its tint'],
        ['foreground', 'primary-soft', ContrastRequirement::Text, 'Text in selected rows'],
        ['secondary-foreground', 'secondary', ContrastRequirement::Text, 'Secondary button label'],
        ['secondary-foreground', 'secondary-hover', ContrastRequirement::Text, 'Hovered secondary button label'],
        ['accent-foreground', 'accent', ContrastRequirement::Text, 'Hovered menu item'],
        ['link', 'surface', ContrastRequirement::Text, 'Links'],
        ['link-hover', 'surface', ContrastRequirement::Text, 'Hovered links'],
        ['success', 'surface', ContrastRequirement::Text, 'Success messages'],
        ['warning', 'surface', ContrastRequirement::Text, 'Warning messages'],
        ['danger', 'surface', ContrastRequirement::Text, 'Validation errors'],
        ['info', 'surface', ContrastRequirement::Text, 'Informational messages'],
        ['success-foreground', 'success', ContrastRequirement::Text, 'Text on success fills'],
        ['warning-foreground', 'warning', ContrastRequirement::Text, 'Text on warning fills'],
        ['danger-foreground', 'danger', ContrastRequirement::Text, 'Destructive button label'],
        ['danger-foreground', 'danger-hover', ContrastRequirement::Text, 'Hovered destructive button label'],
        ['info-foreground', 'info', ContrastRequirement::Text, 'Text on info fills'],
        ['success', 'success-soft', ContrastRequirement::Text, 'Success badges and alerts'],
        ['warning', 'warning-soft', ContrastRequirement::Text, 'Warning badges and alerts'],
        ['danger', 'danger-soft', ContrastRequirement::Text, 'Error badges and alerts'],
        ['info', 'info-soft', ContrastRequirement::Text, 'Info badges and alerts'],
        ['foreground', 'success-soft', ContrastRequirement::Text, 'Alert body text (success)'],
        ['foreground', 'warning-soft', ContrastRequirement::Text, 'Alert body text (warning)'],
        ['foreground', 'danger-soft', ContrastRequirement::Text, 'Alert body text (danger)'],
        ['foreground', 'info-soft', ContrastRequirement::Text, 'Alert body text (info)'],
        ['selected-foreground', 'selected', ContrastRequirement::Text, 'Selected rows'],
        ['sidebar-foreground', 'sidebar', ContrastRequirement::Text, 'Sidebar items'],
        ['sidebar-muted-foreground', 'sidebar', ContrastRequirement::Text, 'Sidebar group labels'],
        ['sidebar-active-foreground', 'sidebar-active', ContrastRequirement::Text, 'Active navigation item'],
        ['topbar-foreground', 'topbar', ContrastRequirement::Text, 'Top bar text'],
        ['border-strong', 'surface', ContrastRequirement::NonText, 'Control boundaries'],
        ['input', 'surface', ContrastRequirement::NonText, 'Input borders'],
        ['input', 'background', ContrastRequirement::NonText, 'Input borders on the page'],
        ['ring', 'surface', ContrastRequirement::NonText, 'Focus indicator'],
        ['ring', 'background', ContrastRequirement::NonText, 'Focus indicator on the page'],
        ['invalid', 'surface', ContrastRequirement::NonText, 'Invalid input border'],
        ['chart-1', 'surface', ContrastRequirement::NonText, 'Chart series 1'],
        ['chart-2', 'surface', ContrastRequirement::NonText, 'Chart series 2'],
        ['chart-3', 'surface', ContrastRequirement::NonText, 'Chart series 3'],
        ['chart-4', 'surface', ContrastRequirement::NonText, 'Chart series 4'],
        ['chart-5', 'surface', ContrastRequirement::NonText, 'Chart series 5'],
        ['chart-6', 'surface', ContrastRequirement::NonText, 'Chart series 6'],
        ['chart-7', 'surface', ContrastRequirement::NonText, 'Chart series 7'],
        ['chart-8', 'surface', ContrastRequirement::NonText, 'Chart series 8'],
    ];

    /**
     * The original gcego/chroma color names, which keep their gcego-* utilities
     * (bg-gcego-surface, text-gcego-text-muted). Newer tokens only get canonical
     * utilities: a gcego-* spelling for them would collide with gcego/chroma's
     * non-color tokens (shadow-gcego-card, shadow-gcego-overlay).
     */
    public const array GcegoPrefixed = [
        'background', 'surface', 'surface-muted', 'text', 'text-muted', 'border', 'border-strong',
        'primary', 'primary-hover', 'primary-foreground', 'primary-soft', 'ring',
        'success', 'warning', 'danger', 'danger-foreground', 'info',
    ];

    /**
     * Categorical chart tokens, in series order.
     */
    public const array ChartSeries = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6', 'chart-7', 'chart-8'];

    /**
     * Status colors, checked together for color-vision collapse.
     */
    public const array StatusColors = ['success', 'warning', 'danger', 'info'];

    /**
     * @return array<string, Token>
     */
    public static function all(): array
    {
        $tokens = [];

        foreach (self::Definitions as $name => [$group, $light, $dark, $description]) {
            $tokens[$name] = new Token($name, $group, $light, $dark, $description);
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::Definitions);
    }

    public static function has(string $name): bool
    {
        return isset(self::Definitions[$name]);
    }

    public static function light(): TokenSet
    {
        return new TokenSet(array_map(static fn (array $definition): string => $definition[1], self::Definitions));
    }

    public static function dark(): TokenSet
    {
        return new TokenSet(array_map(static fn (array $definition): string => $definition[2] ?? $definition[1], self::Definitions));
    }
}
