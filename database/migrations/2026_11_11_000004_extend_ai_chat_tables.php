<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_conversations', 'skill_code')) {
                $table->string('skill_code', 64)->nullable()->after('provider_id');
            }
            if (! Schema::hasColumn('ai_conversations', 'message_count')) {
                $table->unsignedInteger('message_count')->default(0)->after('skill_code');
            }
            if (! Schema::hasColumn('ai_conversations', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('message_count');
            }
        });

        Schema::table('ai_skills', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_skills', 'type')) {
                $table->string('type', 30)->default('tool')->after('description');
            }
            if (! Schema::hasColumn('ai_skills', 'config')) {
                $table->json('config')->nullable()->after('type');
            }
            if (! Schema::hasColumn('ai_skills', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('config');
            }
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_messages', 'meta')) {
                $table->json('meta')->nullable()->after('content');
            }
        });

        Schema::table('ai_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_attachments', 'conversation_id')) {
                $table->unsignedBigInteger('conversation_id')->nullable()->after('message_id');
            }
            if (! Schema::hasColumn('ai_attachments', 'mime_type')) {
                $table->string('mime_type', 128)->nullable()->after('file_path');
            }
            if (! Schema::hasColumn('ai_attachments', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_attachments', function (Blueprint $table) {
            foreach (['file_size', 'mime_type', 'conversation_id'] as $column) {
                if (Schema::hasColumn('ai_attachments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            if (Schema::hasColumn('ai_messages', 'meta')) {
                $table->dropColumn('meta');
            }
        });

        Schema::table('ai_skills', function (Blueprint $table) {
            foreach (['enabled', 'config', 'type'] as $column) {
                if (Schema::hasColumn('ai_skills', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            foreach (['last_message_at', 'message_count', 'skill_code'] as $column) {
                if (Schema::hasColumn('ai_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
