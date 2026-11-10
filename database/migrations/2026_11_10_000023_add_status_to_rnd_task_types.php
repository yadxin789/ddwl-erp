<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_task_types', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('id')->comment('任务类型编码');
            $table->boolean('status')->default(true)->after('sort_order')->comment('1启用 0停用');
        });

        // 回填稳定编码：按既有排序号顺序生成，供归口维护和重复 seed 识别
        $types = DB::table('rnd_task_types')->orderBy('sort_order')->orderBy('id')->get();
        foreach ($types as $index => $type) {
            DB::table('rnd_task_types')
                ->where('id', $type->id)
                ->update(['code' => sprintf('RND-TT-%03d', $index + 1)]);
        }

        Schema::table('rnd_task_types', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_task_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'status']);
        });
    }
};
