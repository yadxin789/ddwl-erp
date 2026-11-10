<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3 G10：票据管理（出纳持有的应收/应付票据登记）
     */
    public function up(): void
    {
        Schema::create('finance_notes', function (Blueprint $table) {
            $table->id();
            $table->string('note_no', 32)->unique()->comment('票据编号');
            $table->string('type', 16)->default('bank')->comment('bank=银行承兑 commercial=商业承兑');
            $table->decimal('amount', 14, 2)->comment('票面金额');
            $table->string('drawer', 64)->nullable()->comment('出票人');
            $table->date('due_date')->comment('到期日');
            $table->string('status', 16)->default('holding')->comment('holding/endorsed/discounted/settled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_notes');
    }
};
