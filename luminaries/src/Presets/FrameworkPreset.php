<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Presets;

/**
 * Default semantic aliases for GCEGO Admin Studio.
 *
 * Targets use Heroicons outline so semantic icons match the rest of the
 * framework UI; applications override any alias in config('luminaries.aliases').
 * Every target is verified against the installed collections by the test suite
 * and by `php artisan gcego:icons:check`.
 */
final class FrameworkPreset
{
    public const string Name = 'framework';

    public const array Aliases = [
        // Navigation
        'navigation.dashboard' => 'heroicon:squares-2x2',
        'navigation.home' => 'heroicon:home',
        'navigation.menu' => 'heroicon:bars-3',
        'navigation.close' => 'heroicon:x-mark',
        'navigation.collapse' => 'heroicon:chevron-double-left',
        'navigation.expand' => 'heroicon:chevron-double-right',
        'navigation.back' => 'heroicon:arrow-left',
        'navigation.forward' => 'heroicon:arrow-right',
        'navigation.external' => 'heroicon:arrow-top-right-on-square',
        'navigation.breadcrumb-separator' => 'heroicon-mini:chevron-right',
        'navigation.disclosure' => 'heroicon-mini:chevron-down',
        'navigation.dropdown' => 'heroicon:chevron-down',
        'navigation.group' => 'heroicon:rectangle-stack',
        'navigation.users' => 'heroicon:users',
        'navigation.settings' => 'heroicon:cog-6-tooth',
        'navigation.notifications' => 'heroicon:bell',
        'navigation.search' => 'heroicon:magnifying-glass',

        // CRUD and generic actions
        'actions.create' => 'heroicon:plus',
        'actions.edit' => 'heroicon:pencil-square',
        'actions.delete' => 'heroicon:trash',
        'actions.force-delete' => 'heroicon:no-symbol',
        'actions.restore' => 'heroicon:arrow-uturn-left',
        'actions.view' => 'heroicon:eye',
        'actions.save' => 'heroicon:check',
        'actions.cancel' => 'heroicon:x-mark',
        'actions.confirm' => 'heroicon:check-circle',
        'actions.close' => 'heroicon:x-mark',
        'actions.refresh' => 'heroicon:arrow-path',
        'actions.download' => 'heroicon:arrow-down-tray',
        'actions.upload' => 'heroicon:arrow-up-tray',
        'actions.export' => 'heroicon:arrow-up-on-square',
        'actions.import' => 'heroicon:arrow-down-on-square',
        'actions.duplicate' => 'heroicon:document-duplicate',
        'actions.copy' => 'heroicon:clipboard',
        'actions.remove' => 'heroicon:x-mark',
        'actions.rename' => 'heroicon:pencil',
        'actions.print' => 'heroicon:printer',
        'actions.filter' => 'heroicon:funnel',
        'actions.search' => 'heroicon:magnifying-glass',
        'actions.more' => 'heroicon:ellipsis-horizontal',
        'actions.more-vertical' => 'heroicon:ellipsis-vertical',
        'actions.attach' => 'heroicon:link',
        'actions.detach' => 'heroicon:link-slash',
        'actions.share' => 'heroicon:share',
        'actions.archive' => 'heroicon:archive-box',
        'actions.open' => 'heroicon:arrow-top-right-on-square',
        'actions.reorder' => 'heroicon:arrows-up-down',

        // Statuses
        'status.success' => 'heroicon:check-circle',
        'status.warning' => 'heroicon:exclamation-triangle',
        'status.error' => 'heroicon:x-circle',
        'status.danger' => 'status.error',
        'status.info' => 'heroicon:information-circle',
        'status.pending' => 'heroicon:clock',
        'status.neutral' => 'heroicon:minus-circle',
        'status.loading' => 'heroicon:arrow-path',
        'status.locked' => 'heroicon:lock-closed',
        'status.unlocked' => 'heroicon:lock-open',

        // Authentication and security
        'authentication.login' => 'heroicon:arrow-right-end-on-rectangle',
        'authentication.logout' => 'heroicon:arrow-right-start-on-rectangle',
        'authentication.password' => 'heroicon:key',
        'authentication.security' => 'heroicon:shield-check',
        'authentication.mfa' => 'heroicon:device-phone-mobile',
        'authentication.passkey' => 'heroicon:finger-print',
        'authentication.email' => 'heroicon:envelope',
        'authentication.user' => 'heroicon:user',
        'authentication.session' => 'heroicon:computer-desktop',
        'authentication.show-password' => 'heroicon:eye',
        'authentication.hide-password' => 'heroicon:eye-slash',
        'authentication.recovery-codes' => 'heroicon:document-text',
        'authentication.locked' => 'status.locked',

        // Notifications
        'notifications.notification' => 'heroicon:bell',
        'notifications.unread' => 'heroicon:bell-alert',
        'notifications.mark-read' => 'heroicon:envelope-open',
        'notifications.dismiss' => 'heroicon:x-mark',
        'notifications.email' => 'heroicon:envelope',
        'notifications.announcement' => 'heroicon:megaphone',

        // Tables
        'tables.sort' => 'heroicon:chevron-up-down',
        'tables.sort-ascending' => 'heroicon:arrow-up',
        'tables.sort-descending' => 'heroicon:arrow-down',
        'tables.move-up' => 'heroicon:chevron-up',
        'tables.move-down' => 'heroicon:chevron-down',
        'tables.search' => 'heroicon:magnifying-glass',
        'tables.filter' => 'heroicon:funnel',
        'tables.columns' => 'heroicon:view-columns',
        'tables.previous-page' => 'heroicon:chevron-left',
        'tables.next-page' => 'heroicon:chevron-right',
        'tables.first-page' => 'heroicon:chevron-double-left',
        'tables.last-page' => 'heroicon:chevron-double-right',
        'tables.row-actions' => 'heroicon:ellipsis-horizontal',
        'tables.bulk-actions' => 'heroicon:ellipsis-vertical',
        'tables.export' => 'heroicon:arrow-down-tray',
        'tables.empty' => 'heroicon:information-circle',
        'tables.no-results' => 'heroicon:magnifying-glass',
        'tables.error' => 'heroicon:exclamation-triangle',
        'tables.copy' => 'actions.copy',
        'tables.image-placeholder' => 'media.image',
        'tables.saved-views' => 'heroicon:bookmark',
        'tables.default-view' => 'heroicon:star',
        'tables.reset' => 'heroicon:arrow-path',
        'tables.true' => 'heroicon:check-circle',
        'tables.false' => 'heroicon:x-circle',

        // Forms
        'forms.help' => 'heroicon:question-mark-circle',
        'forms.error' => 'heroicon:exclamation-circle',
        'forms.valid' => 'heroicon:check-circle',
        'forms.date' => 'heroicon:calendar-days',
        'forms.time' => 'heroicon:clock',
        'forms.upload' => 'heroicon:arrow-up-tray',
        'forms.file' => 'heroicon:document',
        'forms.attachment' => 'heroicon:paper-clip',
        'forms.clear' => 'heroicon:x-mark',
        'forms.show-password' => 'authentication.show-password',
        'forms.hide-password' => 'authentication.hide-password',
        'forms.select' => 'heroicon:chevron-up-down',
        'forms.color' => 'heroicon:swatch',
        'forms.add' => 'heroicon:plus',
        'forms.remove' => 'heroicon:minus',
        'forms.tag' => 'heroicon:tag',

        // Infolists
        'infolists.copy' => 'actions.copy',
        'infolists.true' => 'tables.true',
        'infolists.false' => 'tables.false',
        'infolists.empty' => 'heroicon:information-circle',
        'infolists.image-placeholder' => 'media.image',
        'infolists.link' => 'heroicon:link',
        'infolists.external' => 'navigation.external',

        // Media
        'media.image' => 'heroicon:photo',
        'media.video' => 'heroicon:film',
        'media.audio' => 'heroicon:musical-note',
        'media.document' => 'heroicon:document',
        'media.archive' => 'heroicon:archive-box',
        'media.folder' => 'heroicon:folder',
        'media.upload' => 'heroicon:cloud-arrow-up',
        'media.download' => 'heroicon:arrow-down-tray',
        'media.library' => 'heroicon:rectangle-stack',
        'media.grid-view' => 'heroicon:squares-2x2',
        'media.list-view' => 'heroicon:bars-3',
        'media.close' => 'actions.close',
        'media.remove' => 'actions.remove',

        // Workflows
        'workflows.workflow' => 'heroicon:arrow-path-rounded-square',
        'workflows.transition' => 'heroicon:arrow-right',
        'workflows.approve' => 'heroicon:check-badge',
        'workflows.reject' => 'heroicon:x-circle',
        'workflows.pending' => 'status.pending',
        'workflows.history' => 'heroicon:clock',
        'workflows.assign' => 'heroicon:user-plus',

        // Analytics
        'analytics.chart' => 'heroicon:chart-bar',
        'analytics.line-chart' => 'heroicon:presentation-chart-line',
        'analytics.pie-chart' => 'heroicon:chart-pie',
        'analytics.trend-up' => 'heroicon:arrow-trending-up',
        'analytics.trend-down' => 'heroicon:arrow-trending-down',
        'analytics.report' => 'heroicon:document-chart-bar',
        'analytics.kpi' => 'heroicon:presentation-chart-bar',
        'analytics.export' => 'actions.download',
        'analytics.filter' => 'heroicon:adjustments-horizontal',

        // Widgets
        'widgets.activity' => 'inline:circle',
        'widgets.increase' => 'heroicon:arrow-up',
        'widgets.decrease' => 'heroicon:arrow-down',
        'widgets.unchanged' => 'heroicon:minus',
        'widgets.refresh' => 'actions.refresh',
        'widgets.error' => 'heroicon:exclamation-triangle',
        'widgets.empty' => 'heroicon:information-circle',

        // Settings and appearance
        'settings.settings' => 'heroicon:cog-6-tooth',
        'settings.general' => 'heroicon:adjustments-horizontal',
        'settings.appearance' => 'heroicon:swatch',
        'settings.light' => 'heroicon:sun',
        'settings.dark' => 'heroicon:moon',
        'settings.system' => 'heroicon:computer-desktop',
        'settings.language' => 'heroicon:language',
        'settings.security' => 'authentication.security',
        'settings.integrations' => 'heroicon:puzzle-piece',

        // Panels
        'panels.user-menu' => 'heroicon:user-circle',
        'panels.workspace' => 'heroicon:building-office',
        'panels.switch-workspace' => 'heroicon:arrows-right-left',
        'panels.profile' => 'heroicon:user',
        'panels.help' => 'heroicon:question-mark-circle',
        'panels.notifications' => 'navigation.notifications',
        'panels.switch-panel' => 'heroicon:squares-2x2',
        'panels.menu-toggle' => 'heroicon:chevron-up-down',

        // Pages
        'pages.empty' => 'heroicon:inbox',

        // Layouts
        'layouts.expand' => 'heroicon:chevron-down',
        'layouts.collapse' => 'heroicon:chevron-up',
        'layouts.empty' => 'heroicon:inbox',
        'layouts.disclosure' => 'heroicon-mini:chevron-down',
    ];
}
