<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次1：主数据/CRM/HR/AI 扩展表（~45张）
 * 来自 ddwl-soho + pm-server + laravel12-erp 合并去重
 */
return new class extends Migration {
    public function up(): void
    {
        // === AI 模块扩展 ===
        Schema::create('ai_models', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 64);
            $t->string('model_name', 128);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('ai_attachments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('message_id')->nullable();
            $t->string('file_name', 256);
            $t->string('file_path', 512);
            $t->timestamps();
        });
        Schema::create('ai_ocr_results', function (Blueprint $t) {
            $t->id();
            $t->string('doc_type', 32)->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_skills', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name', 128);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_doc_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name', 128);
            $t->text('prompt')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_change_proposals', function (Blueprint $t) {
            $t->id();
            $t->string('target_type', 64);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->json('proposal')->nullable();
            $t->string('status', 16)->default('pending');
            $t->timestamps();
        });
        Schema::create('ai_delegation_tokens', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('token', 128)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        // === CRM 扩展 ===
        Schema::create('customer_groups', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->timestamps();
        });
        Schema::create('customer_tags', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->string('tag', 64);
            $t->timestamps();
        });
        Schema::create('customer_platform_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->string('platform', 32);
            $t->string('platform_uid', 128);
            $t->timestamps();
        });

        // === HR 扩展 ===
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('work_date');
            $t->timestamp('clock_in')->nullable();
            $t->timestamp('clock_out')->nullable();
            $t->string('status', 16)->default('normal');
            $t->timestamps();
        });
        Schema::create('time_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('project_id')->nullable();
            $t->date('work_date');
            $t->decimal('hours', 5, 2)->default(0);
            $t->string('description', 256)->nullable();
            $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('asset_code', 32)->unique();
            $t->string('name', 128);
            $t->unsignedBigInteger('employee_id')->nullable()->comment('领用人');
            $t->string('status', 16)->default('available');
            $t->timestamps();
        });
        Schema::create('hr_applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('type', 32)->comment('请假/报销/加班');
            $t->json('payload')->nullable();
            $t->string('status', 16)->default('pending');
            $t->timestamps();
        });

        // === 知识库/文档 ===
        Schema::create('knowledges', function (Blueprint $t) {
            $t->id();
            $t->string('title', 256);
            $t->text('content')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->timestamps();
        });
        Schema::create('knowledge_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name', 128);
            $t->timestamps();
        });
        Schema::create('knowledge_docs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('knowledge_id');
            $t->string('file_name', 256);
            $t->string('file_path', 512);
            $t->timestamps();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->string('name', 256);
            $t->string('path', 512);
            $t->unsignedBigInteger('uploader_id')->nullable();
            $t->timestamps();
        });
        Schema::create('file_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('file_id');
            $t->integer('version')->default(1);
            $t->string('path', 512);
            $t->timestamps();
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->string('ref_type', 64);
            $t->unsignedBigInteger('ref_id');
            $t->string('file_name', 256);
            $t->string('file_path', 512);
            $t->timestamps();
        });
        Schema::create('collab_documents', function (Blueprint $t) {
            $t->id();
            $t->string('title', 256);
            $t->longText('content')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->timestamps();
        });
        Schema::create('department_files', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('department_id');
            $t->string('file_name', 256);
            $t->string('file_path', 512);
            $t->timestamps();
        });

        // === 协同/消息 ===
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('from_user_id');
            $t->unsignedBigInteger('to_user_id');
            $t->text('content')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('todos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('title', 256);
            $t->text('description')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->string('status', 16)->default('pending');
            $t->timestamps();
        });
        Schema::create('notification_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('title', 256);
            $t->text('content')->nullable();
            $t->timestamps();
        });
        Schema::create('chat_clear_states', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('room_id');
            $t->timestamp('cleared_at')->nullable();
            $t->timestamps();
        });

        // === 项目管理（pm-server 风格） ===
        Schema::create('project_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name', 128);
            $t->json('steps')->nullable();
            $t->timestamps();
        });
        Schema::create('template_tasks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('template_id');
            $t->string('name', 128);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('task_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name', 128);
            $t->timestamps();
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->string('title', 256);
            $t->unsignedBigInteger('assignee_id')->nullable();
            $t->string('status', 16)->default('todo');
            $t->timestamp('due_at')->nullable();
            $t->timestamps();
        });
        Schema::create('project_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('action', 64);
            $t->text('detail')->nullable();
            $t->timestamps();
        });
        Schema::create('statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64);
            $t->string('name', 128);
            $t->string('module', 32);
            $t->timestamps();
        });
        Schema::create('status_transitions', function (Blueprint $t) {
            $t->id();
            $t->string('from_status', 64);
            $t->string('to_status', 64);
            $t->string('module', 32);
            $t->timestamps();
        });
        Schema::create('default_members', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('role', 64);
            $t->timestamps();
        });

        // === 审批扩展 ===
        Schema::create('approval_flow_steps', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('approval_flow_id');
            $t->integer('step_order')->default(1);
            $t->string('approver_type', 32);
            $t->unsignedBigInteger('approver_id')->nullable();
            $t->timestamps();
        });
        Schema::create('approval_tasks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('approval_instance_id');
            $t->unsignedBigInteger('approver_id');
            $t->string('status', 16)->default('pending');
            $t->text('comment')->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'approval_tasks','approval_flow_steps','default_members','status_transitions','statuses',
            'project_logs','tasks','task_types','template_tasks','project_templates',
            'chat_clear_states','notification_templates','todos','messages',
            'department_files','collab_documents','attachments','file_versions','files',
            'knowledge_docs','knowledge_categories','knowledges',
            'hr_applications','assets','time_entries','attendances',
            'customer_platform_accounts','customer_tags','customer_groups',
            'ai_delegation_tokens','ai_change_proposals','ai_doc_templates','ai_skills',
            'ai_settings','ai_ocr_results','ai_attachments','ai_models',
        ] as $t) Schema::dropIfExists($t);
    }
};
