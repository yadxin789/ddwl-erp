<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 项目模板对齐参考项目：模板描述 + 创建人。
 * steps 继续保存模板任务配置（数组），既有模板的“任务类型 ID 数组”格式保持兼容。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name')->comment('模板描述');
            $table->unsignedBigInteger('user_id')->nullable()->after('description')->comment('创建人ID');
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn(['description', 'user_id']);
        });
    }
};
