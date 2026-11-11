<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 演示账号实名化：把按岗位命名的演示账号改为个人姓名账号。
 *
 * 只处理已知的演示账号，并且只在当前 username 仍是旧值时更新，
 * 避免覆盖正式环境中已经改成真实姓名的账号。
 */
return new class extends Migration
{
    private array $accounts = [
        ['admin', '系统管理员', '张三'],
        ['wh-supervisor', '仓库主管', '李四'],
        ['wh-keeper', '仓管', '王五'],
        ['purchase', '采购专员', '赵六'],
        ['sales', '销售专员', '钱七'],
        ['engineer', '工程师', '孙八'],
        ['quality', '品质主管', '周九'],
        ['prod-supervisor', '生产主管', '吴十'],
        ['iqc-inspector', 'IQC检验员', '郑一'],
        ['rnd-manager', '研发经理', '王明'],
        ['admin-assistant', '人事专员', '李娜'],
        ['accountant', '会计', '陈静'],
        ['cashier', '出纳', '刘芳'],
        ['gm', '总经理', '高远'],
        ['prod-leader', '生产组长', '冯军'],
        ['operator', '作业员', '蒋鹏'],
        ['ipqc-inspector', 'IPQC检验员', '邓雪'],
        ['fqc-inspector', 'FQC检验员', '曹颖'],
        ['pur-manager', '采购主管', '徐涛'],
        ['sal-manager', '销售主管', '罗娟'],
        ['sal-cs', '客服', '彭欣'],
        ['eng-sw', '软件工程师', '唐斌'],
        ['eng-me', '结构工程师', '韩磊'],
        ['pkg-designer', '包装设计师', '谢婷'],
        ['id-designer', '外观设计师', '许凯'],
        ['wh-receiver', '收货员', '卢伟'],
        ['adm-manager', '行政经理', '苏琴'],
        ['ecom-operator', '电商运营', '魏晨'],
        ['ecom-cs', '电商客服', '叶萌'],
    ];

    public function up(): void
    {
        foreach ($this->accounts as [$oldUsername, $oldName, $newName]) {
            $user = DB::table('users')->where('username', $oldUsername)->first();
            if (! $user) {
                continue;
            }

            // 目标姓名账号已存在（例如正式环境已手工建过），则跳过，避免唯一键冲突。
            if (DB::table('users')->where('username', $newName)->where('id', '!=', $user->id)->exists()) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'username' => $newName,
                'name' => $newName,
                'updated_at' => now(),
            ]);
        }

        // material_mpns.approved_by 是字符串（工程批准人），演示数据里写的是 admin。
        DB::table('material_mpns')->where('approved_by', 'admin')->update(['approved_by' => '张三']);
    }

    public function down(): void
    {
        foreach ($this->accounts as [$oldUsername, $oldName, $newName]) {
            $user = DB::table('users')->where('username', $newName)->first();
            if (! $user) {
                continue;
            }

            if (DB::table('users')->where('username', $oldUsername)->where('id', '!=', $user->id)->exists()) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'username' => $oldUsername,
                'name' => $oldName,
                'updated_at' => now(),
            ]);
        }

        DB::table('material_mpns')->where('approved_by', '张三')->update(['approved_by' => 'admin']);
    }
};
