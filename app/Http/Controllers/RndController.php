<?php

namespace App\Http\Controllers;

use App\Models\Office\ChatClearState;
use App\Models\Office\ChatMessage;
use App\Models\Office\ChatRoom;
use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndCollabDocument;
use App\Models\Rnd\RndExtensionApplication;
use App\Models\Rnd\RndMeeting;
use App\Models\Rnd\RndMeetingMember;
use App\Models\Rnd\RndMeetingSignal;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectLog;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MediaFile;
use App\Services\CollabOfficeService;
use App\Services\FileService;
use App\Services\RndProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RndController extends Controller
{
    public function __construct(protected RndProjectService $service, protected FileService $files)
    {
    }

    /**
     * 研发项目列表：页签按四态切换（全部 / 立项中 / 进行中 / 已暂停 / 已完成）。
     * 研发经理与系统管理员可看全量；其余人只看到自己参入的项目（创建 / 执行 / 审核）。
     */
    public function projects(Request $request)
    {
        $user = auth()->user();
        $status = (string) $request->query('status', '');
        $isManager = $this->isProjectManager($user);

        $base = function () use ($isManager) {
            return $isManager
                ? RndProject::withoutGlobalScope('data_scope')
                : RndProject::mine();
        };

        $projects = $base()->byStatus($status)
            ->with(['creator', 'tasks.executor', 'tasks.auditor'])
            ->orderByRaw("FIELD(status, 'in_progress', 'initiation', 'paused', 'completed')")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 页签计数：同样只统计自己参入的项目（研发经理统计全量）
        $countScope = $base();
        $counts = [
            'all' => (clone $countScope)->count(),
            'initiation' => (clone $countScope)->byStatus('initiation')->count(),
            'in_progress' => (clone $countScope)->byStatus('in_progress')->count(),
            'paused' => (clone $countScope)->byStatus('paused')->count(),
            'completed' => (clone $countScope)->byStatus('completed')->count(),
        ];

        // 参与人视角的到期提醒：未完成且计划结束日已过
        $overdueCount = (clone $countScope)
            ->where('status', '<>', 'completed')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->count();

        return view('rnd.projects', compact('projects', 'status', 'counts', 'overdueCount', 'isManager'));
    }

    /**
     * 项目状态切换（四态）：创建人 / 研发经理 / 系统管理员可操作。
     * 已完成的不能再回到"立项中"；从任何状态都可暂停或恢复。
     */
    public function projectStatus(Request $request, RndProject $project)
    {
        $this->authorize('manage', $project);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(RndProject::STATUSES))],
        ], [
            'status.in' => '项目状态只能是：' . implode('、', RndProject::STATUSES),
        ]);

        $from = $project->status;
        $project->update(['status' => $data['status']]);

        RndProjectLog::record(
            $project->id,
            '状态变更',
            '项目状态由「' . (RndProject::STATUSES[$from] ?? $from) . '」改为「' . RndProject::STATUSES[$data['status']] . '」',
            'status'
        );

        return back()->with('success', "项目状态已由「" . (RndProject::STATUSES[$from] ?? $from) . "」改为「" . RndProject::STATUSES[$data['status']] . "」。");
    }

    /**
     * 甘特图：按任务的实际起止日期排布，部门间串行可见先后，今日线便于判断是否超期。
     */
    public function gantt(RndProject $project)
    {
        $this->authorize('view', $project);

        $project->load(['tasks.taskType', 'tasks.executor', 'tasks.auditor', 'tasks.documents']);

        $tasks = $project->tasks->sortBy('start_date')->values();

        $min = $tasks->filter(fn ($t) => $t->start_date)->min('start_date')
            ?? $project->start_date
            ?? now();
        $max = $tasks->filter(fn ($t) => $t->end_date)->max('end_date')
            ?? $project->end_date
            ?? now();

        $rangeStart = \Carbon\Carbon::parse($min)->startOfDay();
        $rangeEnd = \Carbon\Carbon::parse($max)->startOfDay();
        // 两端各留 2 天余量，避免首末条贴边
        $rangeStart->subDays(2);
        $rangeEnd->addDays(2);

        $totalDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        $todayOffset = null;
        if (now()->between($rangeStart, $rangeEnd)) {
            $todayOffset = (int) $rangeStart->diffInDays(now()->startOfDay());
        }

        // 月份刻度（按月分列，宽度按天数占比）
        $months = [];
        $cursor = $rangeStart->copy()->startOfMonth();
        while ($cursor->lte($rangeEnd)) {
            $monthStart = $cursor->copy()->max($rangeStart);
            $monthEnd = $cursor->copy()->endOfMonth()->min($rangeEnd);
            $days = (int) $monthStart->diffInDays($monthEnd) + 1;
            $months[] = [
                'label' => $cursor->format('Y年n月'),
                'width' => round($days / $totalDays * 100, 4),
                'days' => $days,
            ];
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        // 按部门分组：任务自身带 department_id，按部门顺序稳定输出；无部门任务归入"未分配部门"
        $deptIds = $tasks->pluck('department_id')->filter()->unique()->values();
        $deptMap = Department::whereIn('id', $deptIds)->orderBy('sort_order')->orderBy('id')->get();
        $departments = $deptMap->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->dept_name])->values();
        if ($tasks->contains(fn ($t) => ! $t->department_id)) {
            $departments->push(['id' => 0, 'name' => '未分配部门']);
        }

        return view('rnd.gantt', compact('project', 'tasks', 'rangeStart', 'rangeEnd', 'totalDays', 'todayOffset', 'months', 'departments'));
    }

    /**
     * 项目履历：时间线形式展示项目全生命周期动作。
     */
    public function history(RndProject $project)
    {
        $this->authorize('view', $project);

        $logs = RndProjectLog::with(['user', 'task'])
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->paginate(30);

        return view('rnd.history', compact('project', 'logs'));
    }

    /**
     * 创建研发项目页：与参考项目 project/create 对齐，加载任务类型、模板、参与部门与部门人员。
     */
    public function projectCreate()
    {
        // 任务类型字典中该部门的所有启用项都应可选：必选项默认勾选，可选项按需勾选
        $taskTypes = RndTaskType::with('department')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get();

        $departments = $taskTypes->pluck('department')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->values();

        // 任务类型尚未维护部门时，至少让页面可操作：默认归到第一个启用部门
        if ($departments->isEmpty()) {
            $departments = Department::where('status', 1)->orderBy('sort_order')->get();
        }
        $fallbackDeptId = optional($departments->first())->id;
        $taskTypes->each(function ($type) use ($fallbackDeptId) {
            if (! $type->department_id) {
                $type->department_id = $fallbackDeptId;
            }
        });

        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'department_id']);
        $usersByDept = $users->groupBy('department_id')
            ->map(fn ($group) => $group->map(fn ($u) => ['id' => (int) $u->id, 'label' => $u->identity_label])->values()->all())
            ->all();

        // 审核人只能是各部门经理：按 departments.manager_id / 岗位数据范围识别
        $auditorsByDept = [];
        $defaultExecutors = [];
        $defaultAuditors = [];
        foreach ($departments as $dept) {
            $first = $users->firstWhere('department_id', $dept->id);
            if ($first) {
                $defaultExecutors[$dept->id] = (int) $first->id;
            }

            $manager = $dept->resolvedManager();
            if ($manager) {
                $auditorsByDept[$dept->id] = [['id' => (int) $manager->id, 'label' => $manager->identity_label]];
                $defaultAuditors[$dept->id] = (int) $manager->id;
            }
        }

        $templates = ProjectTemplate::orderBy('name')->get();

        return view('rnd.project-create', compact(
            'taskTypes', 'templates', 'departments', 'usersByDept', 'auditorsByDept', 'defaultExecutors', 'defaultAuditors'
        ));
    }

    /**
     * 创建研发项目：按参考项目口径按部门串行排期，并一次性生成任务。
     * 只有“选中部门 + 启用”的任务才会落库，且必须指定执行人与审核人。
     */
    public function projectStore(Request $request)
    {
        $data = $request->validate([
            'project_name' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:64', 'unique:rnd_projects,project_code'],
            'version' => ['nullable', 'string', 'max:20'],
            'product_name' => ['nullable', 'string', 'max:128'],
            'product_model' => ['nullable', 'string', 'max:64'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'selected_depts' => ['required', 'array', 'min:1'],
            'selected_depts.*' => ['integer', 'exists:departments,id'],
            'dept_days' => ['required', 'array'],
            'dept_days.*' => ['integer', 'min:1', 'max:365'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_type_id' => ['required', 'integer', 'exists:rnd_task_types,id'],
            'tasks.*.name' => ['nullable', 'string', 'max:128'],
            'tasks.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'tasks.*.enabled' => ['nullable'],
            'tasks.*.executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'tasks.*.auditor_id' => ['nullable', 'integer', 'exists:users,id'],
            'tasks.*.start_date' => ['nullable', 'date'],
            'tasks.*.end_date' => ['nullable', 'date'],
        ], [
            'project_name.required' => '请输入项目名称',
            'start_date.required' => '请选择开始日期',
            'start_date.after_or_equal' => '开始日期不能早于今天',
            'selected_depts.required' => '请至少选择一个部门',
            'dept_days.*.min' => '部门天数不能少于 1 天',
            'dept_days.*.max' => '部门天数不能超过 365 天',
            'tasks.required' => '请至少配置一个任务',
        ]);

        $selectedDepts = array_map('intval', $data['selected_depts']);
        $tasksByDept = [];
        $errors = [];
        $managerCache = [];

        foreach ($data['tasks'] as $index => $task) {
            $deptId = (int) $task['department_id'];
            $enabled = ! empty($task['enabled']) && $task['enabled'] !== '0';

            // 未选中部门 / 未启用的任务不参与建项目
            if (! in_array($deptId, $selectedDepts, true) || ! $enabled) {
                continue;
            }
            $label = '任务「' . (($task['name'] ?? '') ?: '未命名') . '」';

            if (empty($task['executor_id'])) {
                $errors["tasks.{$index}.executor_id"] = $label . '需要选择执行人';
            }
            if (empty($task['auditor_id'])) {
                $errors["tasks.{$index}.auditor_id"] = $label . '需要选择审核人';
            }

            // 审核人必须是任务所属部门的部门经理（参考项目 task_auditor = 原主管岗位）
            $manager = $managerCache[$deptId] ??= Department::find($deptId)?->resolvedManager();
            if (! $manager) {
                $errors["tasks.{$index}.auditor_id"] = $label . '所属部门未配置部门经理，无法指定审核人';
            } elseif (! empty($task['auditor_id']) && (int) $task['auditor_id'] !== (int) $manager->id) {
                $deptName = Department::find($deptId)?->dept_name ?? ('部门#' . $deptId);
                $errors["tasks.{$index}.auditor_id"] = $label . '的审核人必须是「' . $deptName . '」部门经理（' . $manager->identity_label . '）';
            }

            if (! empty($task['executor_id']) && ! empty($task['auditor_id'])
                && (int) $task['executor_id'] === (int) $task['auditor_id']) {
                $errors["tasks.{$index}.auditor_id"] = $label . '的审核人不能是执行人本人';
            }

            $tasksByDept[$deptId][] = $task;
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        if (empty($tasksByDept)) {
            return back()->withErrors(['tasks' => '请至少启用一个属于已选部门的任务'])->withInput();
        }

        try {
            $project = $this->service->createProject($data, $tasksByDept, $selectedDepts, $data['dept_days']);
        } catch (\DomainException $e) {
            return back()->withErrors(['tasks' => $e->getMessage()])->withInput();
        }

        return redirect()->route('rnd.projects.show', $project)->with('success', "项目 {$project->project_code} 已创建。");
    }

    public function projectShow(RndProject $project)
    {
        // P0-05：非参与者不得查看（创建人 / 任务执行人·审核人 / 研发经理）
        $this->authorize('view', $project);

        $project->load(['creator', 'tasks.taskType', 'tasks.executor', 'documents.file', 'documents.uploader']);
        $taskTypes = RndTaskType::with('department')->where('status', 1)->orderBy('sort_order')->get();

        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'department_id']);
        $usersByDept = $users->groupBy('department_id')
            ->map(fn ($group) => $group->map(fn ($u) => ['id' => (int) $u->id, 'label' => $u->identity_label])->values()->all())
            ->all();

        // 审核人只能是各部门经理（与创建页同口径）
        $auditorsByDept = [];
        $defaultExecutors = [];
        $defaultAuditors = [];
        $taskDeptIds = $taskTypes->pluck('department_id')->filter()->unique()->all();
        foreach (Department::whereIn('id', $taskDeptIds)->orderBy('sort_order')->get() as $dept) {
            $first = $users->firstWhere('department_id', $dept->id);
            if ($first) {
                $defaultExecutors[$dept->id] = (int) $first->id;
            }

            $manager = $dept->resolvedManager();
            if ($manager) {
                $auditorsByDept[$dept->id] = [['id' => (int) $manager->id, 'label' => $manager->identity_label]];
                $defaultAuditors[$dept->id] = (int) $manager->id;
            }
        }

        // P1-05：基线图档 + 变更单 + 可选 BOM
        $baseline = $project->documents->firstWhere('is_baseline', true);
        $ecrs = \App\Models\Rnd\EcrChangeOrder::withoutGlobalScopes()
            ->with(['applicant', 'bom', 'newBom'])
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $boms = \App\Models\Bom\BomHeader::orderByDesc('id')->limit(50)->get();

        return view('rnd.project-show', compact(
            'project', 'taskTypes', 'usersByDept', 'auditorsByDept', 'defaultExecutors', 'defaultAuditors', 'baseline', 'ecrs', 'boms'
        ));
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
            'change_type' => ['required', 'in:bom,material,process,document'],
            'priority' => ['nullable', 'in:low,normal,high'],
            'effective_date' => ['nullable', 'date', 'after_or_equal:today'],
            'title' => ['required', 'string', 'max:128'],
            'scope_description' => ['nullable', 'string', 'max:500'],
            'impacted_material_codes' => ['nullable', 'string', 'max:1000'],
            'replacement_material_codes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $ids = app(\App\Support\EcrImpactService::class)
                ->resolveMaterialCodes((string) ($data['impacted_material_codes'] ?? ''));
            $replacementIds = app(\App\Support\EcrImpactService::class)
                ->resolveMaterialCodes((string) ($data['replacement_material_codes'] ?? ''));
        } catch (\DomainException $e) {
            return back()->withErrors(['impacted_material_codes' => $e->getMessage()])->withInput();
        }

        if ($replacementIds && count($replacementIds) !== count($ids)) {
            return back()->withErrors(['replacement_material_codes' => '替代物料数量必须与受影响物料一一对应（' . count($ids) . ' 项）。'])->withInput();
        }

        $data['project_id'] = $project->id;
        $data['impacted_material_ids'] = $ids;
        $data['replacement_material_ids'] = $replacementIds;
        unset($data['impacted_material_codes'], $data['replacement_material_codes']);

        $ecr = $this->service->createEcr($data);

        return back()->with('success', "变更单 {$ecr->change_no} 已提交（影响等级：" . ($ecr->impact_analysis['risk_level'] ?? 'low') . "），等待研发经理审批；批准后生成 ECO 与新 BOM 版本。");
    }

    /** P3-1：提交前预览影响分析（JSON） */
    public function ecrImpact(Request $request, RndProject $project)
    {
        $this->authorize('manage', $project);
        $data = $request->validate(['bom_id' => ['required', 'exists:bom_headers,id']]);

        $impact = app(\App\Support\EcrImpactService::class)->analyze((int) $data['bom_id']);

        return response()->json($impact);
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
            'name' => ['nullable', 'string', 'max:128'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'executor_id' => ['required', 'exists:users,id'],
            'auditor_id' => ['required', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['required', 'date'],
        ], [
            'auditor_id.required' => '请选择部门经理担任审核人',
        ]);

        $type = RndTaskType::find($data['task_type_id']);
        $deptId = (int) ($data['department_id'] ?? $type?->department_id ?? 0);
        $dept = $deptId ? Department::find($deptId) : null;
        $manager = $dept?->resolvedManager();

        if (! $manager) {
            return back()->withErrors(['auditor_id' => '任务所属部门未配置部门经理，无法指定审核人']);
        }
        if ((int) $data['auditor_id'] !== (int) $manager->id) {
            return back()->withErrors([
                'auditor_id' => '审核人必须是「' . ($dept->dept_name ?? '任务所属部门') . '」部门经理（' . $manager->identity_label . '）',
            ])->withInput();
        }
        if ((int) $data['executor_id'] === (int) $data['auditor_id']) {
            return back()->withErrors(['auditor_id' => '审核人不能是执行人本人'])->withInput();
        }

        $data['department_id'] = $deptId;

        try {
            $this->service->addTask($project->id, $data);
        } catch (\DomainException $e) {
            return back()->withErrors(['auditor_id' => $e->getMessage()])->withInput();
        }

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
     * 任务详情（对齐参考目录 task/show）：任务信息 + 任务操作 + 文件列表 + 延期申请记录。
     * 任务状态保留 overdue，不受项目状态四态化影响。
     */
    public function taskShow(int $task)
    {
        $taskModel = RndProjectTask::withoutGlobalScopes()->with(['taskType', 'executor', 'auditor'])->findOrFail($task);
        $this->authorize('view', $taskModel);

        $project = RndProject::withoutGlobalScopes()->with('creator')->find($taskModel->project_id);

        $documents = RndProjectDocument::withoutGlobalScopes()
            ->with(['file', 'uploader'])
            ->where('task_id', $taskModel->id)
            ->orderByDesc('version_no')
            ->get();

        $extensions = RndExtensionApplication::with(['applicant', 'approver'])
            ->where('task_id', $taskModel->id)
            ->orderByDesc('id')
            ->get();

        $canExecute = auth()->user()->can('execute', $taskModel);
        $canDecide = auth()->user()->can('decide', $taskModel);
        $canUpload = auth()->user()->can('upload', $taskModel);

        return view('rnd.task-show', [
            'task' => $taskModel,
            'project' => $project,
            'documents' => $documents,
            'extensions' => $extensions,
            'canExecute' => $canExecute,
            'canDecide' => $canDecide,
            'canUpload' => $canUpload,
        ]);
    }

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
     * 交付物审核台：只列“我是该任务审核人”的待审文档。
     * 只有部门经理会被指派为任务审核人，因此这里天然只显示各部门经理自己的待审任务。
     */
    public function documentReview()
    {
        $userId = (int) auth()->id();

        $documents = RndProjectDocument::withoutGlobalScopes()
            ->with(['project', 'task.taskType', 'task.executor', 'file', 'uploader'])
            ->where('audit_status', 'pending')
            ->whereHas('task', fn ($q) => $q->where('auditor_id', $userId))
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

    /* ---------- 延期申请 ---------- */
    public function extensionStore(Request $request, RndProjectTask $task)
    {
        // 只有该任务执行人（或项目创建人/研发经理）能申请延期
        $this->authorize('execute', $task);

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

    /** 项目监督人（研发经理）与系统管理员可见全部项目；其余按“创建/执行/审核”口径 */
    /* ---------- P4 项目组内聊天室（对齐参考目录 messages/index：项目群聊 + 附件 + 轮询） ---------- */

    /** 项目群聊房间（一个项目一个房间，按 room_type=project + related_id 复用） */
    private function chatRoomFor(RndProject $project): ChatRoom
    {
        return ChatRoom::firstOrCreate(
            ['room_type' => 'project', 'related_id' => $project->id],
            ['room_name' => '项目群：' . $project->project_name, 'creator_id' => auth()->id()]
        );
    }

    /**
     * 一对一私聊房间：项目内按两个用户 ID 生成稳定键，复用同一套消息/附件/清空游标。
     * 不修改现有 messages 表，也不扩大现有群聊表语义。
     */
    private function privateRoomFor(RndProject $project, int $targetUserId): ChatRoom
    {
        $pair = implode(':', [min((int) auth()->id(), $targetUserId), max((int) auth()->id(), $targetUserId)]);

        return ChatRoom::firstOrCreate(
            ['room_type' => 'rnd_private', 'related_id' => $project->id, 'room_name' => '私聊:' . $pair],
            ['creator_id' => auth()->id()]
        );
    }

    /** 项目成员：创建人 + 任务执行人 + 任务审核人（与项目页参与人口径一致） */
    private function chatMembers(RndProject $project): array
    {
        cache()->put('rnd_chat_online:' . auth()->id(), true, 300);

        $ids = $project->participantIds();

        return User::whereIn('id', $ids)
            ->with(['department', 'position'])
            ->get()
            ->map(function (User $u) {
                $isMe = (int) $u->id === (int) auth()->id();
                $online = $isMe
                    || cache()->has('rnd_chat_online:' . $u->id)
                    || ($u->last_login_at && $u->last_login_at->gt(now()->subMinutes(5)));

                return [
                    'id' => (int) $u->id,
                    'name' => $u->name ?: $u->username,
                    'initial' => mb_substr($u->name ?: $u->username, 0, 1),
                    'avatar_url' => $u->avatar_url,
                    'department' => $u->department->dept_name ?? '',
                    'is_online' => $online,
                    'is_me' => $isMe,
                ];
            })
            ->sortBy(fn ($m) => [$m['is_me'] ? 1 : 0, $m['is_online'] ? 0 : 1, $m['name']])
            ->values()
            ->all();
    }

    private function chatPayload(ChatMessage $m): array
    {
        $file = $m->file_id ? MediaFile::find($m->file_id) : null;
        $name = $m->sender->name ?? ($m->sender->username ?? '—');
        $isAdmin = auth()->user()?->hasRole('role_sys_admin') ?? false;

        return [
            'id' => (int) $m->id,
            'sender' => $name,
            'initial' => mb_substr($name, 0, 1),
            'avatar_url' => $m->sender->avatar_url ?? null,
            'mine' => (int) $m->sender_id === (int) auth()->id(),
            'type' => $m->msg_type,
            'content' => (string) $m->content,
            'file_url' => $file ? route('files.download', $file->id) : null,
            'file_preview_url' => $file && $file->canPreview() ? route('files.preview', $file->id) : null,
            'file_name' => $file->file_name ?? null,
            'time' => $m->created_at?->format('H:i'),
            'full_time' => $m->created_at?->format('Y-m-d H:i:s'),
            'can_delete' => $isAdmin,
        ];
    }

    private function chatMessages(ChatRoom $room, int $limit = 80)
    {
        $clear = ChatClearState::where('user_id', auth()->id())
            ->where('room_id', $room->id)
            ->value('cleared_at');

        return ChatMessage::with('sender')
            ->where('room_id', $room->id)
            ->when($clear, fn ($q) => $q->where('created_at', '>', $clear))
            ->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    public function chat(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $members = $this->chatMembers($model);
        $withId = (int) $request->query('with', 0);
        if ($withId && ! collect($members)->contains(fn ($m) => $m['id'] === $withId)) {
            abort(404, '该成员不在本项目内');
        }

        $room = $withId ? $this->privateRoomFor($model, $withId) : $this->chatRoomFor($model);
        $messages = $this->chatMessages($room);
        $target = $withId ? collect($members)->firstWhere('id', $withId) : null;

        return view('rnd.chat', [
            'project' => $model,
            'room' => $room,
            'members' => $members,
            'messages' => $messages,
            'payload' => $messages->map(fn ($m) => $this->chatPayload($m))->values(),
            'target' => $target,
            'withId' => $withId,
            'emojis' => $this->chatEmojis(),
        ]);
    }

    public function chatSend(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        // 只有项目参与人（创建人 / 执行人 / 审核人 / 研发经理）能进项目群
        $this->authorize('view', $model);

        $request->validate([
            'content' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:20480'],
            'receiver_id' => ['nullable', 'integer'],
        ]);

        $content = trim((string) $request->input('content', ''));
        if ($content === '' && ! $request->hasFile('file')) {
            return back()->withErrors(['content' => '消息内容不能为空。']);
        }

        $receiverId = (int) $request->input('receiver_id', 0);
        if ($receiverId && ! in_array($receiverId, $model->participantIds(), true)) {
            abort(404, '该成员不在本项目内');
        }

        $room = $receiverId ? $this->privateRoomFor($model, $receiverId) : $this->chatRoomFor($model);
        $fileId = null;
        $type = 'text';
        if ($request->hasFile('file')) {
            $fileId = $this->files->upload($request->file('file'), 'chat_file', 'chat_room', $room->id)->id;
            $type = 'file';
        }

        ChatMessage::create([
            'room_id' => $room->id,
            'sender_id' => auth()->id(),
            'msg_type' => $type,
            'content' => $content,
            'file_id' => $fileId,
            'created_at' => now(),
        ]);

        $params = ['project' => $model->id];
        if ($receiverId) {
            $params['with'] = $receiverId;
        }

        return redirect()->route('rnd.projects.chat', $params);
    }

    /** 增量拉取（JSON 轮询）：群聊与私聊共用，断网内网环境可用 */
    public function chatFeed(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $withId = (int) $request->query('with', 0);
        if ($withId && ! in_array($withId, $model->participantIds(), true)) {
            abort(404, '该成员不在本项目内');
        }

        return response()->json([
            'messages' => $this->chatMessages(
                $withId ? $this->privateRoomFor($model, $withId) : $this->chatRoomFor($model)
            )->map(fn ($m) => $this->chatPayload($m))->values(),
        ]);
    }

    /** 清空我的留言板：仅写当前用户的显示游标，不删除任何真实消息 */
    public function chatClear(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $withId = (int) $request->input('with', 0);
        if ($withId && ! in_array($withId, $model->participantIds(), true)) {
            abort(404, '该成员不在本项目内');
        }

        $room = $withId ? $this->privateRoomFor($model, $withId) : $this->chatRoomFor($model);
        ChatClearState::updateOrCreate(
            ['user_id' => auth()->id(), 'room_id' => $room->id],
            ['cleared_at' => now()]
        );

        $params = ['project' => $model->id];
        if ($withId) {
            $params['with'] = $withId;
        }

        return redirect()->route('rnd.projects.chat', $params);
    }

    /** 管理员删除单条消息：软删除，保留附件与追溯记录 */
    public function chatDestroy(int $project, int $message)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        abort_unless(auth()->user()?->hasRole('role_sys_admin'), 403, '只有管理员可以删除留言');

        $msg = ChatMessage::findOrFail($message);
        $room = ChatRoom::findOrFail($msg->room_id);
        abort_unless(
            (int) $room->related_id === (int) $model->id
            && in_array($room->room_type, ['project', 'rnd_private'], true),
            404
        );

        $msg->delete();

        return back();
    }

    private function chatEmojis(): array
    {
        return [
            '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😊',
            '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '🥲', '😋',
            '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐',
            '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌',
            '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🥵',
            '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐',
            '😕', '😟', '🙁', '☹️', '😮‍💨', '😥', '😢', '😭', '😱', '😖',
            '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬',
            '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽',
            '👾', '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀',
            '😿', '😾', '💋', '👄', '👍', '👎', '👌', '✌️', '🤞', '🤟',
            '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '👋', '🤚', '🖐️',
            '✋', '🖖', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '💪', '🦾',
        ];
    }

    /* ---------- P5-1 项目组内协同办公（文档正文落库，断网可用） ---------- */

    public function collab(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        if ($request->filled('doc')) {
            return redirect()->route('rnd.projects.collab.open', [
                'project' => $model->id,
                'doc' => (int) $request->query('doc'),
            ]);
        }

        $docs = RndCollabDocument::with(['creator', 'updater'])
            ->where('project_id', $model->id)
            ->orderByDesc('updated_at')
            ->get();

        return view('rnd.collab', [
            'project' => $model,
            'docs' => $docs,
            'docTypes' => RndCollabDocument::TYPES,
        ]);
    }

    public function collabCreate(int $project, CollabOfficeService $office)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $office->ensureTemplates();

        return view('rnd.collab-create', [
            'project' => $model,
            'docTypes' => RndCollabDocument::TYPES,
        ]);
    }

    public function collabStore(Request $request, int $project, CollabOfficeService $office)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $mode = $request->input('mode', 'template');
        $office->ensureTemplates();

        if ($mode === 'upload') {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:200'],
                'file' => ['required', 'file', 'max:20480'],
            ], [
                'title.required' => '请输入文档名称。',
                'file.required' => '请选择要上传的文档。',
                'file.max' => '文档不能超过 20MB。',
            ]);

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
                throw ValidationException::withMessages(['file' => '仅支持 docx / xlsx / pptx 格式。']);
            }

            $type = ['docx' => 'doc', 'xlsx' => 'sheet', 'pptx' => 'slide'][$ext];
            $doc = RndCollabDocument::create([
                'project_id' => $model->id,
                'title' => $data['title'],
                'doc_type' => $type,
                'content' => '',
                'version' => 1,
                'creator_id' => auth()->id(),
                'updater_id' => auth()->id(),
            ]);

            $office->storeUploadedFile($doc, $file->getRealPath(), $ext);
        } else {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:200'],
                'doc_type' => ['required', 'in:doc,sheet,slide'],
            ], [
                'title.required' => '请输入文档名称。',
                'doc_type.required' => '请选择文档类型。',
            ]);

            $doc = RndCollabDocument::create([
                'project_id' => $model->id,
                'title' => $data['title'],
                'doc_type' => $data['doc_type'],
                'content' => '',
                'version' => 1,
                'creator_id' => auth()->id(),
                'updater_id' => auth()->id(),
            ]);

            $office->createNewFile($doc);
        }

        RndProjectLog::record(
            $model->id,
            '新建协同文档',
            '《' . $doc->title . '》(' . $doc->typeLabel() . ')',
            'document'
        );

        return redirect()->route('rnd.projects.collab.open', [
            'project' => $model->id,
            'doc' => $doc->id,
        ])->with('success', '协同文档已创建，正在加载 OnlyOffice 编辑器。');
    }

    /** 打开 OnlyOffice 协同编辑器 */
    public function collabOpen(int $project, int $doc, CollabOfficeService $office)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $d = RndCollabDocument::where('project_id', $model->id)->findOrFail($doc);
        $office->ensureRepositoryFile($d);

        // key 保持稳定，多人同时打开才会进入同一个 OnlyOffice 协同会话；
        // 文件内容变化由保存回调同步，不在这里换 key。
        $config = $office->buildEditorConfig($d, auth()->user(), true);

        return view('rnd.collab-office', [
            'project' => $model,
            'doc' => $d,
            'config' => $config,
            'officeServer' => $office->serverUrl(),
            'officeEnabled' => $office->enabled(),
        ]);
    }

    /** 系统内下载（登录态） */
    public function collabDownload(int $doc)
    {
        $d = RndCollabDocument::findOrFail($doc);
        $project = RndProject::withoutGlobalScopes()->findOrFail($d->project_id);
        $this->authorize('view', $project);

        if ($d->hasRepositoryFile()) {
            return Storage::disk('file_repository')->download($d->storage_path, $d->downloadName());
        }

        return response()->streamDownload(function () use ($d) {
            echo $d->content;
        }, $d->downloadName(), ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** DocumentServer 读取源文件（无登录态，doc_key 校验） */
    public function collabOfficeDownload(Request $request, int $doc)
    {
        $d = RndCollabDocument::findOrFail($doc);
        abort_unless(hash_equals((string) $d->doc_key, (string) $request->query('k')), 404);

        abort_unless($d->hasRepositoryFile(), 404);

        $mime = match ($d->fileExtension()) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };

        return response()->file(Storage::disk('file_repository')->path($d->storage_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . rawurlencode($d->downloadName()) . '"',
        ]);
    }

    /** DocumentServer 保存回调（无登录态，doc_key + JWT 校验） */
    public function collabOfficeCallback(Request $request, int $doc, CollabOfficeService $office)
    {
        $d = RndCollabDocument::findOrFail($doc);
        abort_unless(hash_equals((string) $d->doc_key, (string) $request->query('k')), 404);

        $payload = $request->json()->all();
        if (! empty($payload['key']) && ! hash_equals((string) $d->doc_key, (string) $payload['key'])) {
            abort(403);
        }
        if ($office->jwtSecret() !== '') {
            abort_unless($office->verifyJwt((string) ($payload['token'] ?? '')), 403);
        }

        $status = (int) ($payload['status'] ?? 0);
        if (in_array($status, [2, 6], true) && ! empty($payload['url'])) {
            $office->saveFromUrl($d, (string) $payload['url']);
        }

        return response()->json(['error' => 0]);
    }

    public function collabSave(Request $request, int $doc)
    {
        $d = RndCollabDocument::findOrFail($doc);
        $project = RndProject::withoutGlobalScopes()->findOrFail($d->project_id);
        $this->authorize('view', $project);

        if ($d->hasRepositoryFile()) {
            throw ValidationException::withMessages([
                'content' => 'Office 文档请在 OnlyOffice 编辑器中保存，系统会自动接收保存回调。',
            ]);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:200000'],
        ]);

        $d->update([
            'title' => $request->input('title'),
            'content' => (string) $request->input('content', ''),
            'version' => (int) $d->version + 1,
            'updater_id' => auth()->id(),
        ]);

        RndProjectLog::record(
            $project->id,
            '保存协同文档',
            '《' . $d->title . '》更新为 v' . $d->version . '，操作人 ' . (auth()->user()->identity_label ?? ''),
            'document'
        );

        return redirect()->route('rnd.projects.collab.open', ['project' => $project->id, 'doc' => $d->id])
            ->with('success', '已保存 v' . $d->version . '（' . (auth()->user()->identity_label ?? '') . '）');
    }

    public function collabDestroy(int $project, int $doc)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $d = RndCollabDocument::where('project_id', $model->id)->findOrFail($doc);
        $user = auth()->user();
        $canDelete = ($user?->hasRole('role_sys_admin') ?? false)
            || (int) $user?->id === (int) $model->creator_id
            || (int) $user?->id === (int) $d->creator_id;

        abort_unless($canDelete, 403, '只有管理员、项目创建人或文档创建人可以删除文档');

        $d->delete();
        RndProjectLog::record($model->id, '删除协同文档', '《' . $d->title . '》移入回收站', 'document');

        return redirect()->route('rnd.projects.collab', $model->id)->with('success', '文档已移入回收站。');
    }

    /* ---------- P5-2 项目组内电话会议（WebRTC + 数据库信令轮询，断网局域网可用） ---------- */

    private function openMeeting(RndProject $project): ?RndMeeting
    {
        return RndMeeting::where('project_id', $project->id)->where('status', 'open')->latest('id')->first();
    }

    public function voice(int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        $members = User::whereIn('id', $model->participantIds())
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'initial' => mb_substr($u->name, 0, 1),
                'avatar_url' => $u->avatar_url,
                'is_host' => (int) $u->id === (int) $model->creator_id,
                'is_me' => (int) $u->id === (int) auth()->id(),
            ])->values();

        $docs = RndCollabDocument::where('project_id', $model->id)->orderByDesc('updated_at')->get();

        return view('rnd.voice', [
            'project' => $model,
            'meeting' => $meeting,
            'members' => $members,
            'docs' => $docs,
            'isHost' => (int) auth()->id() === (int) $model->creator_id,
            'presenter' => $meeting?->current_presenter_id
                ? User::find($meeting->current_presenter_id)
                : ($meeting ? $meeting->creator : null),
        ]);
    }

    public function voiceStart(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            $meeting = RndMeeting::create([
                'project_id' => $model->id,
                'title' => $request->input('title') ?: ('项目电话会议 · ' . $model->project_name),
                'status' => 'open',
                'creator_id' => auth()->id(),
                'current_presenter_id' => auth()->id(),
                'started_at' => now(),
            ]);
        }

        return redirect()->route('rnd.projects.voice', $model->id)->with('success', '会议已开启，可邀请项目成员加入。');
    }

    /** 加入 / 心跳：登记在线状态并返回在线成员 */
    public function voiceJoin(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        RndMeetingMember::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => auth()->id()],
            ['peer_id' => (string) $request->input('peer_id', auth()->id()), 'joined_at' => now(), 'last_seen_at' => now()]
        );

        return response()->json($this->voiceStatePayload($meeting));
    }

    /** 轮询：在线成员 + 发给我的信令（取走即删） */
    public function voiceState(int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        // 心跳
        RndMeetingMember::where('meeting_id', $meeting->id)->where('user_id', auth()->id())
            ->update(['last_seen_at' => now()]);

        return response()->json($this->voiceStatePayload($meeting, ! request()->boolean('peek')));
    }

    private function voiceStatePayload(RndMeeting $meeting, bool $consumeSignals = true): array
    {
        $members = $meeting->onlineMembers()->map(fn ($m) => [
            'user_id' => (int) $m->user_id,
            'name' => $m->user->name ?? ('用户' . $m->user_id),
            'initial' => mb_substr($m->user->name ?? '?', 0, 1),
            'avatar_url' => $m->user->avatar_url ?? null,
            'mine' => (int) $m->user_id === (int) auth()->id(),
        ])->values();

        $signals = RndMeetingSignal::where('meeting_id', $meeting->id)
            ->where('to_user_id', auth()->id())
            ->orderBy('id')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'from' => (int) $s->from_user_id,
                'type' => $s->type,
                'payload' => $s->payload,
            ])->values();

        if ($consumeSignals && $signals->isNotEmpty()) {
            RndMeetingSignal::whereIn('id', $signals->pluck('id'))->delete();
        }

        $presenter = $meeting->current_presenter_id
            ? User::find($meeting->current_presenter_id)
            : $meeting->creator;

        $document = null;
        if ($meeting->current_document_id) {
            $d = RndCollabDocument::where('project_id', $meeting->project_id)->find($meeting->current_document_id);
            if ($d) {
                $document = [
                    'id' => (int) $d->id,
                    'title' => $d->title,
                    'type' => $d->typeLabel(),
                    'version' => (int) $d->version,
                    'download_url' => route('rnd.collab.download', $d),
                ];
            }
        }

        return [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'status' => $meeting->status,
                'host_id' => (int) $meeting->creator_id,
                'is_host' => (int) auth()->id() === (int) $meeting->creator_id,
            ],
            'me' => (int) auth()->id(),
            'members' => $members,
            'signals' => $signals,
            'presenter' => $presenter ? [
                'id' => (int) $presenter->id,
                'name' => $presenter->name,
            ] : null,
            'document' => $document,
            'edit_mode' => (bool) $meeting->presentation_edit_mode,
        ];
    }

    /** 申请演示：给主持人留一条信令，由主持人确认后转移演示权 */
    public function voiceApply(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        if ((int) $meeting->creator_id !== (int) auth()->id()) {
            RndMeetingSignal::create([
                'meeting_id' => $meeting->id,
                'from_user_id' => auth()->id(),
                'to_user_id' => $meeting->creator_id,
                'type' => 'presenter_apply',
                'payload' => json_encode(['name' => auth()->user()->name], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /** 主持人确认/收回演示权，并实时广播给在线成员 */
    public function voicePresenter(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        abort_unless((int) $meeting->creator_id === (int) auth()->id(), 403, '只有主持人可以管理演示权');

        $request->validate(['user_id' => ['nullable', 'integer']]);
        $userId = (int) $request->input('user_id', 0);
        if ($userId && ! in_array($userId, $model->participantIds(), true)) {
            return response()->json(['error' => '该成员不在本项目内'], 404);
        }

        $meeting->update(['current_presenter_id' => $userId ?: null]);
        $this->broadcastMeetingSignal($meeting, 'presenter_changed', [
            'presenter_id' => $userId ?: null,
        ]);

        return response()->json(['ok' => true, 'presenter_id' => $userId ?: null]);
    }

    /** 主持人/演示者选择演示文档 */
    public function voiceDocument(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        $isPresenter = (int) $meeting->current_presenter_id === (int) auth()->id();
        $isHost = (int) $meeting->creator_id === (int) auth()->id();
        abort_unless($isPresenter || $isHost, 403, '只有主持人或演示者可以切换演示文档');

        $request->validate([
            'doc_id' => ['nullable', 'integer'],
            'edit_mode' => ['nullable', 'boolean'],
        ]);

        $docId = (int) $request->input('doc_id', 0);
        if ($docId) {
            $doc = RndCollabDocument::where('project_id', $model->id)->find($docId);
            abort_unless($doc, 404, '文档不存在');
        }

        $meeting->update([
            'current_document_id' => $docId ?: null,
            'presentation_edit_mode' => (bool) $request->boolean('edit_mode'),
        ]);
        $this->broadcastMeetingSignal($meeting, 'doc_selected', [
            'document_id' => $docId ?: null,
            'edit_mode' => (bool) $request->boolean('edit_mode'),
        ]);

        return response()->json(['ok' => true, 'document_id' => $docId ?: null]);
    }

    /** 演示文档正文（只读预览） */
    public function voiceDoc(int $project, int $doc)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $d = RndCollabDocument::where('project_id', $model->id)->findOrFail($doc);

        return response()->json([
            'id' => (int) $d->id,
            'title' => $d->title,
            'type' => $d->typeLabel(),
            'content' => $d->previewText(),
            'download_url' => route('rnd.collab.download', $d),
        ]);
    }

    private function broadcastMeetingSignal(RndMeeting $meeting, string $type, array $payload = []): void
    {
        foreach ($meeting->onlineMembers() as $member) {
            RndMeetingSignal::create([
                'meeting_id' => $meeting->id,
                'from_user_id' => auth()->id(),
                'to_user_id' => $member->user_id,
                'type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }
    }

    public function voiceSignal(Request $request, int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if (! $meeting) {
            return response()->json(['error' => '会议尚未开启'], 409);
        }

        $request->validate([
            'type' => ['required', 'in:offer,answer,candidate,leave'],
            'to' => ['required', 'integer'],
            'payload' => ['nullable', 'string', 'max:20000'],
        ]);

        RndMeetingSignal::create([
            'meeting_id' => $meeting->id,
            'from_user_id' => auth()->id(),
            'to_user_id' => (int) $request->input('to'),
            'type' => $request->input('type'),
            'payload' => (string) $request->input('payload', ''),
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function voiceLeave(int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if ($meeting) {
            RndMeetingMember::where('meeting_id', $meeting->id)->where('user_id', auth()->id())
                ->update(['last_seen_at' => now()->subMinutes(5)]);

            // 广播离场，便于对端立即释放连接
            foreach ($meeting->onlineMembers() as $m) {
                if ((int) $m->user_id !== (int) auth()->id()) {
                    RndMeetingSignal::create([
                        'meeting_id' => $meeting->id,
                        'from_user_id' => auth()->id(),
                        'to_user_id' => $m->user_id,
                        'type' => 'leave',
                        'payload' => '',
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function voiceClose(int $project)
    {
        $model = RndProject::withoutGlobalScopes()->findOrFail($project);
        $this->authorize('view', $model);

        $meeting = $this->openMeeting($model);
        if ($meeting) {
            abort_unless((int) $meeting->creator_id === (int) auth()->id(), 403, '只有主持人可以结束会议');
            $meeting->update(['status' => 'closed', 'ended_at' => now(), 'current_presenter_id' => null]);
        }

        return back()->with('success', '会议已结束。');
    }

    private function isProjectManager(?User $user): bool
    {
        return $user
            && ($user->hasRole('role_rnd_manager') || $user->hasRole('role_sys_admin'));
    }
}
