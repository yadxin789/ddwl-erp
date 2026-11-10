<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('replay_of')->nullable()->after('message')->comment('重放来源日志 id');
            $table->timestamp('replayed_at')->nullable()->after('replay_of')->comment('被重放的时间');
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropColumn(['replay_of', 'replayed_at']);
        });
    }
};
