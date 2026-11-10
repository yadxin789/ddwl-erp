<?php

namespace App\Http\Controllers;

use App\Models\Procurement\MrpSuggestion;
use App\Models\Procurement\ProcurementPlan;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\PurchaseRequisitionLine;
use App\Services\MrpService;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcurementController extends Controller
{
    public function __construct(protected MrpService $mrp)
    {
    }

    public function index()
    {
        $plans = ProcurementPlan::orderByDesc('id')->paginate(15);

        return view('procurement.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:128'],
            'plan_date' => ['required', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['plan_no'] = NumberGenerator::generate('plan');
        $data['status'] = 'draft';
        ProcurementPlan::create($data);

        return back()->with('success', '采购计划已创建。');
    }

    /* ---------- P1-08 MRP / 采购建议 ---------- */

    public function mrp()
    {
        $suggestions = MrpSuggestion::with(['material', 'supplier'])
            ->where('status', 'open')
            ->orderBy('required_date')
            ->orderByDesc('net_qty')
            ->get();

        $converted = MrpSuggestion::with(['material', 'supplier'])
            ->where('status', 'converted')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('procurement.mrp', compact('suggestions', 'converted'));
    }

    public function mrpRun()
    {
        $count = $this->mrp->run();

        return redirect()->route('proc.mrp')->with('success', "MRP 已重新计算：生成 {$count} 条采购建议（已转申请/已忽略的保留不动）。");
    }

    /** 建议 → 采购申请（一条建议一张申请，转后建议关闭） */
    public function mrpConvert(int $suggestion)
    {
        $row = MrpSuggestion::findOrFail($suggestion);
        if ($row->status !== 'open') {
            return back()->withErrors(['mrp' => '该建议已处理过（状态 ' . $row->status . '），不能重复转申请。']);
        }

        $req = DB::transaction(function () use ($row) {
            $req = PurchaseRequisition::create([
                'req_no' => NumberGenerator::generate('purchase_requisition'),
                'title' => 'MRP 建议采购：' . ($row->material->material_code ?? ('#' . $row->material_id)),
                'department_id' => (int) optional(Auth::user())->department_id,
                'requester_id' => Auth::id(),
                'need_date' => $row->required_date,
                'status' => 'pending',
                'remark' => '由 MRP 建议 #' . $row->id . ' 生成；' . $row->demand_source,
            ]);

            PurchaseRequisitionLine::create([
                'requisition_id' => $req->id,
                'material_id' => $row->material_id,
                'qty' => $row->net_qty,
                'unit' => $row->material->unit ?? 'PCS',
                'estimated_price' => $row->material->cost_price ?? 0,
                'remark' => $row->demand_source,
            ]);

            $row->update(['status' => 'converted', 'requisition_id' => $req->id]);

            return $req;
        });

        Log::info('MRP 建议转采购申请', ['suggestion_id' => $row->id, 'requisition_id' => $req->id, 'user_id' => Auth::id()]);

        return redirect()->route('proc.mrp')->with('success', "已转采购申请 {$req->req_no}，建议已关闭。");
    }
}
