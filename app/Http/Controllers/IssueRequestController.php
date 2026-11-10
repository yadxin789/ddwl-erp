<?php

namespace App\Http\Controllers;

use App\Models\Bom\BomLine;
use App\Models\MasterData\Material;
use App\Models\Production\MaterialIssueRequest;
use App\Models\Production\MaterialIssueRequestLine;
use App\Models\Production\ProductionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 领料申请（G9）· v1.3 §4.3 / D-13
 *
 * 规则：生产组长按"物料 + 数量 + 工单 + 用途"申请，不设金额上限；
 *      超出该工单 BOM 标准用量的部分需生产主管审批，并标"超耗"。
 */
class IssueRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = MaterialIssueRequest::query()
            ->with(['productionOrder', 'requester', 'approver', 'lines.material'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('production.issue-requests', compact('requests'));
    }

    public function create()
    {
        $orders = ProductionOrder::query()
            ->whereIn('status', ['planned', 'in_progress', 'released'])
            ->orderByDesc('id')
            ->get(['id', 'order_no', 'plan_qty']);
        $materials = Material::where('status', 1)->orderBy('material_code')->get(['id', 'material_code', 'name']);

        return view('production.issue-request-create', compact('orders', 'materials'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'production_order_id' => ['required', 'exists:production_orders,id'],
            'purpose' => ['required', 'string', 'max:200'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['required', 'exists:materials,id'],
            'qty' => ['required', 'array', 'min:1'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
        ], [], [
            'production_order_id' => '工单',
            'purpose' => '用途',
        ]);

        $order = ProductionOrder::findOrFail($data['production_order_id']);
        $standards = $this->standardQtyByMaterial($order);

        $lines = [];
        $hasOverBom = false;
        foreach ($data['material_id'] as $i => $materialId) {
            $qty = round((float) $data['qty'][$i], 2);
            $standard = $standards[(int) $materialId] ?? null;
            $over = $standard !== null && $qty > $standard + 0.0001;

            $lines[] = [
                'material_id' => (int) $materialId,
                'qty' => $qty,
                'standard_qty' => $standard,
                'is_over_bom' => $over,
            ];
            $hasOverBom = $hasOverBom || $over;
        }

        $issueRequest = DB::transaction(function () use ($data, $order, $lines, $hasOverBom, $request) {
            $record = MaterialIssueRequest::create([
                'request_no' => $this->nextRequestNo(),
                'production_order_id' => $order->id,
                'purpose' => $data['purpose'],
                // 未超 BOM 无需审批，直接生效；超耗部分等生产主管审批
                'status' => $hasOverBom ? 'pending_approval' : 'approved',
                'has_over_bom' => $hasOverBom,
                'requested_by' => $request->user()->id,
                'approved_at' => $hasOverBom ? null : now(),
            ]);

            foreach ($lines as $line) {
                $record->lines()->create($line);
            }

            return $record;
        });

        $message = $hasOverBom
            ? "领料申请 {$issueRequest->request_no} 已提交，含超 BOM 标准用量行，等待生产主管审批。"
            : "领料申请 {$issueRequest->request_no} 已提交（未超 BOM，可直接发料）。";

        return redirect()->route('production.issue-requests')->with('success', $message);
    }

    public function approve(Request $request, MaterialIssueRequest $issueRequest)
    {
        if ($issueRequest->status !== 'pending_approval') {
            return back()->withErrors(['status' => '该申请当前状态为 ' . $issueRequest->status . '，无需审批']);
        }

        $issueRequest->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', "领料申请 {$issueRequest->request_no} 已批准（含超耗）。");
    }

    public function reject(Request $request, MaterialIssueRequest $issueRequest)
    {
        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:200']]);

        if ($issueRequest->status !== 'pending_approval') {
            return back()->withErrors(['status' => '该申请当前状态不可驳回']);
        }

        $issueRequest->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'reject_reason' => $data['reject_reason'],
        ]);

        return back()->with('success', "领料申请 {$issueRequest->request_no} 已驳回。");
    }

    /** 仓管发料完成后回填状态（发料动作仍走工单领料接口） */
    public function markIssued(MaterialIssueRequest $issueRequest)
    {
        if ($issueRequest->status !== 'approved') {
            return back()->withErrors(['status' => '仅已批准的申请可标记发料']);
        }

        $issueRequest->update(['status' => 'issued']);

        return back()->with('success', "领料申请 {$issueRequest->request_no} 已标记发料完成。");
    }

    /**
     * 按工单 BOM 计算每种物料的标准用量：qty_per × 计划数量 ×（1 + 损耗率%）
     *
     * @return array<int, float> material_id => 标准用量
     */
    private function standardQtyByMaterial(ProductionOrder $order): array
    {
        if (! $order->bom_id) {
            return [];
        }

        $planQty = (float) $order->plan_qty;

        return BomLine::query()
            ->where('bom_header_id', $order->bom_id)
            ->get(['material_id', 'qty_per', 'loss_rate'])
            ->groupBy('material_id')
            ->map(function ($rows) use ($planQty) {
                $sum = 0.0;
                foreach ($rows as $row) {
                    $sum += (float) $row->qty_per * $planQty * (1 + (float) $row->loss_rate / 100);
                }

                return round($sum, 2);
            })->all();
    }

    private function nextRequestNo(): string
    {
        $prefix = 'MIR' . now()->format('Ymd');

        for ($i = 1; $i <= 99; $i++) {
            $candidate = $prefix . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (! MaterialIssueRequest::where('request_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix . '-' . strtoupper(substr(uniqid(), -4));
    }
}
