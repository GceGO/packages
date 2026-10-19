<?php

declare(strict_types=1);

namespace Gcego\Navigation\Badges;

use BackedEnum;
use Gcego\Luminaries\IconName;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Presets\NavigationPresets;

/**
 * A badge next to a navigation item: a count or a short status.
 *
 *     NavigationBadge::count(12, 'pending requests')     // "12", read as "12 pending requests"
 *     new NavigationBadge('New', 'success')
 *
 * Counts above $max show as "99+". The label is the accessible meaning, so
 * the badge never relies on its color.
 */
final readonly class NavigationBadge
{
    public string $text;

    public ?string $icon;

    public function __construct(
        int|string $value,
        public string $variant = 'default',
        public ?string $label = null,
        string|BackedEnum|null $icon = null,
        int $max = 99,
    ) {
        if (! isset(NavigationPresets::BadgeVariants[$variant])) {
            throw InvalidNavigationException::invalid('NavigationBadge', sprintf('variant "%s" is not one of %s.', $variant, implode(', ', array_keys(NavigationPresets::BadgeVariants))));
        }

        $text = is_int($value) ? ($value > $max ? $max.'+' : (string) $value) : trim($value);

        if ($text === '' || mb_strlen($text) > 12) {
            throw InvalidNavigationException::invalid('NavigationBadge', 'its text must be 1 to 12 characters.');
        }

        $this->text = $text;
        $this->icon = $icon === null ? null : IconName::of($icon);
    }

    /**
     * A count, or null for zero (no badge).
     */
    public static function count(int $count, ?string $label = null, string $variant = 'primary', int $max = 99): ?self
    {
        return $count <= 0 ? null : new self($count, $variant, $label === null ? null : $count.' '.$label, max: $max);
    }

    /**
     * The variant's classes; over an icon, its solid form (NavigationPresets::IconBadgeVariants).
     */
    public function classes(bool $onIcon = false): string
    {
        return ($onIcon ? NavigationPresets::IconBadgeVariants : NavigationPresets::BadgeVariants)[$this->variant];
    }

    /**
     * @return array{text: string, variant: string, label: string|null, icon: string|null}
     */
    public function toArray(): array
    {
        return ['text' => $this->text, 'variant' => $this->variant, 'label' => $this->label, 'icon' => $this->icon];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $text = $data['text'] ?? null;
        $variant = $data['variant'] ?? 'default';

        return is_string($text) && is_string($variant) ? new self($text, $variant, is_string($data['label'] ?? null) ? $data['label'] : null, is_string($data['icon'] ?? null) ? $data['icon'] : null) : null;
    }
}
