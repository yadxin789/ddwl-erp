<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 系统权限模块：departments（部门）、positions（岗位）
     * 对齐 database-schema.sql §1.2 / §1.3
     */
    public function up(): void
    {
        // 1.2 部门表（树形结构 parent_id）
        Schema::create('departments', function (Blueprint $table) {
            $table->id()->comment('部门ID');
            $table->string('dept_code', 16)->comment('部门编码');
            $table->string('dept_name', 64)->comment('部门名称');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('上级部门ID');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('部门负责人ID');
            $table->integer('sort_order')->default(0)->comment('排序号');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('dept_code', 'uk_dept_code');
            $table->index('parent_id');
        });

        // 1.3 岗位表
        Schema::create('positions', function (Blueprint $table) {
            $table->id()->comment('岗位ID');
            $table->string('pos_code', 16)->comment('岗位编码');
            $table->string('pos_name', 64)->comment('岗位名称');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门ID');
            $table->string('description', 255)->nullable()->comment('岗位说明');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('pos_code', 'uk_pos_code');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
