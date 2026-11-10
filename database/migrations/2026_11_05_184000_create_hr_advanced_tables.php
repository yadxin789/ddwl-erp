<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_recruitments', function (Blueprint $t) {
            $t->id();
            $t->string('req_no', 32)->unique();
            $t->string('position', 64);
            $t->integer('headcount')->default(1);
            $t->string('department', 64)->nullable();
            $t->string('requirement', 500)->nullable();
            $t->string('status', 16)->default('open');
            $t->timestamps();
        });

        Schema::create('hr_salaries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('period', 8)->comment('YYYYMM');
            $t->decimal('base', 12, 2)->default(0);
            $t->decimal('bonus', 12, 2)->default(0);
            $t->decimal('deduction', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('hr_performances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('period', 8);
            $t->tinyInteger('score')->default(60);
            $t->string('grade', 8)->nullable()->comment('S/A/B/C/D');
            $t->text('comment')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_performances');
        Schema::dropIfExists('hr_salaries');
        Schema::dropIfExists('hr_recruitments');
    }
};
