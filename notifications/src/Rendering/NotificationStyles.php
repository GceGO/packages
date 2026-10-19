<?php

declare(strict_types=1);

namespace Gcego\Notifications\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Components\Property;
use Gcego\Notifications\Notification;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\HtmlString;

/**
 * Classes of notification views: the presets merged (gcego/chroma rules)
 * with the application's overrides of the Notification slots
 * (ChromaManager::override(Notification::class, new Slot(...))).
 */
final class NotificationStyles
{
    /** @var array<string, string> */
    private array $merged = [];

    private ?Notification $prototype = null;

    public function __construct(private readonly ChromaManager $styling, private readonly Icons $icons) {}

    /**
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset, string ...$extra): string
    {
        $computed = trim(implode(' ', [...$preset, ...$extra]));
        $this->prototype ??= new Notification(new Property(Notification::Title, 'Prototype'));
        $own = $this->styling->classes($this->prototype, $slot);

        return $this->merged[$slot."\0".$computed] ??= $own === '' ? $computed : $this->styling->merge($computed, $own);
    }

    public function svg(string $icon, string $class = 'size-5'): HtmlString
    {
        return $this->icons->svg($icon, $class);
    }
}
