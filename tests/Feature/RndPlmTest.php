<?php

namespace Tests\Feature;

use App\Models\Bom\BomHeader;
use App\Models\Bom\BomLine;
use App\Models\MasterData\Material;
use App\Models\Organization\User;
use App\Models\Production\ProductionOrder;
use App\Models\Rnd\EcrChangeOrder;
use App\Models\Rnd\RndExtensionApplication;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Services\ApprovalService;
use App\Services\RndProjectService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-05 研发 PLM 最小闭环
 * - 阶段门：无基线不得进试产、待审批 ECR 不得进量产
 * - 基线：同一项目只 1 个 is_baseline，旧基线写 superseded_by
 * - ECR：研发经理审批通过后才生成新 BOM 版本（旧版本 superseded）
 * - 任务：开始 → 提交（需有交付物）→ 通过；延期批准后 end_date 顺延、is_overdue 复位
 */
class RndPlmTest extends TestCase
{
    private int $projectId;
    private int $taskTypeId;
    private int $engId;
    private int $managerId;
    private int $bomId;
    private int $materialId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->engId = (int) User::where('username', '唐斌')->value('id');
        $this->managerId = (int) User::where('username', '王明')->value('id');
        $this->materialId = (int) Material::where('material_code', 'EL000001')->value('id');
        $this->taskTypeId = (int) (RndTaskType::query()->value('id') ?? 0);
        if (! $this->taskTypeId) {
            $type = RndTaskType::create(['name' => 'PLM 测试交付物', 'is_required' => true, 'sort_order' => 99]);
            $this->taskTypeId = (int) $type->id;
        }

        $this->projectId = (int) RndProject::create([
            'project_code' => 'PRJ-PLM-' . substr((string) uniqid(), -5),
            'project_name' => 'PLM 测试项目',
            'product_model' => 'TEST-01',
            'creator_id' => $this->engId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'in_progress',
            'stage' => 'initiation',
            'progress' => 0,
        ])->id;

        $this->bomId = (int) BomHeader::create([
            'bom_code' => 'BOM-PLM-' . substr((string) uniqid(), -5),
            'product_material_id' => $this->materialId,
            'version' => 'V1',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
            'created_by' => $this->engId,
        ])->id;
        BomLine::create([
            'bom_header_id' => $this->bomId,
            'parent_material_id' => $this->materialId,
            'material_id' => $this->materialId,
            'qty_per' => 1,
            'unit' => 'PCS',
            'loss_rate' => 0,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function makeTask(string $status = 'pending'): RndProjectTask
    {
        return RndProjectTask::withoutGlobalScopes()->create([
            'project_id' => $this->projectId,
            'task_type_id' => $this->taskTypeId,
            'executor_id' => $this->engId,
            'auditor_id' => $this->managerId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => $status,
        ]);
    }

    private function makeDocument(string $auditStatus = 'approved'): RndProjectDocument
    {
        $fileId = (int) DB::table('media_files')->insertGetId([
            'file_name' => 'plm-test.pdf',
            'file_path' => 'rnd_doc/plm-test-' . uniqid() . '.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'category' => 'rnd_doc',
            'uploader_id' => $this->engId,
            'ref_type' => 'rnd_document',
            'ref_id' => $this->projectId,
            'created_at' => now(),
        ]);

        return RndProjectDocument::withoutGlobalScopes()->create([
            'project_id' => $this->projectId,
            'task_id' => null,
            'version_no' => (int) RndProjectDocument::withoutGlobalScopes()->where('project_id', $this->projectId)->max('version_no') + 1,
            'file_id' => $fileId,
            'uploader_id' => $this->engId,
            'audit_status' => $auditStatus,
            'auditor_id' => $auditStatus === 'approved' ? $this->managerId : null,
            'audit_at' => $auditStatus === 'approved' ? now() : null,
        ]);
    }

    public function test_ecr_full_form_impact_and_eco_number(): void
    {
        $manager = User::where('username', '王明')->firstOrFail();
        $applicant = User::where('username', '唐斌')->firstOrFail();
        $this->actingAs($applicant);

        ProductionOrder::withoutGlobalScopes()->create([
            'order_no' => 'WO-ECR-' . substr((string) uniqid(), -5),
            'product_material_id' => $this->materialId,
            'bom_id' => $this->bomId,
            'routing_id' => null,
            'plan_qty' => 100,
            'completed_qty' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'created_by' => $manager->id,
        ]);

        $impact = $this->get(route('rnd.ecr.impact', $this->projectId) . '?bom_id=' . $this->bomId);
        $impact->assertOk();
        $impact->assertJsonPath('open_work_orders.count', 1);
        $impact->assertJsonPath('open_work_orders.remaining_qty', 90);
        $impact->assertJsonPath('risk_level', 'high');

        $materialCode = (string) Material::where('id', $this->materialId)->value('material_code');
        $replacementId = (int) Material::where('id', '!=', $this->materialId)->value('id');
        $replacementCode = (string) Material::where('id', $replacementId)->value('material_code');
        $effective = now()->addDays(7)->toDateString();
        $response = $this->post(route('rnd.ecr.store', $this->projectId), [
            'bom_id' => $this->bomId,
            'change_type' => 'material',
            'priority' => 'high',
            'effective_date' => $effective,
            'title' => '电容替代料切换',
            'scope_description' => 'SMT 贴片段 + BOM 第 3 行',
            'impacted_material_codes' => $materialCode,
            'replacement_material_codes' => $replacementCode,
            'reason' => '原厂停产，切换为同规格替代料并验证温漂',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $ecr = EcrChangeOrder::withoutGlobalScopes()->where('project_id', $this->projectId)->orderByDesc('id')->firstOrFail();
        $this->assertSame('high', $ecr->priority);
        $this->assertSame([$this->materialId], $ecr->impacted_material_ids);
        $this->assertSame([$replacementId], $ecr->replacement_material_ids);
        $this->assertSame($effective, $ecr->effective_date?->toDateString());
        $this->assertSame('high', $ecr->impact_analysis['risk_level']);
        $this->assertSame(1, $ecr->impact_analysis['open_work_orders']['count']);

        $this->actingAs($manager);
        app(RndProjectService::class)->approveEcr($ecr->id, $manager->id);
        $approved = EcrChangeOrder::withoutGlobalScopes()->findOrFail($ecr->id);
        $this->assertNotNull($approved->eco_no);
        $this->assertStringStartsWith('ECO-', $approved->eco_no);
        $this->assertNotNull($approved->eco_executed_at);
        $this->assertSame($effective, BomHeader::findOrFail($approved->new_bom_id)->effective_date?->toDateString());
        $this->assertTrue(
            BomLine::where('bom_header_id', $approved->new_bom_id)->where('material_id', $replacementId)->exists(),
            'ECO 应把受影响物料替换为替代物料'
        );
    }

    public function test_stage_gate_requires_baseline_and_blocks_pending_ecr(): void
    {
        $svc = app(RndProjectService::class);
        $this->actingAs(User::where('username', '唐斌')->firstOrFail());

        // 必须逐级：立项不能直接跳试产
        try {
            $svc->setStage($this->projectId, 'trial');
            $this->fail('立项直接跳试产应被拦截');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('逐级', $e->getMessage());
        }

        $svc->setStage($this->projectId, 'design');
        $this->assertSame('design', RndProject::withoutGlobalScopes()->find($this->projectId)->stage);

        // 设计 → 试产：无基线被拦
        try {
            $svc->setStage($this->projectId, 'trial');
            $this->fail('无基线不得进试产');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('基线', $e->getMessage());
        }

        // 审核通过并设基线后放行
        $doc = $this->makeDocument('approved');
        $svc->setBaseline($doc->id);
        $svc->setStage($this->projectId, 'trial');
        $this->assertSame('trial', RndProject::withoutGlobalScopes()->find($this->projectId)->stage);

        // 有待审批 ECR → 不得进量产
        $ecr = $svc->createEcr([
            'project_id' => $this->projectId,
            'bom_id' => $this->bomId,
            'change_type' => 'bom',
            'title' => '替换电容规格',
            'reason' => '供应紧张',
        ]);
        try {
            $svc->setStage($this->projectId, 'mass_production');
            $this->fail('有待审批 ECR 不得进量产');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('变更单', $e->getMessage());
        }

        // 研发经理批准 → ECR 生成 V2，旧版本 superseded，量产门放行
        $this->actingAs(User::where('username', '王明')->firstOrFail());
        app(ApprovalService::class)->decide(
            (int) \App\Models\Approval\ApprovalInstance::where('business_type', 'ecr.change')->where('business_id', $ecr->id)->value('id'),
            true,
            '同意变更'
        );

        $approved = EcrChangeOrder::withoutGlobalScopes()->findOrFail($ecr->id);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->new_bom_id);
        $newBom = BomHeader::findOrFail($approved->new_bom_id);
        $this->assertSame('V2', $newBom->version);
        $this->assertStringContainsString('-V2', $newBom->bom_code, '新版本编码带版本后缀（bom_code 全库唯一）');
        $this->assertSame('superseded', BomHeader::findOrFail($this->bomId)->status);

        $this->actingAs(User::where('username', '唐斌')->firstOrFail());
        $svc->setStage($this->projectId, 'mass_production');
        $this->assertSame('mass_production', RndProject::withoutGlobalScopes()->find($this->projectId)->stage);
    }

    public function test_baseline_supersedes_previous_version(): void
    {
        $svc = app(RndProjectService::class);
        $v1 = $this->makeDocument('approved');
        $v2 = $this->makeDocument('approved');

        $svc->setBaseline($v1->id);
        $this->assertTrue(RndProjectDocument::withoutGlobalScopes()->find($v1->id)->is_baseline);

        $svc->setBaseline($v2->id);
        $old = RndProjectDocument::withoutGlobalScopes()->find($v1->id);
        $this->assertFalse((bool) $old->is_baseline, '旧基线应让位');
        $this->assertSame($v2->id, (int) $old->superseded_by, '旧基线应记录被谁替代');
        $this->assertTrue(RndProjectDocument::withoutGlobalScopes()->find($v2->id)->is_baseline);
    }

    public function test_task_lifecycle_and_extension_resets_overdue(): void
    {
        $svc = app(RndProjectService::class);
        $this->actingAs(User::where('username', '唐斌')->firstOrFail());

        $task = $this->makeTask();
        $svc->startTask($task->id);
        $this->assertSame('in_progress', $task->fresh()->status);

        // 没有交付物不能提交
        try {
            $svc->submitTask($task->id);
            $this->fail('无交付物不应允许提交');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('交付物', $e->getMessage());
        }

        $doc = $this->makeDocument('pending');
        DB::table('rnd_project_documents')->where('id', $doc->id)->update(['task_id' => $task->id]);
        $svc->submitTask($task->id);
        $this->assertSame('submitted', $task->fresh()->status);

        $this->actingAs(User::where('username', '王明')->firstOrFail());
        $svc->decideTask($task->id, true, '通过');
        $this->assertSame('approved', $task->fresh()->status);

        // 延期：逾期任务批准后 end_date 从今天顺延、is_overdue 复位
        $task2 = $this->makeTask('pending');
        DB::table('rnd_project_tasks')->where('id', $task2->id)->update([
            'end_date' => now()->subDays(3)->toDateString(),
            'is_overdue' => true,
            'status' => 'overdue',
        ]);
        $app = RndExtensionApplication::create([
            'task_id' => $task2->id,
            'applicant_id' => $this->engId,
            'apply_days' => 7,
            'reason' => '供应商延期',
            'status' => 'pending',
        ]);
        $svc->approveExtension($app->id);

        $fresh = RndProjectTask::withoutGlobalScopes()->find($task2->id);
        $this->assertFalse((bool) $fresh->is_overdue, '延期批准后逾期标记应复位');
        $this->assertSame(7, (int) $fresh->extension_days);
        $this->assertSame(now()->addDays(7)->toDateString(), $fresh->end_date->toDateString(), 'end_date 应从今天顺延 7 天');
    }
}
