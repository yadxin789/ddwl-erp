<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5：项目组内协同办公（文档）与电话会议（会议房间 + 信令 + 在线状态）。
 * 设计前提：断网 / 内网环境可用 —— 文档正文落库，信令走数据库轮询，不依赖外网服务。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rnd_collab_documents')) {
            Schema::create('rnd_collab_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('title', 200);
                $table->longText('content')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('updater_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rnd_meetings')) {
            Schema::create('rnd_meetings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('title', 120);
                $table->string('status', 20)->default('open'); // open / closed
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rnd_meeting_members')) {
            Schema::create('rnd_meeting_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('peer_id', 64)->nullable(); // WebRTC 本地标识
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->unique(['meeting_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('rnd_meeting_signals')) {
            Schema::create('rnd_meeting_signals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                $table->unsignedBigInteger('from_user_id')->index();
                $table->unsignedBigInteger('to_user_id')->nullable()->index();
                $table->string('type', 20); // offer / answer / candidate / leave
                $table->text('payload')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_meeting_signals');
        Schema::dropIfExists('rnd_meeting_members');
        Schema::dropIfExists('rnd_meetings');
        Schema::dropIfExists('rnd_collab_documents');
    }
};
