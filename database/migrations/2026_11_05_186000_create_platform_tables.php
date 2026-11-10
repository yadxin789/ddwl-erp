<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_api_logs', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('api_path', 256);
            $t->string('method', 8)->default('GET');
            $t->integer('http_code')->default(0);
            $t->integer('duration_ms')->default(0);
            $t->text('request_body')->nullable();
            $t->text('response_body')->nullable();
            $t->timestamp('called_at')->nullable();
            $t->timestamps();
        });

        Schema::create('platform_status_mappings', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('platform_status', 64);
            $t->string('local_status', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_status_mappings');
        Schema::dropIfExists('platform_api_logs');
    }
};
