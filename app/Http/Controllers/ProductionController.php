<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\ProductionIssueRequest;
use App\Http\Requests\Production\StocktakeAdjustRequest;
use App\Models\Bom\BomHeader;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\Material;
use App\Models\Production\MaterialIssue;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionOperationLog;
use App\Models\Production\Routing;
use App\Models\Production\Stocktake;
use App\Models\Production\WorkCenter;
use App\Services\ProductionOrderService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionOrderService $service
    ) {
    }

    /* ---------- 工单 ---------- */

    public function orders(Request $request)
    {
        $orders = ProductionOrder::with('product')
            ->when($request->q, fn ($q, $kw) => $q->where('order_no', 'like', "%{$kw}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('production.orders', compact('orders'));
    }

    public function orderCreate()
    {
        $products = Material::where('status', 1)->whereIn('material_code', ['FG', 'SM'])
            ->orWhere('material_code', 'like', 'FG%')->orWhere('material_code', 'like', 'SM%')
            ->orderBy('material_code')->get();
        $boms = BomHeader::where('status', 'approved')->with('product')->get();

        return view('production.order-create', compact('products', 'boms'));
    }

    public function orderStore(Request $request)
    {
        $data = $request->validate([
            'product_material_id' => ['required', 'exists:materials,id'],
            'bom_id' => ['required', 'exists:bom_headers,id'],
            'plan_qty' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->service->createOrder($data);

        return redirect()->route('production.orders.show', $order)->with('success', "工单 {$order->order_no} 已创建。");
    }

    public function orderShow(ProductionOrder $order)
    {
        $order->load(['product', 'bom.lines.material', 'routing', 'operationLogs.worker', 'issues.lines.material']);
        $requirements = $this->service->bomRequirements($order);
        $warehouses = Warehouse::where('status', 1)->get();

        return view('production.order-show', compact('order', 'requirements', 'warehouses'));
    }

    public function orderRelease(ProductionOrder $order)
    {
        try {
            $this->service->release($order->id);
            return back()->with('success', "工单 {$order->order_no} 已下达。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }
    }

    public function issueMaterial(ProductionIssueRequest $request, ProductionOrder $order)
    {
        $validated = $request->validated();
        $lines = [];
        foreach ($validated['material_id'] as $i => $mid) {
            $qty = (float) ($validated['qty'][$i] ?? 0);
            if ($qty > 0) {
                $lines[] = [
                    'material_id' => (int) $mid,
                    'lot_no' => $validated['lot_no'][$i] ?? '',
                    'qty' => $qty,
                ];
            }
        }
        try {
            $this->service->issueMaterial($order->id, (int) $validated['warehouse_id'], $lines);
            return back()->with('success', '领料完成，原料库存已扣减。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['issue' => $e->getMessage()]);
        }
    }

    /* ---------- P0-06 我的报工（无参入口） ---------- */

    public function reports()
    {
        $userId = auth()->id();

        $logs = ProductionOperationLog::with(['productionOrder.product'])
            ->where('worker_id', $userId)
            ->orderByDesc('reported_at')
            ->paginate(20);

        $today = ProductionOperationLog::where('worker_id', $userId)
            ->whereDate('reported_at', today())
            ->selectRaw('COALESCE(SUM(good_qty),0) AS good_qty, COALESCE(SUM(defective_qty),0) AS defective_qty, COALESCE(SUM(work_hours),0) AS work_hours')
            ->first();

        // 可继续报工的工单（已下达 / 生产中）
        $orders = ProductionOrder::with('product')
            ->whereIn('status', ['released', 'in_progress'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('production.reports', compact('logs', 'today', 'orders'));
    }

    public function report(Request $request, ProductionOrder $order)
    {
        $data = $request->validate([
            'operation_name' => ['required', 'string', 'max:64'],
            'good_qty' => ['required', 'numeric', 'min:0'],
            'defective_qty' => ['nullable', 'numeric', 'min:0'],
            'defect_code' => ['nullable', 'string', 'max:32'],
            'work_hours' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $this->service->report($order->id, $data);
            return back()->with('success', '报工已记录。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }
    }

    public function complete(Request $request, ProductionOrder $order)
    {
        $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id']]);
        try {
            $this->service->complete($order->id, (int) $request->input('warehouse_id'));
            return back()->with('success', "工单 {$order->order_no} 完工入库完成。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['complete' => $e->getMessage()]);
        }
    }

    /* ---------- 盘点 ---------- */

    public function stocktakes()
    {
        $stocktakes = Stocktake::with('warehouse')->orderByDesc('id')->paginate(15);

        return view('production.stocktakes', compact('stocktakes'));
    }

    public function stocktakeCreate(Request $request)
    {
        $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id']]);
        try {
            $st = $this->service->createStocktake((int) $request->input('warehouse_id'));
            return back()->with('success', "盘点单 {$st->stocktake_no} 已创建，共 {$st->lines->count()} 条账面记录。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['stocktake' => $e->getMessage()]);
        }
    }

    public function stocktakeShow(Stocktake $stocktake)
    {
        $stocktake->load('warehouse', 'lines.material');

        return view('production.stocktake-show', compact('stocktake'));
    }

    public function stocktakeAdjust(StocktakeAdjustRequest $request, Stocktake $stocktake)
    {
        try {
            $st = $this->service->adjustStocktake($stocktake->id, $request->validated()['actual']);

            if ($st->status === 'adjusted') {
                return back()->with('success', '盘点差异已全部生效（低于阈值自动通过），库存流水已记录。');
            }

            return back()->with('success', '盘点差异已登记：部分差异金额超过审批阈值，已生成库存调整单待仓库主管审批，库存暂未变动。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['adjust' => $e->getMessage()]);
        }
    }

    /* ---------- 工作中心 ---------- */
    public function workCenters()
    {
        $centers = WorkCenter::orderBy('code')->paginate(20);

        return view('production.work-centers', compact('centers'));
    }

    public function workCenterStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:work_centers,code'],
            'name' => ['required', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:64'],
            'capacity_per_day' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['status'] = 'active';
        WorkCenter::create($data);

        return back()->with('success', '工作中心已添加。');
    }

    /* ---------- 工艺路线 ---------- */
    public function routings()
    {
        $routings = Routing::with('product')->orderByDesc('id')->paginate(15);
        $products = Material::where('status', 1)->where('material_code', 'like', 'FG%')->get();
        $centers = WorkCenter::where('status', 'active')->get();

        return view('production.routings', compact('routings', 'products', 'centers'));
    }

    public function routingStore(Request $request)
    {
        $data = $request->validate([
            'routing_code' => ['required', 'string', 'max:32', 'unique:routings,routing_code'],
            'product_material_id' => ['required', 'exists:materials,id'],
            'version' => ['nullable', 'string', 'max:16'],
        ]);
        $data['version'] = $data['version'] ?? 'V1.0';
        $data['status'] = 'active';
        Routing::create($data);

        return back()->with('success', '工艺路线已创建。');
    }

    /* ---------- 领料单列表 ---------- */
    public function issues()
    {
        $issues = MaterialIssue::with(['productionOrder', 'warehouse'])->orderByDesc('id')->paginate(15);

        return view('production.issues', compact('issues'));
    }
}
