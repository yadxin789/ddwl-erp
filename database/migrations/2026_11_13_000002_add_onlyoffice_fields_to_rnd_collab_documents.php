<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_collab_documents', function (Blueprint $table) {
            $table->string('extension', 10)->nullable()->after('doc_type')->comment('docx/xlsx/pptx');
            $table->string('storage_path')->nullable()->after('extension')->comment('file_repository 磁盘相对路径');
            $table->string('doc_key', 64)->nullable()->after('storage_path')->comment('OnlyOffice 文档 key');
            $table->unsignedBigInteger('size')->default(0)->after('doc_key');
            $table->index('doc_key', 'rnd_collab_doc_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_collab_documents', function (Blueprint $table) {
            $table->dropIndex('rnd_collab_doc_key_idx');
            $table->dropColumn(['extension', 'storage_path', 'doc_key', 'size']);
        });
    }
};
