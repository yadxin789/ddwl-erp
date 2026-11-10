<?php

namespace App\Http\Controllers;

use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialStandardCost;
use App\Services\StandardCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 标准成本维护与差异分析（P1-02 / D-14 / D-20 / §9.4）
 * 仅财务（会计）与总经理可见：权限 cost.standard.view / cost.standard.manage
 */
class StandardCostController extends Controller
{
    public function __construct(protected StandardCostService $costs)
    {
    }

    /**
     * 版本列表：按物料分组显示 active 版本；标记 demo / fallback，业务角色无权访问。
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->input('q'));

        $materials = Material::query()
            ->when($keyword !== '', fn ($q) => $q->where('material_code', 'like', "%{$keyword}%")
                ->orWhere('name', 'like', "%{$keyword}%"))
            ->orderBy('material_code')
            ->paginate(20)
            ->withQueryString();

        $active = MaterialStandardCost::whereIn('material_id', $materials->pluck('id'))
            ->active()
            ->get()
            ->keyBy('material_id');

        $demoCount = MaterialStandardCost::where('source', 'demo')->where('status', 'active')->count();
        $fallbackCount = MaterialStandardCost::where('source', 'fallback')->where('status', 'active')->count();
        $missingCount = Material::count() - MaterialStandardCost::where('status', 'active')->distinct('material_id')->count('material_id');

        return view('finance.standard-costs', compact('materials', 'active', 'keyword', 'demoCount', 'fallbackCount', 'missingCount'));
    }

    /**
     * 新建版本（改价唯一入口，禁止覆盖历史）。
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'material_id' => ['required', 'exists:materials,id'],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->costs->setVersion(
                (int) $data['material_id'],
                (float) $data['standard_cost'],
                $data['effective_from'],
                auth()->id(),
                'manual',
                $data['remark'] ?? null
            );

            return back()->with('success', '标准成本新版本已生效（旧版本自动 superseded）。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['standard_cost' => $e->getMessage()])->withInput();
        }
    }

    public function deactivate(Request $request)
    {
        $data = $request->validate(['version_id' => ['required', 'exists:material_standard_costs,id']]);

        try {
            $this->costs->deactivate((int) $data['version_id'], auth()->id());

            return back()->with('success', '该标准成本版本已停用。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['standard_cost' => $e->getMessage()]);
        }
    }

    /**
     * 差异分析：采购价差（按供应商/物料）+ 标准成本毛利。
     */
    public function variance(Request $request)
    {
        $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to') ?: now()->toDateString();

        // 采购价差：入库流水里 std_cost 与 actual_cost 都有的行
        $varianceRows = InventoryTransaction::query()
            ->selectRaw('material_id, SUM(qty_change) as qty, SUM(price_variance) as variance')
            ->where('txn_type', 'receipt')
            ->whereNotNull('price_variance')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->groupBy('material_id')
            ->orderByDesc(DB::raw('ABS(SUM(price_variance))'))
            ->limit(50)
            ->get();

        $materials = Material::whereIn('id', $varianceRows->pluck('material_id'))
            ->get()->keyBy('id');

        // 毛利（标准成本口径）：开票净额 − 标准成本结转
        $grossRows = DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.order_id')
            ->whereNotNull('l.std_cost_amount')
            ->where('l.shipped_qty', '>', 0)
            ->whereBetween(DB::raw('DATE(o.order_date)'), [$from, $to])
            ->selectRaw('l.material_id, SUM(l.shipped_qty) as qty, SUM(l.shipped_qty * l.unit_price) as revenue, SUM(l.std_cost_amount) as std_amount')
            ->groupBy('l.material_id')
            ->get();

        $gross = $grossRows->map(fn ($r) => [
            'material' => $materials[$r->material_id] ?? null,
            'qty' => (float) $r->qty,
            'revenue' => round((float) $r->revenue, 2),
            'std_amount' => round((float) $r->std_amount, 2),
            'gross_profit' => round((float) $r->revenue - (float) $r->std_amount, 2),
        ]);

        $varianceTotal = round((float) $varianceRows->sum('variance'), 2);
        $grossTotal = round((float) $gross->sum('gross_profit'), 2);

        // 价差（按供应商）：入库流水 → 收货单 → 采购单 → 供应商
        $supplierVariance = DB::table('inventory_transactions as t')
            ->join('goods_receipts as g', 'g.id', '=', 't.ref_id')
            ->join('purchase_orders as p', 'p.id', '=', 'g.po_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('t.ref_type', 'goods_receipt')
            ->whereNotNull('t.price_variance')
            ->whereBetween(DB::raw('DATE(t.created_at)'), [$from, $to])
            ->selectRaw('s.supplier_name as name, SUM(t.price_variance) as variance')
            ->groupBy('s.id', 's.supplier_name')
            ->orderByDesc(DB::raw('ABS(SUM(t.price_variance))'))
            ->get();

        // 量差（标准用量 vs 实际领料）：生产工单按 BOM 单位用量 × 计划数量，对比领料单实际数量
        $qtyVariance = DB::table('production_orders as po')
            ->join('bom_lines as bl', 'bl.bom_header_id', '=', 'po.bom_id')
            ->leftJoinSub(
                DB::table('material_issue_lines as il')
                    ->join('material_issues as mi', 'mi.id', '=', 'il.material_issue_id')
                    ->selectRaw('mi.production_order_id, il.material_id, SUM(il.qty) as actual_qty')
                    ->groupBy('mi.production_order_id', 'il.material_id'),
                'act',
                fn ($j) => $j->on('act.production_order_id', '=', 'po.id')->on('act.material_id', '=', 'bl.material_id')
            )
            ->whereBetween(DB::raw('DATE(po.created_at)'), [$from, $to])
            ->selectRaw('bl.material_id, SUM(po.plan_qty * bl.qty_per) as std_qty, COALESCE(SUM(act.actual_qty), 0) as actual_qty')
            ->groupBy('bl.material_id')
            ->get()
            ->map(fn ($r) => (object) [
                'material_id' => $r->material_id,
                'std_qty' => (float) $r->std_qty,
                'actual_qty' => (float) $r->actual_qty,
                'diff_qty' => round((float) $r->actual_qty - (float) $r->std_qty, 2),
            ]);

        $qtyMaterials = Material::whereIn('id', $qtyVariance->pluck('material_id'))->get()->keyBy('id');

        return view('finance.cost-variance', compact(
            'varianceRows', 'materials', 'gross', 'varianceTotal', 'grossTotal', 'from', 'to',
            'supplierVariance', 'qtyVariance', 'qtyMaterials'
        ));
    }
}
