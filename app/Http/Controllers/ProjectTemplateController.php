<?php

namespace App\Http\Controllers;

use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 项目模板（G11）：以任务类型组合为模板，一键建项目并按模板生成任务。
 */
class ProjectTemplateController extends Controller
{
    public function index()
    {
        $templates = ProjectTemplate::query()->orderBy('name')->get();
        $taskTypes = RndTaskType::orderBy('sort_order')->get(['id', 'name', 'department_id']);

        return view('project.templates', compact('templates', 'taskTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'task_type_ids' => ['required', 'array', 'min:1'],
            'task_type_ids.*' => ['integer', 'exists:rnd_task_types,id'],
        ]);

        ProjectTemplate::create([
            'code' => 'TPL' . now()->format('YmdHis'),
            'name' => $data['name'],
            'steps' => array_values($data['task_type_ids']),
        ]);

        return back()->with('success', '模板已创建：' . $data['name']);
    }

    /**
     * 用模板建项目：生成项目 + 每个任务类型一条任务（截止日按模板任务顺序 +7 天递推）。
     */
    public function apply(Request $request, ProjectTemplate $template)
    {
        $data = $request->validate([
            'project_name' => ['required', 'string', 'max:128'],
            'product_model' => ['nullable', 'string', 'max:64'],
            'start_date' => ['required', 'date'],
        ]);

        $taskTypes = RndTaskType::whereIn('id', array_filter((array) ($template->steps ?? []), 'is_numeric'))
            ->orderBy('sort_order')
            ->get();

        DB::transaction(function () use ($data, $taskTypes) {
            $project = RndProject::create([
                'project_code' => 'PRJ' . now()->format('YmdHis'),
                'project_name' => $data['project_name'],
                'product_model' => $data['product_model'] ?? null,
                'creator_id' => auth()->id(),
                'start_date' => $data['start_date'],
                'end_date' => null,
                'status' => 'in_progress',
                'progress' => 0,
            ]);

            $offset = 0;
            foreach ($taskTypes as $taskType) {
                $offset += 7;
                RndProjectTask::create([
                    'project_id' => $project->id,
                    'task_type_id' => $taskType->id,
                    'department_id' => $taskType->department_id,
                    'executor_id' => auth()->id(),
                    'auditor_id' => null,
                    'start_date' => $data['start_date'],
                    'end_date' => now()->parse($data['start_date'])->addDays($offset)->toDateString(),
                    'status' => 'pending',
                ]);
            }
        });

        return redirect()->route('project.templates')->with('success', '项目已按模板创建，任务已生成。');
    }
}
