<?php

declare(strict_types=1);

namespace Gcego\Infolists;

use Gcego\Actions\Action;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Building\InfolistBuilder;
use Gcego\Infolists\Contracts\InfolistComponent;
use Gcego\Infolists\Schema\SchemaCompiler;
use Gcego\Security\Contracts\Requirement;
use Throwable;

/**
 * A declarative record-detail view, and the "infolist" part of a blueprint:
 *
 *     new Blueprint('subjects', new Ability('viewAny', Subject::class), new Source(Subject::class), new Infolist(
 *         new Section('Subject Information',
 *             new Text('name', 'Subject Name'),
 *             new Text('code', 'Subject Code'),
 *             new Badge('status', 'Status'),
 *         ),
 *         new Section('Record Information',
 *             new DateTime('created_at', 'Created At'),
 *             new DateTime('updated_at', 'Last Updated'),
 *         ),
 *     ))
 *
 * Children are entries and layouts (and gcego/actions actions, shown with
 * the record). A definition only: constructing it never queries, renders or
 * mounts Livewire. Records come from the blueprint's Source through
 * gcego/data and gcego/security, by key, for the current user.
 */
final class Infolist extends Component implements BlueprintPart
{
    use Makeable;

    /** Grid columns for top-level entries (default gcego-infolists.layouts.default_columns). */
    public const string Columns = 'columns';

    /** Also find soft-deleted records (they still need the view ability). */
    public const string WithTrashed = 'with_trashed';

    public const string EmptyHeading = 'empty_heading';

    /** Styling slots defined by infolists (Tailwind::Wrapper, Header, Title, Description, Body, Footer, Label apply too). */
    public const string Section = 'section';

    public const string SectionHeader = 'section-header';

    public const string SectionBody = 'section-body';

    public const string Entry = 'entry';

    public const string EntryLabel = 'entry-label';

    public const string EntryValue = 'entry-value';

    public const string EntryDescription = 'entry-description';

    public const string EmptyState = 'empty';

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Infolist);
    }

    public function infolistName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : 'infolist';
    }

    public function columns(int $default): int
    {
        $columns = $this->get(self::Columns);

        return is_int($columns) ? $columns : $default;
    }

    public static function builderClass(): string
    {
        return InfolistBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('infolists.infolist')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), default: 'infolist', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,31}$/', $value) === 1, ruleDescription: 'a lowercase name'),
                new PropertyDefinition(self::Columns, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'),
                new PropertyDefinition(self::WithTrashed, Type::bool(), default: false),
                new PropertyDefinition(self::EmptyHeading, Type::string()),
            )
            ->withAllowedChildren([InfolistComponent::class, Action::class], 1)
            ->withAllowedConfigurations([Tailwind::class, Requirement::class])
            ->withConstraint(static function (ComponentInterface $infolist): ?string {
                if (! $infolist instanceof self) {
                    return null;
                }

                try {
                    (new SchemaCompiler)->compile($infolist);
                } catch (Throwable $exception) {
                    return $exception->getMessage();
                }

                return null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }
}
