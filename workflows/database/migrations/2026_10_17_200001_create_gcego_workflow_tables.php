<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow instances, their tasks, approval decisions, append-only
 * history and idempotent command records. Morph keys are strings (64), so
 * subjects and people may use integer, ULID or UUID keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcego_workflow_instances', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('workflow', 64);
            $table->unsignedInteger('version');
            $table->char('structure', 16);
            $table->string('subject_type');
            $table->string('subject_id', 64);
            $table->string('tenant', 100)->nullable();
            $table->string('state', 64);
            $table->string('status', 16);
            $table->string('initiator_type')->nullable();
            $table->string('initiator_id', 64)->nullable();
            $table->index(['initiator_type', 'initiator_id']);
            $table->unsignedInteger('lock_version')->default(1);
            // 1 while running, null when finished: at most one running instance per workflow and subject.
            $table->unsignedTinyInteger('running')->nullable();
            $table->timestamp('state_entered_at')->nullable();
            // The history sequence at which the current state was entered.
            $table->unsignedInteger('state_sequence')->default(0);
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow', 'subject_type', 'subject_id', 'running'], 'gcego_workflow_instances_running_unique');
            $table->index(['tenant', 'workflow', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'deadline_at']);
        });

        Schema::create('gcego_workflow_tasks', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('instance_id')->constrained('gcego_workflow_instances')->cascadeOnDelete();
            $table->string('tenant', 100)->nullable();
            $table->string('kind', 16);
            $table->string('name', 64);
            $table->string('stage', 64)->nullable();
            $table->unsignedSmallInteger('stage_index')->default(0);
            $table->unsignedInteger('round')->default(1);
            $table->string('state', 64);
            $table->string('assignee_type')->nullable();
            $table->string('assignee_id', 64)->nullable();
            $table->index(['assignee_type', 'assignee_id']);
            $table->boolean('pool')->default(false);
            $table->string('status', 16);
            $table->string('priority', 16)->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('delegated_by_type')->nullable();
            $table->string('delegated_by_id', 64)->nullable();
            $table->index(['delegated_by_type', 'delegated_by_id']);
            $table->unsignedTinyInteger('delegations')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant', 'assignee_type', 'assignee_id', 'status']);
            $table->index(['tenant', 'pool', 'status']);
            $table->index(['instance_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('gcego_workflow_decisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('gcego_workflow_instances')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('gcego_workflow_tasks')->cascadeOnDelete();
            $table->string('tenant', 100)->nullable();
            $table->string('approval', 64);
            $table->string('stage', 64);
            $table->unsignedInteger('round');
            $table->string('actor_type');
            $table->string('actor_id', 64);
            $table->string('decision', 16);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // One decision per person, approval stage and round.
            $table->unique(['instance_id', 'approval', 'stage', 'round', 'actor_type', 'actor_id'], 'gcego_workflow_decisions_once');
        });

        Schema::create('gcego_workflow_events', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('instance_id')->constrained('gcego_workflow_instances')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('tenant', 100)->nullable();
            $table->string('type', 48);
            $table->string('actor_kind', 16);
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->index(['actor_type', 'actor_id']);
            $table->string('actor_name', 64)->nullable();
            $table->string('from_state', 64)->nullable();
            $table->string('to_state', 64)->nullable();
            $table->foreignId('task_id')->nullable()->constrained('gcego_workflow_tasks')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->char('correlation', 26);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['instance_id', 'sequence']);
            $table->index(['tenant', 'type', 'created_at']);
        });

        Schema::create('gcego_workflow_commands', static function (Blueprint $table): void {
            $table->id();
            $table->char('key', 64)->unique();
            $table->unsignedBigInteger('instance_id')->nullable();
            $table->json('result');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['gcego_workflow_commands', 'gcego_workflow_events', 'gcego_workflow_decisions', 'gcego_workflow_tasks', 'gcego_workflow_instances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
