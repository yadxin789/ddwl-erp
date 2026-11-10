<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique()->comment('基础数据域编码');
            $table->string('name', 128)->comment('基础数据域名称');
            $table->unsignedBigInteger('owner_department_id')->nullable()->comment('归口部门ID');
            $table->string('route_name', 128)->nullable()->comment('维护入口路由名，空表示待建设');
            $table->string('description', 500)->nullable()->comment('归口职责说明');
            $table->boolean('status')->default(true)->comment('1启用 0停用');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('owner_department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_domains');
    }
};
