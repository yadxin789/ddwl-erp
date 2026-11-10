<?php

namespace App\Http\Controllers;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\Inventory\Warehouse;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryAdjustmentService $adjustments)
    {
    }
    /**
     * 库存查询（按物料/仓库/状态筛选）
     */
    public function index(Request $request)
    {
        $query = InventoryStock::query()->with(['material', 'warehouse', 'location']);

        if ($request->filled('material_id')) {
            $query->where('material_id', $request->input('material_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $stocks = $query->orderBy('material_id')->paginate(20)->withQueryString();
        $materials = Material::where('status', 1)->orderBy('material_code')->get(['id', 'material_code', 'name']);
        $warehouses = Warehouse::where('status', 1)->orderBy('warehouse_code')->get();

        return view('inventory.index', compact('stocks', 'materials', 'warehouses'));
    }

    /**
     * 库存流水查询
     */
    public function transactions(Request $request)
    {
        $query = InventoryTransaction::query()->with(['material', 'operator']);

        if ($request->filled('material_id')) {
            $query->where('material_id', $request->input('material_id'));
        }
        if ($request->filled('txn_type')) {
            $query->where('txn_type', $request->input('txn_type'));
        }

        $transactions = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $materials = Material::where('status', 1)->orderBy('material_code')->get(['id', 'material_code', 'name']);

        return view('inventory.transactions', compact('transactions', 'materials'));
    }

    /**
     * 库存调整（P0-04 两段化）：待审单据 + 调整历史 + 可调整库存行
     * 归属仓储（v1.3 §4.21）：发起 `inventory.adjust.request`，审批 `inventory.adjust.approve`。
     */
    public function adjustments()
    {
        $pending = InventoryAdjustment::query()
            ->with(['material', 'warehouse', 'requester'])
            ->pending()
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'pending_page');

        $history = InventoryAdjustment::query()
            ->with(['material', 'warehouse', 'requester', 'approver'])
            ->whereIn('status', ['approved', 'auto_approved', 'void'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'history_page');

        $stocks = InventoryStock::query()
            ->with(['material', 'warehouse', 'location'])
            ->where('qty', '>', 0)
            ->orderBy('material_id')
            ->limit(200)
            ->get();

        $threshold = $this->adjustments->threshold();

        return view('inventory.adjustments', compact('pending', 'history', 'stocks', 'threshold'));
    }

    /**
     * 提交库存调整申请：低于阈值当场生效（auto_approved），超阈值挂审批
     */
    public function adjustmentStore(Request $request)
    {
        $data = $request->validate([
            'stock_id' => ['required', 'exists:inventory_stock,id'],
            'new_qty' => ['required', 'numeric', 'min:0'],
            'remark' => ['required', 'string', 'max:200'],
        ]);

        try {
            $adj = $this->adjustments->requestManual(
                (int) $data['stock_id'],
                (float) $data['new_qty'],
                $data['remark'],
                $request->user()?->id
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            return back()->withErrors(['new_qty' => $e->getMessage()]);
        }

        if ($adj->status === 'auto_approved') {
            return back()->with('success', sprintf(
                '调整单 %s：金额 %s 元低于阈值 %s 元，已自动通过并生效（%+0.2f）。',
                $adj->adjustment_no,
                number_format((float) $adj->amount, 2),
                number_format((float) $adj->threshold, 2),
                (float) $adj->delta_qty
            ));
        }

        return back()->with('success', sprintf(
            '调整单 %s：金额 %s 元 ≥ 阈值 %s 元，已提交仓库主管审批，库存尚未变动。',
            $adj->adjustment_no,
            $adj->amount === null ? '无法计价' : number_format((float) $adj->amount, 2),
            number_format((float) $adj->threshold, 2)
        ));
    }

    /**
     * 审批通过：生效并写流水（重复批准幂等）
     */
    public function adjustmentApprove(Request $request, int $adjustment)
    {
        $data = $request->validate(['remark' => ['nullable', 'string', 'max:200']]);

        try {
            $adj = $this->adjustments->approve($adjustment, (int) $request->user()->id, $data['remark'] ?? null);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            '调整单 %s 已生效（%+0.2f，%0.2f → %0.2f）。',
            $adj->adjustment_no,
            (float) $adj->delta_qty,
            (float) $adj->before_qty,
            (float) $adj->after_qty
        ));
    }

    /**
     * 审批驳回：库存不动，单据作废
     */
    public function adjustmentReject(Request $request, int $adjustment)
    {
        $data = $request->validate(['remark' => ['required', 'string', 'max:200']]);

        try {
            $adj = $this->adjustments->reject($adjustment, (int) $request->user()->id, $data['remark']);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['reject' => $e->getMessage()]);
        }

        return back()->with('success', sprintf('调整单 %s 已驳回，库存未变动。', $adj->adjustment_no));
    }
}
