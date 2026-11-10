<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('manager', 64)->nullable();
            $t->timestamps();
        });

        Schema::create('hr_employees', function (Blueprint $t) {
            $t->id();
            $t->string('emp_no', 32)->unique();
            $t->string('name', 64);
            $t->string('gender', 8)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('phone', 32)->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->string('position', 64)->nullable();
            $t->date('hire_date')->nullable();
            $t->string('status', 16)->default('active')->comment('active/leave/quit');
            $t->timestamps();
        });

        Schema::create('hr_attendances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('work_date');
            $t->timestamp('check_in')->nullable();
            $t->timestamp('check_out')->nullable();
            $t->string('status', 16)->default('normal')->comment('normal/late/early/absent/leave');
            $t->timestamps();
        });

        Schema::create('hr_leaves', function (Blueprint $t) {
            $t->id();
            $t->string('leave_no', 32)->unique();
            $t->unsignedBigInteger('employee_id');
            $t->string('type', 16)->comment('annual/sick/personal/marriage');
            $t->date('from_date');
            $t->date('to_date');
            $t->decimal('days', 4, 1)->default(0);
            $t->string('reason', 500)->nullable();
            $t->string('status', 16)->default('pending');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leaves');
        Schema::dropIfExists('hr_attendances');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_departments');
    }
};
