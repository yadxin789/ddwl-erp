<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3 §3.2「每个岗位至少绑定一个角色」的落库：
     * positions 原先只有编码/名称/部门，无法表达岗位→角色关系，导致 22 岗与角色两套体系不连通。
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->json('role_names')->nullable()->after('department_id')
                ->comment('岗位绑定角色（spatie role name 数组，至少 1 个）');
            $table->string('data_scope', 120)->nullable()->after('role_names')
                ->comment('数据范围说明（本人/本线/本部门/全部）');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['role_names', 'data_scope']);
        });
    }
};
