<?php

namespace App\Http\Controllers;

use App\Models\Organization\Department;
use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MasterDataDomain;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 基础数据归口管理：
 * - 归口部门负责口径与命名标准
 * - 管理员在后台统一维护，业务角色先在各自页面只读查看
 */
class MasterDataController extends Controller
{
    public function index()
    {
        $domains = MasterDataDomain::query()
            ->with('ownerDepartment')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $departments = Department::query()
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('dept_code')
            ->get();

        return view('admin.master-data', compact('domains', 'departments'));
    }

    public function updateDomain(Request $request, MasterDataDomain $domain)
    {
        $data = $request->validate([
            'owner_department_id' => ['required', 'exists:departments,id'],
            'status' => ['required', 'in:0,1'],
        ]);

        $domain->update($data);

        return back()->with('success', '归口已更新：' . $domain->name);
    }

    public function taskTypes()
    {
        $types = RndTaskType::query()
            ->with('department')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->paginate(50);

        $departments = Department::query()
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('dept_code')
            ->get();

        $taskUsage = RndProjectTask::query()
            ->selectRaw('task_type_id, COUNT(*) as aggregate')
            ->groupBy('task_type_id')
            ->pluck('aggregate', 'task_type_id')
            ->all();

        $templateUsage = [];
        foreach (ProjectTemplate::query()->get(['id', 'name', 'steps']) as $template) {
            foreach ($template->taskConfigs() as $config) {
                $typeId = (int) ($config['task_type_id'] ?? 0);
                if ($typeId) {
                    $templateUsage[$typeId] = ($templateUsage[$typeId] ?? 0) + 1;
                }
            }
        }

        $nextSortOrder = (RndTaskType::max('sort_order') ?? 0) + 1;

        return view('admin.master-data-task-types', compact(
            'types', 'departments', 'taskUsage', 'templateUsage', 'nextSortOrder'
        ));
    }

    public function taskTypeStore(Request $request)
    {
        $data = $this->validateTaskType($request);

        RndTaskType::create([
            'code' => $this->nextTaskTypeCode(),
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $data['sort_order'] ?? ((RndTaskType::max('sort_order') ?? 0) + 1),
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '任务类型已创建：' . $data['name']);
    }

    public function taskTypeUpdate(Request $request, RndTaskType $taskType)
    {
        $data = $this->validateTaskType($request, $taskType->id);

        $taskType->update([
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $data['sort_order'] ?? $taskType->sort_order,
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '任务类型已保存：' . $taskType->name);
    }

    public function taskTypeDestroy(RndTaskType $taskType)
    {
        $taskCount = RndProjectTask::where('task_type_id', $taskType->id)->count();

        $templateCount = 0;
        foreach (ProjectTemplate::query()->get(['id', 'steps']) as $template) {
            if (collect($template->taskConfigs())->contains(
                fn ($config) => (int) ($config['task_type_id'] ?? 0) === (int) $taskType->id
            )) {
                $templateCount++;
            }
        }

        if ($taskCount > 0 || $templateCount > 0) {
            return back()->withErrors([
                'task_type' => sprintf(
                    '「%s」已被 %d 个项目任务、%d 个项目模板引用，不能删除；请改为停用。',
                    $taskType->name,
                    $taskCount,
                    $templateCount
                ),
            ]);
        }

        $name = $taskType->name;
        $taskType->delete();

        return back()->with('success', '任务类型已删除：' . $name);
    }

    private function validateTaskType(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('rnd_task_types', 'name')->ignore($ignoreId),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ], [
            'name.required' => '请输入任务类型名称',
            'name.unique' => '任务类型名称已存在',
            'department_id.required' => '请选择执行部门',
        ]);
    }

    private function nextTaskTypeCode(): string
    {
        $number = RndTaskType::count() + 1;
        do {
            $code = sprintf('RND-TT-%03d', $number);
            $number++;
        } while (RndTaskType::where('code', $code)->exists());

        return $code;
    }
}
