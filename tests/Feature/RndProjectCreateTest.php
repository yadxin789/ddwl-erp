<?php

namespace Tests\Feature;

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

        $this->manager = User::where('username', 'rnd-manager')->firstOrFail();
        $this->auditor = User::where('username', 'eng-me')->firstOrFail();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_create_page_renders_with_reference_sections(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('rnd.projects.create'))
            ->assertOk()
            ->assertSee('选择参与部门')
            ->assertSee('部门天数配置')
            ->assertSee('任务配置')
            ->assertSee('任务名称');
    }

    public function test_template_create_page_renders(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('project.templates.create'))
            ->assertOk()
            ->assertSee('创建新研发项目模板')
            ->assertSee('任务名称');
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
                    'auditor_id' => $this->auditor->id,
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
            'auditor_id' => $this->auditor->id,
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
                    'auditor_id' => $this->auditor->id,
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
            'auditor_id' => $this->auditor->id,
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
