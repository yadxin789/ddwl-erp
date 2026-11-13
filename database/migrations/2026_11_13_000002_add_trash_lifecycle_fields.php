<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 回收站闭环：软删除记录统一记录“谁删除”，文件表纳入软删除。
 * 物理文件在软删除阶段保留，只有回收站彻底删除或到期清理时才移除。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_chat_messages', 'deleted_by')) {
            Schema::table('system_chat_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            });
        }

        if (! Schema::hasColumn('rnd_collab_documents', 'deleted_by')) {
            Schema::table('rnd_collab_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            });
        }

        if (! Schema::hasColumn('media_files', 'deleted_at')) {
            Schema::table('media_files', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('media_files', 'deleted_by')) {
            Schema::table('media_files', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media_files', 'deleted_by')) {
            Schema::table('media_files', function (Blueprint $table) {
                $table->dropColumn('deleted_by');
            });
        }

        if (Schema::hasColumn('media_files', 'deleted_at')) {
            Schema::table('media_files', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('rnd_collab_documents', 'deleted_by')) {
            Schema::table('rnd_collab_documents', function (Blueprint $table) {
                $table->dropColumn('deleted_by');
            });
        }

        if (Schema::hasColumn('system_chat_messages', 'deleted_by')) {
            Schema::table('system_chat_messages', function (Blueprint $table) {
                $table->dropColumn('deleted_by');
            });
        }
    }
};
