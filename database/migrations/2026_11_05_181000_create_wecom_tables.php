<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wecom_configs', function (Blueprint $t) {
            $t->id();
            $t->string('corp_id', 128);
            $t->string('agent_id', 64)->nullable();
            $t->string('app_secret', 256)->nullable();
            $t->string('token', 128)->nullable();
            $t->string('encoding_aes_key', 256)->nullable();
            $t->boolean('status')->default(false);
            $t->timestamps();
        });

        Schema::create('wecom_message_logs', function (Blueprint $t) {
            $t->id();
            $t->string('msg_id', 128)->nullable();
            $t->string('to_user', 256)->nullable();
            $t->string('msg_type', 32)->default('text');
            $t->text('content')->nullable();
            $t->string('status', 16)->default('pending')->comment('pending/sent/failed');
            $t->text('error_msg')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wecom_message_logs');
        Schema::dropIfExists('wecom_configs');
    }
};
