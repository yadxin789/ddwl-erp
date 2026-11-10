<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinanceNote extends Model
{
    protected $fillable = ['note_no', 'type', 'amount', 'drawer', 'due_date', 'status'];

    protected $casts = ['due_date' => 'date'];
}
