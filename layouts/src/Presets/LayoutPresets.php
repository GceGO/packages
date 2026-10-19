<?php

declare(strict_types=1);

namespace Gcego\Layouts\Presets;

/**
 * Literal Tailwind classes for gcego/layouts, scanned by gcego.css through
 * "layouts/src/Presets". Every class a layout can emit is listed here (or
 * in the package views), so the compiler always finds it: responsive values
 * are looked up in these bounded maps, never built from strings at runtime.
 *
 * Generated once from the bounded scales below and kept as literals.
 */
final class LayoutPresets
{
    /** The spacing steps gaps accept. */
    public const array GapSteps = [0, 1, 2, 3, 4, 6, 8, 10, 12];

    /** The spacing steps spacers accept. */
    public const array SpacerSteps = [2, 4, 6, 8, 12, 16];

    /** The vertical flow of a Layout and a Stack. */
    public const array Flow = ['flex', 'min-w-0', 'flex-col'];

    /** Every grid. */
    public const array GridBase = ['grid', 'min-w-0'];

    /** Every row. */
    public const array RowBase = ['flex', 'min-w-0'];

    /** A placed child (span or visibility). */
    public const array Item = ['min-w-0'];

    /** Layout and section titles. */
    public const array Heading = ['text-lg', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    /** The title of a root Layout. */
    public const array PageHeading = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    /** Descriptions under titles. */
    public const array Description = ['text-sm', 'text-gcego-text-muted'];

    /** Section and card headers. */
    public const array Header = ['flex', 'flex-wrap', 'items-start', 'justify-between', 'gap-x-4', 'gap-y-2'];

    /** Title, icon and description in a header. */
    public const array HeaderText = ['flex', 'min-w-0', 'flex-1', 'items-start', 'gap-3'];

    /** Header icons. */
    public const array IconBadge = ['inline-flex', 'size-9', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'bg-gcego-primary/10', 'text-gcego-primary'];

    /** Header actions. */
    public const array Actions = ['flex', 'shrink-0', 'flex-wrap', 'items-center', 'gap-2'];

    /** Section and card bodies. */
    public const array Body = ['flex', 'min-w-0', 'flex-col', 'gap-6'];

    /** Section and card footers. */
    public const array Footer = ['flex', 'flex-wrap', 'items-center', 'justify-end', 'gap-2', 'border-t', 'border-gcego-border', 'pt-4', 'text-sm', 'text-gcego-text-muted'];

    /** A section (spacing between header and body). */
    public const array SectionPlain = ['flex', 'min-w-0', 'flex-col', 'gap-4'];

    /** Disclosure summaries (collapsible sections, accordions). */
    public const array Summary = ['flex', 'cursor-pointer', 'list-none', 'items-center', 'justify-between', 'gap-3', 'rounded-gcego', 'outline-none', 'select-none', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', '[&::-webkit-details-marker]:hidden'];

    /** The disclosure chevron. */
    public const array Chevron = ['size-4', 'shrink-0', 'text-gcego-text-muted', 'transition-transform', 'duration-200', 'motion-reduce:transition-none', 'group-open:rotate-180'];

    /** Main and aside regions. */
    public const array Region = ['min-w-0'];

    /** Split and sidebar grids (stacked on small screens). */
    public const array SplitBase = ['grid', 'min-w-0', 'grid-cols-1'];

    /** The tab list (scrolls horizontally when tabs overflow). */
    public const array TabList = ['flex', 'gap-1', 'overflow-x-auto', 'border-b', 'border-gcego-border', '[scrollbar-width:thin]'];

    /** A tab. */
    public const array Tab = ['inline-flex', 'min-h-10', 'shrink-0', 'items-center', 'gap-2', 'border-b-2', 'border-transparent', 'px-3', 'text-sm', 'font-medium', 'whitespace-nowrap', 'text-gcego-text-muted', '-mb-px', 'hover:text-gcego-text', 'aria-selected:border-gcego-primary', 'aria-selected:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:-outline-offset-2', 'focus-visible:outline-gcego-ring'];

    /** A tab panel. */
    public const array TabPanel = ['flex', 'min-w-0', 'flex-col', 'gap-6', 'pt-5', 'focus-visible:outline-2', 'focus-visible:outline-offset-4', 'focus-visible:outline-gcego-ring'];

    /** An accordion. */
    public const array Accordion = ['divide-y', 'divide-gcego-border', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    /** An accordion item. */
    public const array AccordionItem = ['group'];

    /** An accordion item summary. */
    public const array AccordionSummary = ['px-4', 'py-3', 'text-sm', 'font-medium', 'text-gcego-text', 'hover:bg-gcego-surface-muted'];

    /** An accordion item body. */
    public const array AccordionBody = ['flex', 'min-w-0', 'flex-col', 'gap-4', 'px-4', 'pt-1', 'pb-4', 'text-sm', 'text-gcego-text'];

    /** A collapsible region. */
    public const array Collapsible = ['group', 'min-w-0'];

    /** A collapsible summary. */
    public const array CollapsibleSummary = ['py-2', 'text-sm', 'font-medium', 'text-gcego-text'];

    /** A collapsible body. */
    public const array CollapsibleBody = ['flex', 'min-w-0', 'flex-col', 'gap-4', 'pt-2'];

    /** A horizontal divider. */
    public const array Divider = ['border-0', 'border-t', 'border-gcego-border'];

    /** A vertical divider. */
    public const array VerticalDivider = ['w-px', 'self-stretch', 'bg-gcego-border'];

    /** A divider with a label. */
    public const array LabelledDivider = ['flex', 'items-center', 'gap-3', 'text-xs', 'font-medium', 'uppercase', 'tracking-wide', 'text-gcego-text-muted', 'before:h-px', 'before:flex-1', 'before:bg-gcego-border', 'after:h-px', 'after:flex-1', 'after:bg-gcego-border'];

    /** A spacer. */
    public const array Spacer = ['shrink-0'];

    /** The side column of a Sidebar (keeps its width). */
    public const array SidebarRegion = ['shrink-0'];

    /** A divided stack (a rule between children instead of a gap). */
    public const array Divided = ['divide-y', 'divide-gcego-border', '[&>*]:py-4', '[&>*:first-child]:pt-0', '[&>*:last-child]:pb-0'];

    /** A centred container. */
    public const array Centered = ['mx-auto', 'w-full'];

    /** A container that is not centred. */
    public const array FullWidth = ['w-full'];

    /** A row that never stacks. */
    public const array RowDirection = ['flex-row', 'items-center'];

    /** A row while it is stacked. */
    public const array RowStacked = ['flex-col', 'items-stretch'];

    /** A wrapping row. */
    public const array Wrap = ['flex-wrap'];

    /** A row that never wraps. */
    public const array NoWrap = ['flex-nowrap'];

    /** A dense grid. */
    public const array Dense = ['grid-flow-row-dense'];

    /** A growing spacer. */
    public const array Grow = ['flex-1'];

    /** A scroll area. */
    public const array ScrollArea = ['min-w-0', 'overscroll-contain', 'rounded-gcego', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    /** The background of a sticky region, so content scrolling under it stays readable. */
    public const array StickySurface = ['bg-gcego-surface/95', 'backdrop-blur-sm', 'supports-[backdrop-filter]:bg-gcego-surface/80'];

    /** An empty state. */
    public const array EmptyState = ['flex', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-gcego-border-strong', 'px-6', 'py-10', 'text-center'];

    /** The empty state icon. */
    public const array EmptyIcon = ['inline-flex', 'size-10', 'items-center', 'justify-center', 'rounded-full', 'bg-gcego-surface-muted', 'text-gcego-text-muted'];

    /** The empty state title. */
    public const array EmptyTitle = ['text-sm', 'font-semibold', 'text-gcego-text'];

    /** The empty state description. */
    public const array EmptyDescription = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    /** The empty state actions. */
    public const array EmptyActions = ['mt-2', 'flex', 'flex-wrap', 'items-center', 'justify-center', 'gap-2'];

    /** The empty text of a section or card. */
    public const array EmptyText = ['py-6', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    /** A loading placeholder. */
    public const array Loading = ['flex', 'flex-col', 'gap-3'];

    /** A skeleton block. */
    public const array Skeleton = ['rounded-gcego', 'bg-gcego-surface-muted', 'motion-safe:animate-pulse'];

    /**
     * Grid columns by breakpoint and count (1 to 6).
     *
     * @var array<string, array<int, string>>
     */
    public const array GridColumns = [
        'default' => [
            1 => 'grid-cols-1',
            2 => 'grid-cols-2',
            3 => 'grid-cols-3',
            4 => 'grid-cols-4',
            5 => 'grid-cols-5',
            6 => 'grid-cols-6',
        ],
        'sm' => [
            1 => 'sm:grid-cols-1',
            2 => 'sm:grid-cols-2',
            3 => 'sm:grid-cols-3',
            4 => 'sm:grid-cols-4',
            5 => 'sm:grid-cols-5',
            6 => 'sm:grid-cols-6',
        ],
        'md' => [
            1 => 'md:grid-cols-1',
            2 => 'md:grid-cols-2',
            3 => 'md:grid-cols-3',
            4 => 'md:grid-cols-4',
            5 => 'md:grid-cols-5',
            6 => 'md:grid-cols-6',
        ],
        'lg' => [
            1 => 'lg:grid-cols-1',
            2 => 'lg:grid-cols-2',
            3 => 'lg:grid-cols-3',
            4 => 'lg:grid-cols-4',
            5 => 'lg:grid-cols-5',
            6 => 'lg:grid-cols-6',
        ],
        'xl' => [
            1 => 'xl:grid-cols-1',
            2 => 'xl:grid-cols-2',
            3 => 'xl:grid-cols-3',
            4 => 'xl:grid-cols-4',
            5 => 'xl:grid-cols-5',
            6 => 'xl:grid-cols-6',
        ],
        '2xl' => [
            1 => '2xl:grid-cols-1',
            2 => '2xl:grid-cols-2',
            3 => '2xl:grid-cols-3',
            4 => '2xl:grid-cols-4',
            5 => '2xl:grid-cols-5',
            6 => '2xl:grid-cols-6',
        ],
    ];

    /**
     * Column spans by breakpoint: 1 to 6, "full" and "auto".
     *
     * @var array<string, array<int|string, string>>
     */
    public const array ColumnSpans = [
        'default' => [
            1 => 'col-span-1',
            2 => 'col-span-2',
            3 => 'col-span-3',
            4 => 'col-span-4',
            5 => 'col-span-5',
            6 => 'col-span-6',
            'full' => 'col-span-full',
            'auto' => 'col-auto',
        ],
        'sm' => [
            1 => 'sm:col-span-1',
            2 => 'sm:col-span-2',
            3 => 'sm:col-span-3',
            4 => 'sm:col-span-4',
            5 => 'sm:col-span-5',
            6 => 'sm:col-span-6',
            'full' => 'sm:col-span-full',
            'auto' => 'sm:col-auto',
        ],
        'md' => [
            1 => 'md:col-span-1',
            2 => 'md:col-span-2',
            3 => 'md:col-span-3',
            4 => 'md:col-span-4',
            5 => 'md:col-span-5',
            6 => 'md:col-span-6',
            'full' => 'md:col-span-full',
            'auto' => 'md:col-auto',
        ],
        'lg' => [
            1 => 'lg:col-span-1',
            2 => 'lg:col-span-2',
            3 => 'lg:col-span-3',
            4 => 'lg:col-span-4',
            5 => 'lg:col-span-5',
            6 => 'lg:col-span-6',
            'full' => 'lg:col-span-full',
            'auto' => 'lg:col-auto',
        ],
        'xl' => [
            1 => 'xl:col-span-1',
            2 => 'xl:col-span-2',
            3 => 'xl:col-span-3',
            4 => 'xl:col-span-4',
            5 => 'xl:col-span-5',
            6 => 'xl:col-span-6',
            'full' => 'xl:col-span-full',
            'auto' => 'xl:col-auto',
        ],
        '2xl' => [
            1 => '2xl:col-span-1',
            2 => '2xl:col-span-2',
            3 => '2xl:col-span-3',
            4 => '2xl:col-span-4',
            5 => '2xl:col-span-5',
            6 => '2xl:col-span-6',
            'full' => '2xl:col-span-full',
            'auto' => '2xl:col-auto',
        ],
    ];

    /**
     * Gaps by breakpoint and spacing step.
     *
     * @var array<string, array<int, string>>
     */
    public const array Gaps = [
        'default' => [
            0 => 'gap-0',
            1 => 'gap-1',
            2 => 'gap-2',
            3 => 'gap-3',
            4 => 'gap-4',
            6 => 'gap-6',
            8 => 'gap-8',
            10 => 'gap-10',
            12 => 'gap-12',
        ],
        'sm' => [
            0 => 'sm:gap-0',
            1 => 'sm:gap-1',
            2 => 'sm:gap-2',
            3 => 'sm:gap-3',
            4 => 'sm:gap-4',
            6 => 'sm:gap-6',
            8 => 'sm:gap-8',
            10 => 'sm:gap-10',
            12 => 'sm:gap-12',
        ],
        'md' => [
            0 => 'md:gap-0',
            1 => 'md:gap-1',
            2 => 'md:gap-2',
            3 => 'md:gap-3',
            4 => 'md:gap-4',
            6 => 'md:gap-6',
            8 => 'md:gap-8',
            10 => 'md:gap-10',
            12 => 'md:gap-12',
        ],
        'lg' => [
            0 => 'lg:gap-0',
            1 => 'lg:gap-1',
            2 => 'lg:gap-2',
            3 => 'lg:gap-3',
            4 => 'lg:gap-4',
            6 => 'lg:gap-6',
            8 => 'lg:gap-8',
            10 => 'lg:gap-10',
            12 => 'lg:gap-12',
        ],
        'xl' => [
            0 => 'xl:gap-0',
            1 => 'xl:gap-1',
            2 => 'xl:gap-2',
            3 => 'xl:gap-3',
            4 => 'xl:gap-4',
            6 => 'xl:gap-6',
            8 => 'xl:gap-8',
            10 => 'xl:gap-10',
            12 => 'xl:gap-12',
        ],
        '2xl' => [
            0 => '2xl:gap-0',
            1 => '2xl:gap-1',
            2 => '2xl:gap-2',
            3 => '2xl:gap-3',
            4 => '2xl:gap-4',
            6 => '2xl:gap-6',
            8 => '2xl:gap-8',
            10 => '2xl:gap-10',
            12 => '2xl:gap-12',
        ],
    ];

    /**
     * Vertical gaps between rows.
     *
     * @var array<int, string>
     */
    public const array RowGaps = [
        0 => 'gap-y-0',
        1 => 'gap-y-1',
        2 => 'gap-y-2',
        3 => 'gap-y-3',
        4 => 'gap-y-4',
        6 => 'gap-y-6',
        8 => 'gap-y-8',
        10 => 'gap-y-10',
        12 => 'gap-y-12',
    ];

    /**
     * Horizontal gaps between columns.
     *
     * @var array<int, string>
     */
    public const array ColumnGaps = [
        0 => 'gap-x-0',
        1 => 'gap-x-1',
        2 => 'gap-x-2',
        3 => 'gap-x-3',
        4 => 'gap-x-4',
        6 => 'gap-x-6',
        8 => 'gap-x-8',
        10 => 'gap-x-10',
        12 => 'gap-x-12',
    ];

    /**
     * Cross-axis alignment by breakpoint.
     *
     * @var array<string, array<string, string>>
     */
    public const array Items = [
        'default' => [
            'start' => 'items-start',
            'center' => 'items-center',
            'end' => 'items-end',
            'stretch' => 'items-stretch',
            'baseline' => 'items-baseline',
        ],
        'sm' => [
            'start' => 'sm:items-start',
            'center' => 'sm:items-center',
            'end' => 'sm:items-end',
            'stretch' => 'sm:items-stretch',
            'baseline' => 'sm:items-baseline',
        ],
        'md' => [
            'start' => 'md:items-start',
            'center' => 'md:items-center',
            'end' => 'md:items-end',
            'stretch' => 'md:items-stretch',
            'baseline' => 'md:items-baseline',
        ],
        'lg' => [
            'start' => 'lg:items-start',
            'center' => 'lg:items-center',
            'end' => 'lg:items-end',
            'stretch' => 'lg:items-stretch',
            'baseline' => 'lg:items-baseline',
        ],
        'xl' => [
            'start' => 'xl:items-start',
            'center' => 'xl:items-center',
            'end' => 'xl:items-end',
            'stretch' => 'xl:items-stretch',
            'baseline' => 'xl:items-baseline',
        ],
        '2xl' => [
            'start' => '2xl:items-start',
            'center' => '2xl:items-center',
            'end' => '2xl:items-end',
            'stretch' => '2xl:items-stretch',
            'baseline' => '2xl:items-baseline',
        ],
    ];

    /**
     * Main-axis distribution by breakpoint.
     *
     * @var array<string, array<string, string>>
     */
    public const array Justify = [
        'default' => [
            'start' => 'justify-start',
            'center' => 'justify-center',
            'end' => 'justify-end',
            'between' => 'justify-between',
            'around' => 'justify-around',
            'evenly' => 'justify-evenly',
        ],
        'sm' => [
            'start' => 'sm:justify-start',
            'center' => 'sm:justify-center',
            'end' => 'sm:justify-end',
            'between' => 'sm:justify-between',
            'around' => 'sm:justify-around',
            'evenly' => 'sm:justify-evenly',
        ],
        'md' => [
            'start' => 'md:justify-start',
            'center' => 'md:justify-center',
            'end' => 'md:justify-end',
            'between' => 'md:justify-between',
            'around' => 'md:justify-around',
            'evenly' => 'md:justify-evenly',
        ],
        'lg' => [
            'start' => 'lg:justify-start',
            'center' => 'lg:justify-center',
            'end' => 'lg:justify-end',
            'between' => 'lg:justify-between',
            'around' => 'lg:justify-around',
            'evenly' => 'lg:justify-evenly',
        ],
        'xl' => [
            'start' => 'xl:justify-start',
            'center' => 'xl:justify-center',
            'end' => 'xl:justify-end',
            'between' => 'xl:justify-between',
            'around' => 'xl:justify-around',
            'evenly' => 'xl:justify-evenly',
        ],
        '2xl' => [
            'start' => '2xl:justify-start',
            'center' => '2xl:justify-center',
            'end' => '2xl:justify-end',
            'between' => '2xl:justify-between',
            'around' => '2xl:justify-around',
            'evenly' => '2xl:justify-evenly',
        ],
    ];

    /**
     * Spacer sizes by breakpoint (the flex basis: height in a stack, width in a row).
     *
     * @var array<string, array<int, string>>
     */
    public const array SpacerSizes = [
        'default' => [
            2 => 'basis-2',
            4 => 'basis-4',
            6 => 'basis-6',
            8 => 'basis-8',
            12 => 'basis-12',
            16 => 'basis-16',
        ],
        'sm' => [
            2 => 'sm:basis-2',
            4 => 'sm:basis-4',
            6 => 'sm:basis-6',
            8 => 'sm:basis-8',
            12 => 'sm:basis-12',
            16 => 'sm:basis-16',
        ],
        'md' => [
            2 => 'md:basis-2',
            4 => 'md:basis-4',
            6 => 'md:basis-6',
            8 => 'md:basis-8',
            12 => 'md:basis-12',
            16 => 'md:basis-16',
        ],
        'lg' => [
            2 => 'lg:basis-2',
            4 => 'lg:basis-4',
            6 => 'lg:basis-6',
            8 => 'lg:basis-8',
            12 => 'lg:basis-12',
            16 => 'lg:basis-16',
        ],
        'xl' => [
            2 => 'xl:basis-2',
            4 => 'xl:basis-4',
            6 => 'xl:basis-6',
            8 => 'xl:basis-8',
            12 => 'xl:basis-12',
            16 => 'xl:basis-16',
        ],
        '2xl' => [
            2 => '2xl:basis-2',
            4 => '2xl:basis-4',
            6 => '2xl:basis-6',
            8 => '2xl:basis-8',
            12 => '2xl:basis-12',
            16 => '2xl:basis-16',
        ],
    ];

    /**
     * Hidden below a breakpoint.
     *
     * @var array<string, string>
     */
    public const array HiddenBelow = [
        'sm' => 'max-sm:hidden',
        'md' => 'max-md:hidden',
        'lg' => 'max-lg:hidden',
        'xl' => 'max-xl:hidden',
        '2xl' => 'max-2xl:hidden',
    ];

    /**
     * Hidden from a breakpoint up.
     *
     * @var array<string, string>
     */
    public const array HiddenFrom = [
        'sm' => 'sm:hidden',
        'md' => 'md:hidden',
        'lg' => 'lg:hidden',
        'xl' => 'xl:hidden',
        '2xl' => '2xl:hidden',
    ];

    /**
     * Auto-fit grids by minimum item width.
     *
     * @var array<string, string>
     */
    public const array AutoFit = [
        'xs' => 'grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]',
        'sm' => 'grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))]',
        'md' => 'grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]',
        'lg' => 'grid-cols-[repeat(auto-fit,minmax(min(100%,24rem),1fr))]',
    ];

    /**
     * Rows that stack below a breakpoint lay out horizontally from it.
     *
     * @var array<string, string>
     */
    public const array RowFrom = [
        'sm' => 'sm:flex-row',
        'md' => 'md:flex-row',
        'lg' => 'lg:flex-row',
        'xl' => 'xl:flex-row',
    ];

    /**
     * Split ratios as grid columns and the span of each region (first, second); they reuse the grid classes.
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    public const array SplitRatios = [
        '1:1' => [2, 1, 1],
        '2:1' => [3, 2, 1],
        '3:1' => [4, 3, 1],
        '3:2' => [5, 3, 2],
        '1:2' => [3, 1, 2],
        '1:3' => [4, 1, 3],
        '2:3' => [5, 2, 3],
    ];

    /**
     * Sidebar widths from the breakpoint where it sits beside the main region.
     *
     * @var array<string, array<string, string>>
     */
    public const array SidebarWidths = [
        'md' => [
            'sm' => 'md:w-64',
            'md' => 'md:w-72',
            'lg' => 'md:w-80',
            'xl' => 'md:w-96',
        ],
        'lg' => [
            'sm' => 'lg:w-64',
            'md' => 'lg:w-72',
            'lg' => 'lg:w-80',
            'xl' => 'lg:w-96',
        ],
        'xl' => [
            'sm' => 'xl:w-64',
            'md' => 'xl:w-72',
            'lg' => 'xl:w-80',
            'xl' => 'xl:w-96',
        ],
    ];

    /**
     * A sticky side region from the breakpoint where regions sit side by side.
     *
     * @var array<string, string>
     */
    public const array StickyRegion = [
        'md' => 'md:sticky md:top-6 md:self-start',
        'lg' => 'lg:sticky lg:top-6 lg:self-start',
        'xl' => 'xl:sticky xl:top-6 xl:self-start',
    ];

    /**
     * Sticky from a breakpoint (default: always).
     *
     * @var array<string, string>
     */
    public const array StickyFrom = [
        'default' => 'sticky',
        'sm' => 'sm:sticky',
        'md' => 'md:sticky',
        'lg' => 'lg:sticky',
        'xl' => 'xl:sticky',
    ];

    /**
     * Sticky offsets by edge and step.
     *
     * @var array<string, array<int, string>>
     */
    public const array StickyOffsets = [
        'top' => [
            0 => 'top-0',
            2 => 'top-2',
            4 => 'top-4',
            6 => 'top-6',
            8 => 'top-8',
            12 => 'top-12',
            16 => 'top-16',
        ],
        'bottom' => [
            0 => 'bottom-0',
            2 => 'bottom-2',
            4 => 'bottom-4',
            6 => 'bottom-6',
            8 => 'bottom-8',
            12 => 'bottom-12',
            16 => 'bottom-16',
        ],
    ];

    /**
     * Stacking layers for sticky regions.
     *
     * @var array<string, string>
     */
    public const array Layers = [
        'base' => 'z-10',
        'raised' => 'z-20',
        'overlay' => 'z-30',
    ];

    /**
     * Container maximum widths.
     *
     * @var array<string, string>
     */
    public const array MaxWidths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
        'prose' => 'max-w-prose',
        'full' => 'max-w-none',
    ];

    /**
     * Container horizontal padding.
     *
     * @var array<string, string>
     */
    public const array ContainerPaddings = [
        'none' => 'px-0',
        'sm' => 'px-4',
        'md' => 'px-4 sm:px-6 lg:px-8',
        'lg' => 'px-6 sm:px-8 lg:px-12',
    ];

    /**
     * Card and section padding.
     *
     * @var array<string, string>
     */
    public const array Paddings = [
        'none' => 'p-0',
        'sm' => 'p-4',
        'md' => 'p-5 sm:p-6',
        'lg' => 'p-6 sm:p-8',
    ];

    /**
     * Section surfaces.
     *
     * @var array<string, string>
     */
    public const array Surfaces = [
        'card' => 'rounded-gcego-lg border border-gcego-border bg-gcego-surface shadow-gcego-card',
        'muted' => 'rounded-gcego-lg bg-gcego-surface-muted',
        'none' => '',
    ];

    /**
     * Card variants.
     *
     * @var array<string, string>
     */
    public const array CardVariants = [
        'default' => 'rounded-gcego-lg border border-gcego-border bg-gcego-surface shadow-gcego-card',
        'muted' => 'rounded-gcego-lg border border-transparent bg-gcego-surface-muted',
        'outline' => 'rounded-gcego-lg border border-gcego-border-strong bg-transparent',
        'elevated' => 'rounded-gcego-lg border border-gcego-border bg-gcego-surface shadow-lg',
        'info' => 'rounded-gcego-lg border border-gcego-info/30 border-l-4 border-l-gcego-info bg-gcego-surface shadow-gcego-card',
        'success' => 'rounded-gcego-lg border border-gcego-success/30 border-l-4 border-l-gcego-success bg-gcego-surface shadow-gcego-card',
        'warning' => 'rounded-gcego-lg border border-gcego-warning/40 border-l-4 border-l-gcego-warning bg-gcego-surface shadow-gcego-card',
        'danger' => 'rounded-gcego-lg border border-gcego-danger/30 border-l-4 border-l-gcego-danger bg-gcego-surface shadow-gcego-card',
    ];

    /**
     * Divider spacing by orientation.
     *
     * @var array<string, array<string, string>>
     */
    public const array DividerSpacing = [
        'horizontal' => [
            'none' => 'my-0',
            'sm' => 'my-2',
            'md' => 'my-4',
            'lg' => 'my-8',
        ],
        'vertical' => [
            'none' => 'mx-0',
            'sm' => 'mx-2',
            'md' => 'mx-4',
            'lg' => 'mx-8',
        ],
    ];

    /**
     * Scroll area maximum heights.
     *
     * @var array<string, string>
     */
    public const array ScrollHeights = [
        'none' => '',
        'sm' => 'max-h-64',
        'md' => 'max-h-96',
        'lg' => 'max-h-[32rem]',
        'xl' => 'max-h-[40rem]',
    ];

    /**
     * Scroll directions.
     *
     * @var array<string, string>
     */
    public const array ScrollDirections = [
        'vertical' => 'overflow-y-auto overflow-x-hidden',
        'horizontal' => 'overflow-x-auto overflow-y-hidden',
        'both' => 'overflow-auto',
    ];

    /**
     * Skeleton line widths, cycled.
     *
     * @var list<string>
     */
    public const array SkeletonLines = ['h-4 w-full', 'h-4 w-11/12', 'h-4 w-4/5', 'h-4 w-2/3'];

    /**
     * Skeleton shapes by variant.
     *
     * @var array<string, string>
     */
    public const array SkeletonVariants = [
        'card' => 'h-40 w-full rounded-gcego-lg',
        'grid' => 'h-28 w-full rounded-gcego-lg',
        'table' => 'h-10 w-full',
    ];
}
