<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Workflows
|--------------------------------------------------------------------------
|
| Workflows are declared in PHP (app/GCEGo/Workflows, discovered by
| gcego/registry, kind "workflow"). Every command (start, transition,
| decision, task) is authorized on the server, tenant-scoped, checked
| against the instance's version (optimistic locking) and recorded in an
| append-only history. Side effects (automation, notifications) run only
| after the transaction commits.
|
*/

return [

    'discovery' => [
        // Folders whose PHP files return workflows (added to gcego-registry.discovery.roots when missing).
        'paths' => [app_path('GCEGo/Workflows')],
    ],

    'execution' => [
        // Refuse commands carrying an outdated instance version (stale screens).
        'optimistic_locking' => true,
        // Database connection of workflow tables (null: the default).
        'connection' => null,
    ],

    'approvals' => [
        'require_comment_on_rejection' => true,
        'require_comment_on_changes' => true,
        // Initiators may not decide on their own requests (separation of duties) unless an approval allows it.
        'allow_self_approval' => false,
        // Maximum length of a delegation chain.
        'max_delegations' => 3,
    ],

    'automation' => [
        'enabled' => true,
        // Queue of automation and notification jobs (null connection: the application default).
        'queue' => 'workflows',
        'connection' => null,
        'tries' => 3,
    ],

    'deadlines' => [
        'enabled' => true,
        // Register "gcego:workflows:process-deadlines" with Laravel's scheduler (every five minutes, without overlapping).
        'schedule' => true,
        // Instances and tasks handled per run.
        'batch' => 200,
    ],

    'notifications' => [
        // Notify assignees and initiators through gcego/notifications.
        'enabled' => true,
    ],

    'history' => [
        // Events shown per page of a timeline.
        'per_page' => 20,
    ],

    'inbox' => [
        'per_page' => 15,
    ],

    'observability' => [
        // A log channel for structured workflow activity (null: Laravel events only).
        'log_channel' => null,
    ],

    'security' => [
        // Scope every query to the current gcego/security tenant.
        'enforce_tenant_scope' => true,
        // The Laravel Gate ability that may manage any workflow (cancel, suspend, reassign) besides the subject's own policy.
        'manage_ability' => 'gcego-workflows.manage',
    ],

    'panels' => [
        // Add the inbox ("<panel>/workflows") and instance pages to every gcego/panels panel.
        'enabled' => true,
    ],

];
