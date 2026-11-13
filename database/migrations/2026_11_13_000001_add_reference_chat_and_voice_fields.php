<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 聊天消息软删除：管理员删除后仍可从回收站恢复，与参考目录一致。
        if (! Schema::hasColumn('system_chat_messages', 'deleted_at')) {
            Schema::table('system_chat_messages', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 电话会议演示态：当前演示者、当前演示文档、是否处于编辑模式。
        if (! Schema::hasColumn('rnd_meetings', 'current_presenter_id')) {
            Schema::table('rnd_meetings', function (Blueprint $table) {
                $table->unsignedBigInteger('current_presenter_id')->nullable()->after('creator_id');
                $table->unsignedBigInteger('current_document_id')->nullable()->after('current_presenter_id');
                $table->boolean('presentation_edit_mode')->default(false)->after('current_document_id');
            });
        }

        // 协作文档类型（新建时选择 Word/表格/演示/文本）与软删除（移入回收站）。
        if (! Schema::hasColumn('rnd_collab_documents', 'doc_type')) {
            Schema::table('rnd_collab_documents', function (Blueprint $table) {
                $table->string('doc_type', 16)->default('doc')->after('title');
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_chat_messages', 'deleted_at')) {
            Schema::table('system_chat_messages', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('rnd_meetings', 'current_presenter_id')) {
            Schema::table('rnd_meetings', function (Blueprint $table) {
                $table->dropColumn(['current_presenter_id', 'current_document_id', 'presentation_edit_mode']);
            });
        }

        if (Schema::hasColumn('rnd_collab_documents', 'doc_type')) {
            Schema::table('rnd_collab_documents', function (Blueprint $table) {
                $table->dropSoftDeletes();
                $table->dropColumn('doc_type');
            });
        }
    }
};
