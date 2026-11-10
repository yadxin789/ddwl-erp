<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinanceReconcile extends Model
{
    protected $fillable = ['reconcile_no', 'period', 'type', 'book_amount', 'actual_amount', 'diff_amount', 'status'];
    protected $casts = ['period' => 'date'];
}
