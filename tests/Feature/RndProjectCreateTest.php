<?php

namespace Tests\Feature;

use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 研发项目创建页 / 模板创建页对齐参考项目后的回归：
 * - 任务名称独立字段
 * - 部门天数串行排期
 * - 模板保存完整任务配置
 */
class RndProjectCreateTest extends TestCase
{
    private User $manager;
    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->manager = User::where('username', '王明')->firstOrFail();
        $this->auditor = User::where('username', '王明')->firstOrFail();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function auditorFor(int $deptId): User
    {
        $manager = Department::findOrFail($deptId)->resolvedManager();
        $this->assertNotNull($manager, '测试前置：部门必须有部门经理');

        return $manager;
    }

    public function test_project_store_rejects_auditor_who_is_not_department_manager(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;

        $this->post(route('rnd.projects.store'), [
            'project_name' => '审核人越权项目',
            'start_date' => now()->toDateString(),
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 5],
            'tasks' => [[
                'task_type_id' => $type->id,
                'name' => '越权审核任务',
                'department_id' => $deptId,
                'enabled' => '1',
                'executor_id' => $this->manager->id,
                'auditor_id' => User::where('username', '韩磊')->firstOrFail()->id,
            ]],
        ])->assertSessionHasErrors('tasks.0.auditor_id');

        $this->assertDatabaseMissing('rnd_projects', ['project_name' => '审核人越权项目']);
    }

    public function test_create_page_auditor_candidates_are_only_department_managers(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('rnd.projects.create'))->assertOk();

        $auditorsByDept = $response->viewData('auditorsByDept');
        $this->assertNotEmpty($auditorsByDept);

        foreach ($auditorsByDept as $deptId => $candidates) {
            $manager = Department::findOrFail($deptId)->resolvedManager();
            $this->assertCount(1, $candidates, '每个部门只应有 1 名审核人候选');
            $this->assertSame((int) $manager->id, (int) $candidates[0]['id']);
        }

        // 工程师不能出现在审核人候选里（只用于执行人下拉）
        $auditorIds = collect($auditorsByDept)->flatten(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) User::where('username', '韩磊')->firstOrFail()->id, $auditorIds);
        $this->assertNotContains((int) User::where('username', '孙八')->firstOrFail()->id, $auditorIds);
    }

    public function test_create_page_renders_with_reference_sections(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('rnd.projects.create'))
            ->assertOk()
            ->assertSee('选择参与部门')
            ->assertSee('部门天数配置')
            ->assertSee('任务配置')
            ->assertSee('任务名称')
            ->assertSee('"label":"王明"', false);
    }

    public function test_template_create_page_renders(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('project.templates.create'))
            ->assertOk()
            ->assertSee('创建新研发项目模板')
            ->assertSee('任务名称')
            ->assertSee('"label":"王明"', false);
    }

    public function test_project_store_creates_named_tasks_with_serial_department_dates(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;

        $response = $this->post(route('rnd.projects.store'), [
            'project_name' => '研发创建页回归项目',
            'version' => 'V1.2',
            'product_name' => '回归产品',
            'product_model' => 'RG-01',
            'start_date' => now()->toDateString(),
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 5],
            'tasks' => [
                [
                    'task_type_id' => $type->id,
                    'name' => '自定义任务名称',
                    'department_id' => $deptId,
                    'enabled' => '1',
                    'executor_id' => $this->manager->id,
                    'auditor_id' => $this->auditorFor($deptId)->id,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $project = RndProject::where('project_name', '研发创建页回归项目')->firstOrFail();
        $this->assertSame('V1.2', $project->version);
        $this->assertSame('回归产品', $project->product_name);
        $this->assertSame('initiation', $project->stage);

        $task = RndProjectTask::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('自定义任务名称', $task->name);
        $this->assertSame($deptId, (int) $task->department_id);
        $this->assertSame(now()->toDateString(), $task->start_date->toDateString());
        $this->assertSame(now()->addDays(4)->toDateString(), $task->end_date->toDateString());

        $this->get(route('rnd.projects.show', $project))
            ->assertOk()
            ->assertSee('"label":"' . $this->manager->username . '"', false);
    }

    public function test_project_store_accepts_multiple_tasks_of_same_type(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;
        $task = [
            'task_type_id' => $type->id,
            'department_id' => $deptId,
            'enabled' => '1',
            'executor_id' => $this->manager->id,
            'auditor_id' => $this->auditorFor($deptId)->id,
        ];

        $this->post(route('rnd.projects.store'), [
            'project_name' => '同类型多任务回归项目',
            'start_date' => now()->toDateString(),
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 5],
            'tasks' => [
                $task + ['name' => '电源原理图'],
                $task + ['name' => '主控原理图'],
                $task + ['name' => '驱动原理图'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = RndProject::where('project_name', '同类型多任务回归项目')->firstOrFail();
        $names = RndProjectTask::where('project_id', $project->id)->orderBy('id')->pluck('name')->all();

        $this->assertSame(['电源原理图', '主控原理图', '驱动原理图'], $names);
    }

    public function test_template_detail_renders_and_can_be_updated(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;
        $task = [
            'task_type_id' => $type->id,
            'department_id' => $deptId,
            'enabled' => '1',
            'executor_id' => $this->manager->id,
            'auditor_id' => $this->auditorFor($deptId)->id,
        ];

        $this->post(route('project.templates.store'), [
            'name' => '待编辑模板',
            'description' => '原描述',
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 5],
            'tasks' => [
                $task + ['name' => '电源原理图'],
                $task + ['name' => '主控原理图'],
            ],
        ])->assertRedirect(route('project.templates'));

        $template = ProjectTemplate::where('name', '待编辑模板')->firstOrFail();

        $this->get(route('project.templates.show', $template))
            ->assertOk()
            ->assertSee('模板详情')
            ->assertSee('待编辑模板')
            ->assertSee('电源原理图')
            ->assertSee('主控原理图');

        $this->put(route('project.templates.update', $template), [
            'name' => '已编辑模板',
            'description' => '新描述',
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 6],
            'tasks' => [
                $task + ['name' => '更新后的原理图'],
            ],
        ])->assertRedirect(route('project.templates.show', $template));

        $template->refresh();
        $this->assertSame('已编辑模板', $template->name);
        $this->assertSame('新描述', $template->description);
        $this->assertCount(1, $template->taskConfigs());
        $this->assertSame('更新后的原理图', $template->taskConfigs()[0]['name']);
        $this->assertSame(6, (int) $template->taskConfigs()[0]['days']);
    }

    public function test_template_index_has_detail_and_delete_instead_of_apply(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('project.templates'))
            ->assertOk()
            ->assertSee('详情')
            ->assertSee('删除')
            ->assertSee('deleteTemplateModal')
            ->assertDontSee('用此模板建项目');
    }

    public function test_template_can_be_deleted(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;

        $this->post(route('project.templates.store'), [
            'name' => '待删除模板',
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 3],
            'tasks' => [[
                'task_type_id' => $type->id,
                'name' => '待删除任务',
                'department_id' => $deptId,
                'enabled' => '1',
                'executor_id' => $this->manager->id,
                'auditor_id' => $this->auditorFor($deptId)->id,
            ]],
        ])->assertRedirect(route('project.templates'));

        $template = ProjectTemplate::where('name', '待删除模板')->firstOrFail();

        $this->delete(route('project.templates.destroy', $template))
            ->assertRedirect(route('project.templates'));

        $this->assertDatabaseMissing('project_templates', ['id' => $template->id]);
    }

    public function test_template_store_saves_full_task_config(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;

        $this->post(route('project.templates.store'), [
            'name' => '回归模板',
            'description' => '测试模板',
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 3],
            'tasks' => [
                [
                    'task_type_id' => $type->id,
                    'name' => '模板任务名称',
                    'department_id' => $deptId,
                    'enabled' => '1',
                    'executor_id' => $this->manager->id,
                    'auditor_id' => $this->auditorFor($deptId)->id,
                ],
            ],
        ])->assertRedirect(route('project.templates'));

        $template = ProjectTemplate::where('name', '回归模板')->firstOrFail();
        $this->assertSame('测试模板', $template->description);
        $this->assertSame($this->manager->id, (int) $template->user_id);

        $config = $template->taskConfigs()[0];
        $this->assertSame($type->id, (int) $config['task_type_id']);
        $this->assertSame('模板任务名称', $config['name']);
        $this->assertSame($deptId, (int) $config['department_id']);
        $this->assertSame(3, (int) $config['days']);

        $this->get(route('project.templates.config', $template))
            ->assertOk()
            ->assertJsonPath('tasks.0.name', '模板任务名称')
            ->assertJsonPath('tasks.0.task_type_id', $type->id);
    }

    public function test_template_store_accepts_multiple_tasks_of_same_type(): void
    {
        $this->actingAs($this->manager);

        $type = RndTaskType::whereNotNull('department_id')->orderBy('sort_order')->firstOrFail();
        $deptId = (int) $type->department_id;
        $task = [
            'task_type_id' => $type->id,
            'department_id' => $deptId,
            'enabled' => '1',
            'executor_id' => $this->manager->id,
            'auditor_id' => $this->auditorFor($deptId)->id,
        ];

        $this->post(route('project.templates.store'), [
            'name' => '同类型多任务模板',
            'description' => '一个类型下多张图纸',
            'selected_depts' => [$deptId],
            'dept_days' => [$deptId => 5],
            'tasks' => [
                $task + ['name' => '电源原理图'],
                $task + ['name' => '主控原理图'],
            ],
        ])->assertRedirect(route('project.templates'));

        $template = ProjectTemplate::where('name', '同类型多任务模板')->firstOrFail();
        $this->assertCount(2, $template->taskConfigs());
        $this->assertSame(['电源原理图', '主控原理图'], array_column($template->taskConfigs(), 'name'));

        $this->get(route('project.templates.config', $template))
            ->assertOk()
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('tasks.0.name', '电源原理图')
            ->assertJsonPath('tasks.1.name', '主控原理图');

        $this->post(route('project.templates.apply', $template), [
            'project_name' => '按多任务模板创建的项目',
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

        $project = RndProject::where('project_name', '按多任务模板创建的项目')->firstOrFail();
        $names = RndProjectTask::where('project_id', $project->id)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['电源原理图', '主控原理图'], $names);
    }
}

