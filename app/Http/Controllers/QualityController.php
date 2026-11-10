<?php

namespace App\Http\Controllers;

use App\Enums\InspectionStage;
use App\Models\MasterData\Material;
use App\Models\Quality\Defect;
use App\Models\Quality\InspectionRecord;
use App\Models\Quality\InspectionTemplate;
use App\Services\QualityService;
use Illuminate\Http\Request;

class QualityController extends Controller
{
    public function __construct(protected QualityService $service)
    {
    }

    public function templates()
    {
        $templates = InspectionTemplate::orderBy('stage')->paginate(15);

        return view('quality.templates', compact('templates'));
    }

    public function templateStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:inspection_templates,code'],
            'name' => ['required', 'string', 'max:128'],
            'stage' => ['required', 'in:' . implode(',', array_column(InspectionStage::cases(), 'value'))],
            'standard' => ['nullable', 'string', 'max:64'],
        ]);
        $this->service->createTemplate($data);

        return back()->with('success', '检验模板已创建。');
    }

    public function inspections(Request $request)
    {
        $records = InspectionRecord::with(['material', 'inspector'])
            ->when($request->stage, fn($q, $s) => $q->where('stage', $s))
            ->orderByDesc('id')->paginate(15);

        return view('quality.inspections', compact('records'));
    }

    /**
     * P1-06 IQC 独立待检台：qc_hold 批次列表（物料/供应商/到货日），按岗位显示自己的检验阶段。
     */
    public function pending()
    {
        $user = auth()->user();
        $stage = $this->service->stageForUser($user);
        $locked = $this->service->isStageLocked($user);
        $batches = $this->service->pendingBatches();

        return view('quality.pending', compact('batches', 'stage', 'locked'));
    }

    public function inspectionCreate()
    {
        $materials = Material::where('status', 1)->orderBy('material_code')->get();
        $user = auth()->user();
        $stage = $this->service->stageForUser($user);
        $locked = $this->service->isStageLocked($user);

        return view('quality.inspection-create', compact('materials', 'stage', 'locked'));
    }

    public function inspectionStore(Request $request)
    {
        $data = $request->validate([
            'stage' => ['required', 'in:' . implode(',', array_column(InspectionStage::cases(), 'value'))],
            'material_id' => ['required', 'exists:materials,id'],
            'sample_qty' => ['required', 'numeric', 'min:0'],
            'qualified_qty' => ['required', 'numeric', 'min:0'],
            'defect_qty' => ['required', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        // P1-06：按岗位锁定阶段，跨阶段录入直接 422（IQC 只能录 IQC，IPQC 只能录 IPQC，FQC 只能录 FQC）
        $lockedStage = $this->service->stageForUser(auth()->user());
        if ($lockedStage !== null && $data['stage'] !== $lockedStage) {
            abort(422, "本岗位只能录入 {$lockedStage} 阶段的检验记录，不能录入 {$data['stage']}");
        }

        $this->service->createInspection($data);

        return redirect()->route('quality.inspections')->with('success', '检验记录已提交。');
    }

    public function defects()
    {
        $defects = Defect::orderBy('code')->paginate(20);

        return view('quality.defects', compact('defects'));
    }

    public function defectStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'unique:defects,code'],
            'name' => ['required', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:32'],
        ]);
        $this->service->createDefect($data);

        return back()->with('success', '缺陷代码已添加。');
    }
}
