<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-3 MFA/TOTP：用户级二次验证。
 * mfa_secret 用 Laravel encrypted cast 落库；恢复码只存 bcrypt 哈希。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable()->after('password');
            $table->json('mfa_recovery_codes')->nullable()->after('mfa_secret');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_recovery_codes');
            $table->timestamp('mfa_last_verified_at')->nullable()->after('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at', 'mfa_last_verified_at']);
        });
    }
};
