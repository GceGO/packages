<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A link shown with a notification ("View report"):
 *
 *     new NotificationAction('View report', '/admin/reports/42')
 *
 * The URL must be root-relative, https, or on the application's own
 * origin. It is a link only: the destination page authorizes the user
 * again, so a notification never grants access to anything. Opening an
 * action from the notification center marks the notification read.
 */
final class NotificationAction extends Component
{
    public const string Url = 'url';

    /** Present it as the main action. */
    public const string Primary = 'primary';

    /** Open in a new tab (with rel="noopener"). */
    public const string NewTab = 'new_tab';

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : '';
    }

    public function url(): string
    {
        $url = $this->get(self::Url);

        return is_string($url) ? $url : '';
    }

    /**
     * @return array{label: string, url: string, primary: bool, new_tab: bool}
     */
    public function toArray(): array
    {
        return ['label' => $this->label(), 'url' => $this->url(), 'primary' => $this->get(self::Primary) === true, 'new_tab' => $this->get(self::NewTab) === true];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('notifications.action')
            ->withoutProperties(self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 60, ruleDescription: 'a label of at most 60 characters'),
                new PropertyDefinition(self::Url, Type::string(), required: true, rule: static fn (mixed $value): bool => SafeUrl::check($value), ruleDescription: 'a root-relative path, an https URL or a URL of this application'),
                new PropertyDefinition(self::Primary, Type::bool(), default: false),
                new PropertyDefinition(self::NewTab, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Url];
    }
}
