<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3 §6 / G8：公告通知（全员可读，行政或运维发布）
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->comment('标题');
            $table->text('content')->comment('正文');
            $table->string('category', 32)->default('general')->comment('general/policy/notice/urgent');
            $table->unsignedBigInteger('publisher_id')->nullable()->comment('发布人');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->tinyInteger('status')->default(1)->comment('状态：1=发布 0=下架');
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
