<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->string('domain', 128)->nullable();
            $t->string('contact_name', 64)->nullable();
            $t->string('contact_phone', 32)->nullable();
            $t->date('expired_at')->nullable();
            $t->string('status', 16)->default('active')->comment('active/suspended/expired');
            $t->json('settings')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
