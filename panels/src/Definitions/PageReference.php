<?php

declare(strict_types=1);

namespace Gcego\Panels\Definitions;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Pages\Page;

/**
 * Includes a registered gcego/pages page in a panel (a dashboard, a report,
 * a custom page): it is routed under the panel and listed in the panel's
 * navigation (unless it needs a record or parameters, or opts out here).
 *
 *     new PageReference('reports')
 *     new PageReference('audit', new Property(PageReference::Navigation, false))   // routed, not listed
 *
 * The page keeps its own requirements; the same page may be in several panels.
 */
final class PageReference extends Component
{
    /** List the page in the panel's navigation (default: true). */
    public const string Navigation = 'navigation';

    public function listed(): bool
    {
        return $this->get(self::Navigation) !== false;
    }

    public function page(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('panels.page')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 100 && preg_match(Page::NamePattern, $value) === 1, ruleDescription: 'a page name such as "overview" or "reports.monthly"'),
                new PropertyDefinition(self::Navigation, Type::bool(), default: true),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
