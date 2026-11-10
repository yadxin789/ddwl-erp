<?php

namespace App\Models\Purchasing;

use Illuminate\Database\Eloquent\Model;

/** P3-6 供应商绩效快照（按期生成，可复算）。 */
class SupplierPerformanceScore extends Model
{
    protected $fillable = [
        'period_start', 'period_end', 'supplier_id', 'tenant_id', 'supplier_code', 'supplier_name',
        'receipt_count', 'on_time_count', 'late_count', 'qualified_qty', 'rejected_qty',
        'on_time_rate', 'quality_rate', 'score', 'grade', 'detail_json', 'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'receipt_count' => 'integer',
        'on_time_count' => 'integer',
        'late_count' => 'integer',
        'qualified_qty' => 'decimal:2',
        'rejected_qty' => 'decimal:2',
        'on_time_rate' => 'decimal:2',
        'quality_rate' => 'decimal:2',
        'score' => 'decimal:2',
        'detail_json' => 'array',
    ];
}
