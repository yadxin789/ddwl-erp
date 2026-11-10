<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- 研发项目 ---------- */
        Schema::create('rnd_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 32)->unique()->comment('项目编号');
            $table->string('project_name', 128)->comment('项目名称');
            $table->string('product_model', 64)->nullable();
            $table->unsignedBigInteger('creator_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('in_progress')->comment('in_progress/overdue/completed/overdue_completed/paused');
            $table->integer('progress')->default(0);
            $table->timestamps();
        });

        Schema::create('rnd_task_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('文档类型名称');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('rnd_project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_type_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('executor_id');
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->comment('截止日期');
            $table->string('status', 20)->default('pending')->comment('pending/in_progress/submitted/approved/rejected/overdue');
            $table->boolean('is_overdue')->default(false);
            $table->integer('extension_days')->default(0);
            $table->timestamps();
            $table->index('project_id');
            $table->index('executor_id');
        });

        Schema::create('rnd_project_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->integer('version_no')->default(1);
            $table->unsignedBigInteger('file_id')->comment('media_files.id');
            $table->unsignedBigInteger('uploader_id');
            $table->string('audit_status', 16)->default('pending')->comment('pending/approved/rejected');
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->timestamp('audit_at')->nullable();
            $table->string('audit_remark', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('project_id');
        });

        Schema::create('rnd_extension_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('applicant_id');
            $table->integer('apply_days');
            $table->text('reason');
            $table->string('status', 16)->default('pending')->comment('pending/approved/rejected');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
        });

        /* ---------- 协同办公 ---------- */
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('notify_type', 32);
            $table->string('title', 128);
            $table->text('content')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('business_type', 32)->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('user_id');
        });

        Schema::create('system_todo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('todo_type', 32)->comment('approval/rnd_task/ecom_exception/quality');
            $table->string('title', 128);
            $table->timestamp('deadline')->nullable();
            $table->boolean('is_finished')->default(false);
            $table->string('business_type', 32)->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->index('user_id');
        });

        Schema::create('system_chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_name', 128);
            $table->string('room_type', 16)->default('department')->comment('department/project');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('creator_id');
            $table->timestamps();
        });

        Schema::create('system_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('sender_id');
            $table->string('msg_type', 16)->default('text')->comment('text/file');
            $table->text('content')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('room_id');
        });

        /* ---------- 审批流 ---------- */
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('flow_code', 32)->unique();
            $table->string('flow_name', 64);
            $table->string('business_type', 32)->comment('purchase/rnd_extension/scrap/bom_approve');
            $table->json('steps_json')->comment('审批步骤配置');
            $table->boolean('status')->default(true);
        });

        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->string('business_type', 32);
            $table->unsignedBigInteger('business_id');
            $table->integer('current_step')->default(1);
            $table->string('status', 16)->default('pending')->comment('pending/approved/rejected/cancelled');
            $table->unsignedBigInteger('applicant_id');
            $table->json('logs_json')->nullable()->comment('各步审批记录');
            $table->timestamps();
            $table->index(['business_type', 'business_id']);
        });

        /* ---------- AI ---------- */
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('provider_code', 32)->unique();
            $table->string('api_base', 255);
            $table->string('api_key', 255)->nullable();
            $table->string('model', 64)->default('gpt-4o-mini');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 128)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('conversation_type', 16)->default('chat')->comment('chat/ocr');
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('role', 16)->comment('user/assistant/system');
            $table->longText('content');
            $table->integer('token_usage')->nullable();
            $table->timestamps();
            $table->index('conversation_id');
        });

        /* ---------- 安全审计 ---------- */
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username', 32)->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 16)->comment('success/failed');
            $table->string('fail_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('login_failures', function (Blueprint $table) {
            $table->id();
            $table->string('username', 32);
            $table->string('ip_address', 45);
            $table->integer('fail_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 32);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('detail')->nullable();
            $table->string('level', 16)->default('info')->comment('info/warning/critical');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 64);
            $table->string('target', 128)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['sensitive_access_logs','security_events','login_failures','login_logs',
            'ai_messages','ai_conversations','ai_providers',
            'approval_instances','approval_flows',
            'system_chat_messages','system_chat_rooms','system_todo_items','system_notifications',
            'rnd_extension_applications','rnd_project_documents','rnd_project_tasks','rnd_task_types','rnd_projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
