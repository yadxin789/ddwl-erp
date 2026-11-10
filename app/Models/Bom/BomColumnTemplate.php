<?php

namespace App\Models\Bom;

use Illuminate\Database\Eloquent\Model;

class BomColumnTemplate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'template_name', 'mpn_column', 'qty_column', 'spec_column',
        'refdes_column', 'manufacturer_column', 'name_column', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'integer',
    ];
}
