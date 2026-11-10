<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $fillable = ['tx_no', 'category', 'amount', 'direction', 'summary', 'tx_date'];
    protected $casts = ['tx_date' => 'date'];
}
