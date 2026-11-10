<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P3-1 ECO 实际替换：受影响物料 → 替代物料一一对应。 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecr_change_orders', function (Blueprint $table) {
            $table->json('replacement_material_ids')->nullable()->after('impacted_material_ids')
                ->comment('替代物料 id 列表，与受影响物料一一对应');
        });
    }

    public function down(): void
    {
        Schema::table('ecr_change_orders', function (Blueprint $table) {
            $table->dropColumn('replacement_material_ids');
        });
    }
};
