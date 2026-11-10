<?php

namespace App\Http\Controllers;

use App\Models\Organization\User;
use App\Models\Rnd\RndExtensionApplication;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Services\FileService;
use App\Services\RndProjectService;
use Illuminate\Http\Request;

class RndController extends Controller
{
    public function __construct(protected RndProjectService $service, protected FileService $files)
    {
    }

    public function projects()
    {
        // P0-05：列表口径 = 我创建的 + 我是执行人的项目（与 workspace/my-projects 一致）
        $projects = RndProject::mine()->with('creator')->orderByDesc('id')->paginate(15);

        return view('rnd.projects', compact('projects'));
    }

    public function projectCreate()
    {
        return view('rnd.project-create');
    }

    public function projectStore(Request $request)
    {
        $data = $request->validate([
            'project_name' => ['required', 'string', 'max:128'],
            'product_model' => ['nullable', 'string', 'max:64'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
        $p = $this->service->createProject($data);

        return redirect()->route('rnd.projects.show', $p)->with('success', "项目 {$p->project_code} 已创建。");
    }

    public function projectShow(RndProject $project)
    {
        // P0-05：非参与者不得查看（创建人 / 任务执行人·审核人 / 研发经理）
        $this->authorize('view', $project);

        $project->load(['creator', 'tasks.taskType', 'tasks.executor', 'documents.file', 'documents.uploader']);
        $taskTypes = RndTaskType::orderBy('sort_order')->get();
        $users = User::where('status', 1)->get();   // users 表没有 is_active 列，用 status=1 表示在岗

        // P1-05：基线图档 + 变更单 + 可选 BOM
        $baseline = $project->documents->firstWhere('is_baseline', true);
        $ecrs = \App\Models\Rnd\EcrChangeOrder::withoutGlobalScopes()
            ->with(['applicant', 'bom', 'newBom'])
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $boms = \App\Models\Bom\BomHeader::orderByDesc('id')->limit(50)->get();

        return view('rnd.project-show', compact('project', 'taskTypes', 'users', 'baseline', 'ecrs', 'boms'));
    }

    /* ---------- P1-05 阶段门 / 基线 / ECR ---------- */

    public function projectStage(Request $request, RndProject $project)
    {
        $this->authorize('manage', $project);
        $data = $request->validate(['stage' => ['required', 'in:initiation,design,trial,mass_production']]);

        try {
            $this->service->setStage($project->id, $data['stage']);
            return back()->with('success', '阶段已推进为「' . (RndProject::STAGES[$data['stage']] ?? $data['stage']) . '」。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }
    }

    public function documentBaseline(int $document)
    {
        $doc = RndProjectDocument::withoutGlobalScopes()->findOrFail($document);
        $this->authorize('manage', $doc->project);

        try {
            $this->service->setBaseline($doc->id);
            return back()->with('success', "v{$doc->version_no} 已设为项目基线图档。");
        } catch (\DomainException $e) {
            return back()->withErrors(['baseline' => $e->getMessage()]);
        }
    }

    public function ecrStore(Request $request, RndProject $project)
    {
        $this->authorize('manage', $project);
        $data = $request->validate([
            'bom_id' => ['required', 'exists:bom_headers,id'],
            'change_type' => ['required', 'in:bom,material,process'],
            'title' => ['required', 'string', 'max:128'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $data['project_id'] = $project->id;
        $ecr = $this->service->createEcr($data);

        return back()->with('success', "变更单 {$ecr->change_no} 已提交，等待研发经理审批；批准后才生成新 BOM 版本。");
    }

    /* ---------- P1-05 我的任务：开始 / 提交 / 审核 ---------- */

    public function taskStart(int $task)
    {
        $model = RndProjectTask::withoutGlobalScopes()->findOrFail($task);
        $this->authorize('execute', $model);
        try {
            $this->service->startTask($model->id);
            return back()->with('success', '任务已开始。');
        } catch (\DomainException $e) {
            return back()->withErrors(['task' => $e->getMessage()]);
        }
    }

    public function taskSubmit(int $task)
    {
        $model = RndProjectTask::withoutGlobalScopes()->findOrFail($task);
        $this->authorize('execute', $model);
        try {
            $this->service->submitTask($model->id);
            return back()->with('success', '任务已提交，等待审核。');
        } catch (\DomainException $e) {
            return back()->withErrors(['task' => $e->getMessage()]);
        }
    }

    public function taskDecide(Request $request, int $task)
    {
        $model = RndProjectTask::withoutGlobalScopes()->findOrFail($task);
        $this->authorize('decide', $model);
        $request->validate(['pass' => ['required', 'boolean'], 'remark' => ['nullable', 'string', 'max:255']]);
        try {
            $this->service->decideTask($model->id, (bool) $request->input('pass'), $request->input('remark'));
            return back()->with('success', '任务审核完成。');
        } catch (\DomainException $e) {
            return back()->withErrors(['task' => $e->getMessage()]);
        }
    }

    public function taskStore(Request $request, RndProject $project)
    {
        // P0-05：只有项目创建人或研发经理能派任务
        $this->authorize('createTask', $project);

        $data = $request->validate([
            'task_type_id' => ['required', 'exists:rnd_task_types,id'],
            'executor_id' => ['required', 'exists:users,id'],
            'auditor_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['required', 'date'],
        ]);
        $this->service->addTask($project->id, $data);

        return back()->with('success', '任务已分配，已通知执行人。');
    }

    public function documentUpload(Request $request, RndProjectTask $task)
    {
        // P0-05：只有该任务执行人（或项目创建人）能提交交付物
        $this->authorize('upload', $task);

        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $media = $this->files->upload($request->file('file'), 'rnd_doc', 'rnd_document', $task->id);
        $this->service->uploadDocument($task->id, $media->id);

        return back()->with('success', '文档已上传，等待审核。');
    }

    public function documentAudit(Request $request, int $document)
    {
        // P0-05：不信路由模型绑定（交付物表有"上传人=本人"行级范围，审核人会先 404），自己取单据再授权
        $doc = RndProjectDocument::withoutGlobalScopes()->findOrFail($document);
        $this->authorize('audit', $doc);

        $request->validate(['pass' => ['required', 'boolean'], 'remark' => ['nullable', 'string', 'max:255']]);
        $this->service->auditDocument($doc->id, (bool) $request->input('pass'), $request->input('remark'));

        return back()->with('success', '审核完成。');
    }

    /* ---------- P0-06 无参工作台：我的任务 / 交付物上传 / 交付物审核 ---------- */

    /**
     * 我的任务：执行人视角 + 审核人视角，按紧迫度排序（逾期、驳回在前）。
     */
    public function myTasks()
    {
        $userId = auth()->id();

        $tasks = RndProjectTask::with(['project', 'taskType', 'documents.file', 'auditor'])
            ->where(function ($q) use ($userId) {
                $q->where('executor_id', $userId)->orWhere('auditor_id', $userId);
            })
            ->orderByRaw("FIELD(status, 'overdue', 'rejected', 'pending', 'submitted', 'approved', 'completed', 'cancelled')")
            ->orderBy('end_date')
            ->paginate(20);

        return view('rnd.my-tasks', compact('tasks'));
    }

    /**
     * 交付物上传台：只列"待我交付"的任务（未提交 / 被驳回 / 逾期）。
     */
    public function documents()
    {
        $userId = auth()->id();

        $tasks = RndProjectTask::with(['project', 'taskType', 'documents.file'])
            ->where('executor_id', $userId)
            ->whereIn('status', ['pending', 'overdue', 'rejected'])
            ->orderByRaw("FIELD(status, 'overdue', 'rejected', 'pending')")
            ->orderBy('end_date')
            ->paginate(20);

        return view('rnd.documents', compact('tasks'));
    }

    /**
     * 交付物审核台：全部待审文档（审核权 rnd.approve 持有者才可进）。
     * 用 withoutGlobalScopes 越过"本人=上传人"的行级范围，否则审核人看不到别人的交付物。
     */
    public function documentReview()
    {
        $documents = RndProjectDocument::withoutGlobalScopes()
            ->with(['project', 'task.taskType', 'task.executor', 'file', 'uploader'])
            ->where('audit_status', 'pending')
            ->orderBy('created_at')
            ->paginate(20);

        return view('rnd.document-review', compact('documents'));
    }

    /* ---------- 任务类型字典管理 ---------- */
    public function taskTypes()
    {
        $types = RndTaskType::orderBy('sort_order')->paginate(50);

        return view('rnd.task-types', compact('types'));
    }

    public function taskTypeStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:rnd_task_types,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
        ]);
        $data['sort_order'] = (RndTaskType::max('sort_order') ?? 0) + 1;
        $data['is_required'] = $request->boolean('is_required');
        RndTaskType::create($data);

        return back()->with('success', '任务类型已添加。');
    }

    /* ---------- 延期申请 ---------- */
    public function extensionStore(Request $request, RndProjectTask $task)
    {
        $data = $request->validate([
            'apply_days' => ['required', 'integer', 'min:1', 'max:90'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $application = RndExtensionApplication::create([
            'task_id' => $task->id,
            'applicant_id' => auth()->id(),
            'apply_days' => $data['apply_days'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        // P1-01：延期申请挂审批（研发经理单审），通过后由 applyRndExtension 回写任务
        $instance = app(\App\Services\ApprovalService::class)
            ->start('rnd.extension', $application->id);

        return back()->with('success', $instance
            ? "延期 {$data['apply_days']} 天申请已提交，等待研发经理审批（审批通过后自动顺延）。"
            : "延期 {$data['apply_days']} 天申请已提交，等待审批。");
    }
}
