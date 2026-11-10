<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_rooms', function (Blueprint $t) {
            $t->id();
            $t->string('room_no', 32)->unique();
            $t->string('name', 128);
            $t->unsignedBigInteger('host_id');
            $t->string('status', 16)->default('waiting')->comment('waiting/ongoing/ended');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
        });

        Schema::create('voice_participants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('voice_room_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role', 16)->default('member')->comment('host/member');
            $t->timestamp('joined_at')->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_participants');
        Schema::dropIfExists('voice_rooms');
    }
};
