<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3 §8.4 / S5：岗位数据范围落到"可执行枚举"
     *
     * data_scope 保留中文原文给人看；data_scope_type 是机器可执行口径：
     *   all        全部数据
     *   department 本部门数据
     *   self       仅本人经手/负责
     *   none       不看业务数据（系统管理员）
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->string('data_scope_type', 16)->default('self')->after('data_scope')
                ->comment('数据范围：all/department/self/none');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('data_scope_type');
        });
    }
};
