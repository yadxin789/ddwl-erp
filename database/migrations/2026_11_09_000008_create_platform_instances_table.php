<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-07：电商平台接入（只做引导/设置/适配器，不做交易闭环）
 * - platform_instances：接入实例（凭证加密存储、映射 JSON、启停、默认仓、同步频率、自检结果、授权到期）
 * - platform_sku_mappings：SKU 映射预留表（本期不强制填）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_instances', function (Blueprint $table) {
            $table->id();
            $table->string('platform_key', 48)->unique()->comment('预置平台 key 或 custom:xxx');
            $table->string('name', 64)->comment('实例名称（店铺名）');
            $table->string('region', 16)->default('domestic')->comment('domestic/overseas');
            $table->text('credentials_json')->nullable()->comment('凭证（加密存储）');
            $table->json('mapping_json')->nullable()->comment('字段映射');
            $table->string('api_base_url', 256)->nullable()->comment('自定义平台接口地址');
            $table->string('auth_type', 24)->nullable()->comment('apikey/oauth2/signature');
            $table->string('callback_url', 256)->nullable();
            $table->unsignedBigInteger('default_warehouse_id')->nullable();
            $table->string('sync_frequency', 24)->default('manual')->comment('manual/15m/hourly/daily');
            $table->boolean('status')->default(true)->comment('启用/停用（停用不产生同步任务）');
            $table->timestamp('last_check_at')->nullable();
            $table->string('last_check_result', 16)->nullable()->comment('ok/failed');
            $table->string('last_check_message', 255)->nullable();
            $table->date('expires_at')->nullable()->comment('授权到期日（前 7 天提醒）');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['region', 'status'], 'idx_instance_region_status');
            $table->index('expires_at', 'idx_instance_expires');
        });

        Schema::create('platform_sku_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_instance_id');
            $table->string('platform_sku', 128);
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['platform_instance_id', 'platform_sku'], 'uk_instance_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sku_mappings');
        Schema::dropIfExists('platform_instances');
    }
};
