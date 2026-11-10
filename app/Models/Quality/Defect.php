<?php

namespace App\Models\Quality;

use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    protected $fillable = ['code', 'name', 'category', 'description'];
}
