<?php

namespace App\Http\Controllers;

use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndTaskType;
use App\Services\RndProjectService;
use Illuminate\Http\Request;

/**
 * 项目模板（G11）：与参考项目 project-template 对齐。
 * 模板保存“部门 + 部门天数 + 任务类型 + 执行人/审核人”的完整配置，创建项目时一次生成任务。
 */
class ProjectTemplateController extends Controller
{
    public function __construct(protected RndProjectService $service)
    {
    }

    public function index()
    {
        $templates = ProjectTemplate::query()->with('user')->orderByDesc('id')->get();

        return view('project.templates', compact('templates'));
    }

    /** 创建新研发项目模板页 */
    public function create()
    {
        $taskTypes = RndTaskType::with('department')
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->get();

        $departments = $taskTypes->pluck('department')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->values();

        if ($departments->isEmpty()) {
            $departments = Department::where('status', 1)->orderBy('sort_order')->get();
        }
        $fallbackDeptId = optional($departments->first())->id;
        $taskTypes->each(function ($type) use ($fallbackDeptId) {
            if (! $type->department_id) {
                $type->department_id = $fallbackDeptId;
            }
        });

        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name', 'department_id']);
        $usersByDept = $users->groupBy('department_id')
            ->map(fn ($group) => $group->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values()->all())
            ->all();

        $defaultExecutors = [];
        $defaultAuditors = [];
        foreach ($departments as $dept) {
            $first = $users->firstWhere('department_id', $dept->id);
            if ($first) {
                $defaultExecutors[$dept->id] = (int) $first->id;
                $defaultAuditors[$dept->id] = (int) $first->id;
            }
        }

        return view('project.template-create', compact(
            'taskTypes', 'departments', 'usersByDept', 'defaultExecutors', 'defaultAuditors'
        ));
    }

    public function store(Request $request)
    {
        $base = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            // 新格式
            'selected_depts' => ['nullable', 'array'],
            'selected_depts.*' => ['integer', 'exists:departments,id'],
            'dept_days' => ['nullable', 'array'],
            'dept_days.*' => ['integer', 'min:1', 'max:365'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.task_type_id' => ['required_with:tasks', 'integer', 'exists:rnd_task_types,id'],
            'tasks.*.name' => ['nullable', 'string', 'max:128'],
            'tasks.*.department_id' => ['required_with:tasks', 'integer', 'exists:departments,id'],
            'tasks.*.enabled' => ['nullable'],
            'tasks.*.executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'tasks.*.auditor_id' => ['nullable', 'integer', 'exists:users,id'],
            // 旧格式兼容
            'task_type_ids' => ['nullable', 'array'],
            'task_type_ids.*' => ['integer', 'exists:rnd_task_types,id'],
        ], [
            'name.required' => '请输入模板名称',
        ]);

        $selectedDepts = array_map('intval', $base['selected_depts'] ?? []);
        $deptDays = $base['dept_days'] ?? [];
        $steps = [];
        $errors = [];

        if (! empty($base['tasks'])) {
            foreach ($base['tasks'] as $index => $task) {
                $deptId = (int) $task['department_id'];
                $enabled = ! empty($task['enabled']) && $task['enabled'] !== '0';
                if (! in_array($deptId, $selectedDepts, true) || ! $enabled) {
                    continue;
                }
                if (empty($task['executor_id'])) {
                    $errors["tasks.{$index}.executor_id"] = '任务「' . (($task['name'] ?? '') ?: '未命名') . '」需要选择执行人';
                }
                if (empty($task['auditor_id'])) {
                    $errors["tasks.{$index}.auditor_id"] = '任务「' . (($task['name'] ?? '') ?: '未命名') . '」需要选择审核人';
                }

                $steps[] = [
                    'task_type_id' => (int) $task['task_type_id'],
                    'name' => (string) ($task['name'] ?? ''),
                    'department_id' => $deptId,
                    'executor_id' => $task['executor_id'] ? (int) $task['executor_id'] : null,
                    'auditor_id' => $task['auditor_id'] ? (int) $task['auditor_id'] : null,
                    'days' => (int) ($deptDays[$deptId] ?? 5),
                    'sort_order' => (int) $index,
                ];
            }
        } elseif (! empty($base['task_type_ids'])) {
            $steps = array_values($base['task_type_ids']);
            $selectedDepts = RndTaskType::whereIn('id', $steps)->pluck('department_id')->filter()->unique()->values()->all();
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        if (empty($steps)) {
            return back()->withErrors(['tasks' => '请至少启用一个属于已选部门的任务'])->withInput();
        }

        ProjectTemplate::create([
            'code' => 'TPL' . now()->format('YmdHis') . strtoupper(substr(uniqid(), -3)),
            'name' => $base['name'],
            'description' => $base['description'] ?? null,
            'user_id' => auth()->id(),
            'steps' => $steps,
        ]);

        return redirect()->route('project.templates')->with('success', '模板已创建：' . $base['name']);
    }

    /** 创建项目页“导入模板配置”接口 */
    public function config(ProjectTemplate $template)
    {
        $configs = $template->taskConfigs();
        $taskTypes = RndTaskType::whereIn('id', collect($configs)->pluck('task_type_id')->all())
            ->get()
            ->keyBy('id');

        $rows = [];
        $selectedDepts = [];
        $deptDays = [];

        foreach ($configs as $index => $config) {
            $type = $taskTypes->get((int) ($config['task_type_id'] ?? 0));
            if (! $type) {
                continue;
            }
            $deptId = (int) ($config['department_id'] ?? $type->department_id ?? 0);
            if (! $deptId) {
                continue;
            }
            $selectedDepts[$deptId] = $deptId;
            $deptDays[$deptId] = (int) ($config['days'] ?? 5);

            $rows[] = [
                'task_type_id' => (int) $type->id,
                'task_type_name' => $type->name,
                'name' => $config['name'] ?? $type->name,
                'department_id' => $deptId,
                'executor_id' => isset($config['executor_id']) ? (int) $config['executor_id'] : null,
                'auditor_id' => isset($config['auditor_id']) ? (int) $config['auditor_id'] : null,
                'days' => (int) ($config['days'] ?? 5),
                'sort_order' => (int) ($config['sort_order'] ?? $index),
            ];
        }

        return response()->json([
            'selected_depts' => array_values($selectedDepts),
            'dept_days' => (object) $deptDays,
            'tasks' => $rows,
        ]);
    }

    /**
     * 用模板建项目：按模板配置的部门顺序串行排期，并生成任务。
     */
    public function apply(Request $request, ProjectTemplate $template)
    {
        $data = $request->validate([
            'project_name' => ['required', 'string', 'max:128'],
            'product_model' => ['nullable', 'string', 'max:64'],
            'start_date' => ['required', 'date'],
        ]);

        $configs = $template->taskConfigs();
        $taskTypes = RndTaskType::whereIn('id', collect($configs)->pluck('task_type_id')->all())
            ->get()
            ->keyBy('id');

        $tasksByDept = [];
        $selectedDepts = [];
        $deptDays = [];
        $defaultExecutor = auth()->id();

        foreach ($configs as $config) {
            $type = $taskTypes->get((int) ($config['task_type_id'] ?? 0));
            if (! $type) {
                continue;
            }
            $deptId = (int) ($config['department_id'] ?? $type->department_id ?? 0);
            if (! $deptId) {
                continue;
            }
            $selectedDepts[$deptId] = $deptId;
            $deptDays[$deptId] = (int) ($config['days'] ?? 5);
            $tasksByDept[$deptId][] = [
                'task_type_id' => (int) $type->id,
                'name' => $config['name'] ?? $type->name,
                'department_id' => $deptId,
                'executor_id' => $config['executor_id'] ?? $defaultExecutor,
                'auditor_id' => $config['auditor_id'] ?? null,
            ];
        }

        $project = $this->service->createProject(
            $data + ['version' => 'V1.0'],
            $tasksByDept,
            array_values($selectedDepts),
            $deptDays
        );

        return redirect()->route('rnd.projects.show', $project)->with('success', '项目已按模板创建，任务已生成。');
    }
}
