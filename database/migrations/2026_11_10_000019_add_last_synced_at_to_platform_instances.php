<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P3-08 平台实例记录最近一次订单拉取时间，供同步频率（15m/hourly/daily）判定。 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_instances', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('last_check_message');
        });
    }

    public function down(): void
    {
        Schema::table('platform_instances', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
