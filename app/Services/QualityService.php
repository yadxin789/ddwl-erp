<?php

namespace App\Services;

use App\Models\Quality\Defect;
use App\Models\Quality\InspectionRecord;
use App\Models\Quality\InspectionTemplate;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 质量服务：检验模板 / 检验记录 / 缺陷代码
 * P1-03：业务逻辑（单号、判定结果、检验人）下沉到 Service
 */
class QualityService
{
    public function createTemplate(array $data): InspectionTemplate
    {
        Log::info('质检模板创建', ['user_id' => Auth::id(), 'code' => $data['code']]);
        $data['check_items'] = [];
        $data['status'] = true;

        return InspectionTemplate::create($data);
    }

    public function createInspection(array $data): InspectionRecord
    {
        Log::info('质检记录提交', ['user_id' => Auth::id(), 'material_id' => $data['material_id'], 'defect_qty' => $data['defect_qty']]);
        $data['record_no'] = NumberGenerator::generate('inspection');
        $data['result'] = ($data['defect_qty'] ?? 0) == 0 ? 'accepted' : 'rejected';
        $data['inspector_id'] = Auth::id();
        $data['inspected_at'] = now();

        return InspectionRecord::create($data);
    }

    /**
     * P1-06：岗位锁定检验阶段（IQC/IPQC/FQC 各管自己的阶段）。
     * 品质主管（role_qc_manager）不锁定，可代录；其他岗位返回 null（跨阶段一律拒绝）。
     */
    public function stageForUser($user): ?string
    {
        if (! $user) {
            return null;
        }
        $map = [
            'role_qc_iqc' => 'IQC',
            'role_qc_ipqc' => 'IPQC',
            'role_qc_fqc' => 'FQC',
        ];
        foreach ($map as $role => $stage) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return $stage;
            }
        }

        // 品质主管允许全阶段（代录/复核）
        if (method_exists($user, 'hasRole') && $user->hasRole('role_qc_manager')) {
            return null;
        }

        return null;
    }

    public function isStageLocked($user): bool
    {
        return in_array($this->stageForUser($user), ['IQC', 'IPQC', 'FQC'], true);
    }

    /**
     * P1-06 IQC 待检台：qc_hold 库存批次 → 物料/仓库/批次/数量，反查最近收货单拿供应商与到货日。
     */
    public function pendingBatches()
    {
        $rows = \App\Models\Inventory\InventoryStock::withoutGlobalScopes()
            ->with(['material'])
            ->where('status', \App\Enums\StockStatus::QcHold->value)
            ->where('qty', '>', 0)
            ->orderBy('receive_date')
            ->orderBy('material_id')
            ->get();

        return $rows->map(function ($row) {
            $line = \App\Models\Purchasing\GoodsReceiptLine::where('material_id', $row->material_id)
                ->where('lot_no', (string) $row->lot_no)
                ->orderByDesc('id')
                ->first();
            $receipt = $line
                ? \App\Models\Purchasing\GoodsReceipt::withoutGlobalScopes()->with('supplier')->find($line->receipt_id)
                : null;

            return (object) [
                'stock' => $row,
                'material' => $row->material,
                'lot_no' => $row->lot_no,
                'qty' => (float) $row->qty,
                'warehouse_id' => $row->warehouse_id,
                'receipt' => $receipt,
                'supplier' => $receipt?->supplier,
                'receipt_date' => $receipt?->receipt_date ?? $row->receive_date,
            ];
        });
    }

    public function createDefect(array $data): Defect
    {
        Log::info('缺陷代码创建', ['user_id' => Auth::id(), 'code' => $data['code']]);

        return Defect::create($data);
    }
}
