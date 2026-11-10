<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 研发模块对齐参考项目：
 * - rnd_project_tasks 增加任务名称（任务类型只表示文档类型，不再兼任任务名）
 * - rnd_projects 增加产品名称与版本号，供创建项目页使用
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_project_tasks', function (Blueprint $table) {
            $table->string('name', 128)->nullable()->after('task_type_id')->comment('任务名称');
        });

        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->string('product_name', 128)->nullable()->after('project_name')->comment('产品名称');
            $table->string('version', 20)->default('V1.0')->after('product_name')->comment('项目版本号');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'version']);
        });

        Schema::table('rnd_project_tasks', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
