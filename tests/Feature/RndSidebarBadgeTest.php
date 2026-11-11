<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Support\NavigationService;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 侧边栏统计徽章：研发口径 = 我的项目（创建∪执行∪审核）、我的待执行任务、文档审核。
 */
class RndSidebarBadgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_sidebar_badges_follow_project_task_and_audit_scope(): void
    {
        $creator = User::where('username', '王明')->firstOrFail();
        $executor = User::where('username', '孙八')->firstOrFail();
        $auditor = User::where('username', '罗娟')->firstOrFail();
        $type = RndTaskType::where('name', '市场调研报告')->firstOrFail();

        $project = RndProject::create([
            'project_code' => NumberGenerator::generate('rnd_project'),
            'project_name' => '侧边栏徽章测试项目',
            'creator_id' => $creator->id,
            'status' => 'in_progress',
            'stage' => 'initiation',
            'progress' => 0,
        ]);

        RndProjectTask::create([
            'project_id' => $project->id,
            'task_type_id' => $type->id,
            'name' => '市场调研',
            'department_id' => $type->department_id,
            'executor_id' => $executor->id,
            'auditor_id' => $auditor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        // 待审核任务（已提交，等待部门经理判定）
        RndProjectTask::create([
            'project_id' => $project->id,
            'task_type_id' => $type->id,
            'name' => '市场调研（待审）',
            'department_id' => $type->department_id,
            'executor_id' => $executor->id,
            'auditor_id' => $auditor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'submitted',
        ]);

        $nav = app(NavigationService::class)->forUser($executor);
        $items = collect(array_merge($nav['business'], $nav['shared']));
        $this->assertSame(1, $items->firstWhere('name', '我的项目列表')['badge'], '执行人能在“我的项目”里看到参与项目');
        $this->assertSame(1, $items->firstWhere('name', '我的任务')['badge'], '执行人有 1 条待执行任务');

        $nav = app(NavigationService::class)->forUser($auditor);
        $items = collect(array_merge($nav['business'], $nav['shared']));
        $this->assertSame(1, $items->firstWhere('name', '我的项目列表')['badge'], '审核人能在“我的项目”里看到参与项目');
        $this->assertSame(1, $items->firstWhere('name', '我的任务')['badge'], '审核人有 1 条待审核任务');
        $this->assertSame(0, $items->firstWhere('name', '文档审核')['badge'], '尚无待审交付物时为 0');
    }
}
