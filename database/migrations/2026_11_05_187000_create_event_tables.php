<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('event_type', 128);
            $t->string('ref_type', 64)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 16)->default('pending')->comment('pending/published/failed');
            $t->integer('retry_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('integration_inbox', function (Blueprint $t) {
            $t->id();
            $t->string('event_type', 128);
            $t->string('source', 64)->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 16)->default('received')->comment('received/processed/failed');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('domain_events', function (Blueprint $t) {
            $t->id();
            $t->string('name', 128);
            $t->string('agg_type', 64)->nullable();
            $t->unsignedBigInteger('agg_id')->nullable();
            $t->json('data')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('integration_inbox');
        Schema::dropIfExists('integration_outbox');
    }
};
