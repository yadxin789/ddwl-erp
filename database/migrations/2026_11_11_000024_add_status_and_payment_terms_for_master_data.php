<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('conversion_factor')->comment('1启用 0停用');
        });

        Schema::table('number_sequences', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('current_seq')->comment('1启用 0停用');
        });

        Schema::table('tax_entities', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('type')->comment('1启用 0停用');
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('rate')->comment('1启用 0停用');
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique()->comment('结算方式编码');
            $table->string('name', 64)->comment('结算方式名称');
            $table->integer('days')->default(0)->comment('账期天数');
            $table->string('description', 255)->nullable();
            $table->boolean('status')->default(true)->comment('1启用 0停用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terms');

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('tax_entities', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
