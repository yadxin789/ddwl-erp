<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MediaFile;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * P0-05 / D-01：研发项目/任务/交付物逐对象授权 + 列表语义统一
 *
 * rnd_projects / rnd_project_tasks 在演示库是 0 行，所以全部测试数据在本用例内造，
 * 事务包裹 + 结束时回滚，不污染开发库。
 */
class RndAuthorizationTest extends TestCase
{
    private User $creator;      // 研发经理：建项目
    private User $executor;     // 工程师：执行任务
    private User $auditor;      // 审核人
    private User $outsider;     // 与项目无关的工程师

    private RndProject $project;
    private RndProjectTask $task;
    private RndProjectDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->creator = User::where('username', '王明')->firstOrFail();
        $this->executor = User::where('username', '孙八')->firstOrFail();
        $this->auditor = User::where('username', '韩磊')->firstOrFail();
        $this->outsider = User::where('username', '唐斌')->firstOrFail();

        $this->project = RndProject::create([
            'project_code' => NumberGenerator::generate('rnd_project'),
            'project_name' => 'P0-05 授权测试项目',
            'creator_id' => $this->creator->id,
            'status' => 'in_progress',
            'progress' => 0,
        ]);

        $type = RndTaskType::firstOrCreate(
            ['name' => 'P0-05 测试任务类型'],
            ['description' => '测试用', 'is_required' => false, 'sort_order' => 999]
        );

        $this->task = RndProjectTask::create([
            'project_id' => $this->project->id,
            'task_type_id' => $type->id,
            'executor_id' => $this->executor->id,
            'auditor_id' => $this->auditor->id,
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        $file = MediaFile::create([
            'file_name' => 'p0-05-test.pdf',
            'file_path' => 'rnd_doc/p0-05-test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'category' => 'rnd_doc',
            'uploader_id' => $this->executor->id,
            'created_at' => now(),
        ]);

        $this->document = RndProjectDocument::create([
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'version_no' => 1,
            'file_id' => $file->id,
            'uploader_id' => $this->executor->id,
            'audit_status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_outsider_cannot_view_project_or_assign_task(): void
    {
        $this->actingAs($this->outsider);

        $this->get("/rnd/projects/{$this->project->id}")->assertStatus(403);
        $this->post("/rnd/projects/{$this->project->id}/tasks", [
            'task_type_id' => $this->task->task_type_id,
            'executor_id' => $this->outsider->id,
            'end_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(403);
    }

    public function test_participants_can_view_project_but_only_creator_assigns_tasks(): void
    {
        $this->actingAs($this->executor);
        $this->get("/rnd/projects/{$this->project->id}")->assertStatus(200);

        // 执行人不是创建人：不能派任务
        $this->post("/rnd/projects/{$this->project->id}/tasks", [
            'task_type_id' => $this->task->task_type_id,
            'executor_id' => $this->executor->id,
            'end_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(403);

        $this->actingAs($this->creator);
        $this->get("/rnd/projects/{$this->project->id}")->assertStatus(200);
        $this->post("/rnd/projects/{$this->project->id}/tasks", [
            'task_type_id' => $this->task->task_type_id,
            'executor_id' => $this->executor->id,
            'end_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(302);
    }

    public function test_only_task_executor_can_upload_document(): void
    {
        $this->assertTrue(Gate::forUser($this->executor)->allows('upload', $this->task));
        $this->assertFalse(Gate::forUser($this->outsider)->allows('upload', $this->task));
        $this->assertTrue(Gate::forUser($this->creator)->allows('upload', $this->task), '项目创建人可代交');

        $this->actingAs($this->outsider);
        $this->post("/rnd/tasks/{$this->task->id}/documents")->assertStatus(403);
    }

    public function test_only_auditor_or_manager_can_audit_document(): void
    {
        $this->assertTrue(Gate::forUser($this->auditor)->allows('audit', $this->document), '指定审核人可判定');
        $this->assertTrue(Gate::forUser($this->creator)->allows('audit', $this->document), '研发经理可兜底判定');
        $this->assertFalse(Gate::forUser($this->outsider)->allows('audit', $this->document));

        // 审核人不是上传人：控制器自己取单据（不信路由绑定）后授权通过
        $this->actingAs($this->auditor);
        $this->post("/rnd/documents/{$this->document->id}/audit", ['pass' => true])->assertStatus(302);
        $this->assertSame('approved', $this->document->refresh()->audit_status);

        // 非参与者：同一路由应 403
        $this->actingAs($this->outsider);
        $this->post("/rnd/documents/{$this->document->id}/audit", ['pass' => true])->assertStatus(403);
    }

    public function test_project_list_uses_mine_scope(): void
    {
        $this->actingAs($this->executor);
        $this->get('/rnd/projects')->assertStatus(200)->assertSee('P0-05 授权测试项目');

        $this->actingAs($this->outsider);
        $this->get('/rnd/projects')->assertStatus(200)->assertDontSee('P0-05 授权测试项目');
    }
}
