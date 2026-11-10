<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 系统权限模块：system_settings（系统参数）、media_files（统一文件表）
     * 对齐 database-schema.sql §1.4 / §1.5
     */
    public function up(): void
    {
        // 1.4 系统参数表
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id()->comment('参数ID');
            $table->string('config_key', 64)->comment('参数键名');
            $table->text('config_value')->nullable()->comment('参数值');
            $table->string('group_name', 32)->nullable()->comment('分组：base/order/stock/quality/ecommerce');
            $table->string('description', 255)->nullable()->comment('参数说明');
            $table->tinyInteger('is_public')->default(0)->comment('是否前端可见');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('config_key', 'uk_config_key');
        });

        // 1.5 统一文件表
        Schema::create('media_files', function (Blueprint $table) {
            $table->id()->comment('文件ID');
            $table->string('file_name', 255)->comment('原始文件名');
            $table->string('file_path', 512)->comment('存储路径');
            $table->unsignedBigInteger('file_size')->nullable()->comment('文件大小（字节）');
            $table->string('mime_type', 128)->nullable()->comment('MIME类型');
            $table->string('category', 32)->comment('分类：rnd_doc/chat_file/bom_excel/avatar/collab_doc');
            $table->unsignedBigInteger('uploader_id')->comment('上传人ID');
            $table->string('ref_type', 32)->nullable()->comment('关联业务类型');
            $table->unsignedBigInteger('ref_id')->nullable()->comment('关联业务ID');
            $table->timestamp('created_at')->nullable()->comment('上传时间');

            $table->index('category', 'idx_category');
            $table->index(['ref_type', 'ref_id'], 'idx_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('system_settings');
    }
};
