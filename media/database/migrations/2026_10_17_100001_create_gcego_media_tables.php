<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media records and their attachments to models. Separate from any other
 * media package's tables (for example spatie/laravel-medialibrary's "media").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcego_media', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('tenant', 100)->nullable();
            $table->nullableMorphs('uploader');
            $table->string('collection', 64);
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('original_name', 191);
            $table->string('mime', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 16)->default('pending');
            $table->string('scan_status', 16)->default('unscanned');
            $table->json('metadata')->nullable();
            $table->json('variants')->nullable();
            $table->string('alt', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant', 'collection', 'created_at']);
            $table->index(['tenant', 'mime']);
            $table->index(['tenant', 'status']);
            $table->index(['tenant', 'original_name']);
            $table->index(['checksum']);
        });

        Schema::create('gcego_media_attachments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('gcego_media')->cascadeOnDelete();
            $table->string('attachable_type');
            $table->string('attachable_id', 64);
            $table->string('collection', 64);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['media_id', 'attachable_type', 'attachable_id', 'collection'], 'gcego_media_attachments_unique');
            $table->index(['attachable_type', 'attachable_id', 'collection', 'position'], 'gcego_media_attachments_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcego_media_attachments');
        Schema::dropIfExists('gcego_media');
    }
};
