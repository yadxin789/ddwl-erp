<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * users 表结构对齐 database-schema.md §1.1（系统用户表，Laravel 自带扩展）
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('用户ID');
            $table->string('username', 32)->unique()->comment('登录账号');
            $table->string('name', 64)->comment('姓名');
            $table->string('email', 128)->nullable()->comment('邮箱');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->string('password')->comment('密码哈希');
            $table->unsignedBigInteger('department_id')->nullable()->index()->comment('部门ID');
            $table->unsignedBigInteger('position_id')->nullable()->index()->comment('岗位ID');
            $table->string('avatar', 255)->nullable()->comment('头像路径');
            $table->string('wecom_userid', 64)->nullable()->comment('企业微信UserID');
            $table->timestamp('wecom_bound_at')->nullable()->comment('企业微信绑定时间');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->string('remember_token', 100)->nullable()->comment('记住我令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 128)->primary()->comment('邮箱');
            $table->string('token')->comment('重置令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('会话ID');
            $table->foreignId('user_id')->nullable()->index()->comment('用户ID');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->text('user_agent')->nullable()->comment('浏览器UA');
            $table->longText('payload')->comment('会话载荷');
            $table->integer('last_activity')->index()->comment('最后活动时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
