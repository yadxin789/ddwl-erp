<?php

namespace App\Services;

use App\Models\Organization\Department;
use App\Models\Rnd\EcrChangeOrder;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RndProjectService
{
    public function __construct(protected NotificationService $notifier)
    {
    }

    /**
     * 创建研发项目。
     *
     * 传入选中的部门、部门天数与按部门分组的任务时，会按参考项目口径一次性生成任务：
     * 部门间串行、部门内并联（同部门任务共用起止日期）。
     */
    public function createProject(array $data, array $tasksByDept = [], array $selectedDeptIds = [], array $deptDays = []): RndProject
    {
        Log::info('创建研发项目', ['user_id' => Auth::id(), 'project_name' => $data['project_name'] ?? null]);

        foreach ($tasksByDept as $deptId => $tasks) {
            foreach ($tasks as $taskData) {
                $this->assertAuditorIsDepartmentManager(
                    (int) $deptId,
                    $taskData['auditor_id'] ?? null,
                    $taskData['executor_id'] ?? null,
                    (string) (($taskData['name'] ?? '') ?: '未命名任务')
                );
            }
        }

        return DB::transaction(function () use ($data, $tasksByDept, $selectedDeptIds, $deptDays) {
            $deptIds = Department::query()
                ->whereIn('id', array_map('intval', $selectedDeptIds))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $start = \Carbon\Carbon::parse($data['start_date'] ?? now()->toDateString());
            $deptStart = $start->copy();
            $projectEnd = $start->copy();

            foreach ($deptIds as $deptId) {
                $days = max(1, (int) ($deptDays[$deptId] ?? 1));
                $deptEnd = $deptStart->copy()->addDays($days - 1);
                $projectEnd = $deptEnd->copy();
                $deptStart = $deptEnd->copy()->addDay();
            }

            $project = RndProject::create([
                'project_code' => ! empty($data['code']) ? $data['code'] : NumberGenerator::generate('rnd_project'),
                'project_name' => $data['project_name'],
                'product_name' => $data['product_name'] ?? null,
                'product_model' => $data['product_model'] ?? null,
                'version' => ! empty($data['version']) ? $data['version'] : 'V1.0',
                'creator_id' => Auth::id() ?? 0,
                'start_date' => $start->toDateString(),
                'end_date' => $projectEnd->toDateString(),
                'status' => 'in_progress',
                'stage' => 'initiation',
                'progress' => 0,
            ]);

            $deptStart = $start->copy();
            foreach ($deptIds as $deptId) {
                $days = max(1, (int) ($deptDays[$deptId] ?? 1));
                $deptEnd = $deptStart->copy()->addDays($days - 1);

                foreach (($tasksByDept[$deptId] ?? []) as $taskData) {
                    $taskType = RndTaskType::find($taskData['task_type_id'] ?? null);
                    $taskName = trim((string) ($taskData['name'] ?? '')) ?: ($taskType->name ?? '未命名任务');

                    $task = RndProjectTask::create([
                        'project_id' => $project->id,
                        'task_type_id' => $taskData['task_type_id'],
                        'name' => $taskName,
                        'department_id' => $deptId,
                        'executor_id' => $taskData['executor_id'] ?? null,
                        'auditor_id' => $taskData['auditor_id'] ?? null,
                        'start_date' => $deptStart->toDateString(),
                        'end_date' => $deptEnd->toDateString(),
                        'status' => 'pending',
                    ]);

                    if (! empty($taskData['executor_id'])) {
                        $this->notifier->addTodo(
                            (int) $taskData['executor_id'],
                            'rnd_task',
                            "研发任务：{$taskName}（项目 {$project->project_code}）",
                            $deptEnd->toDateString(),
                            'rnd_task',
                            $task->id
                        );
                    }
                }

                $deptStart = $deptEnd->copy()->addDay();
            }

            return $project;
        });
    }

    public function addTask(int $projectId, array $data): RndProjectTask
    {
        Log::info('创建研发任务', ['user_id' => Auth::id(), 'project_id' => $projectId, 'executor_id' => $data['executor_id']]);

        $taskType = RndTaskType::find($data['task_type_id'] ?? null);
        $taskName = trim((string) ($data['name'] ?? '')) ?: ($taskType->name ?? '交付物');
        $deptId = (int) ($data['department_id'] ?? $taskType?->department_id ?? 0);

        $this->assertAuditorIsDepartmentManager(
            $deptId,
            $data['auditor_id'] ?? null,
            $data['executor_id'] ?? null,
            $taskName
        );

        $task = RndProjectTask::create([
            'project_id' => $projectId,
            'task_type_id' => $data['task_type_id'],
            'name' => $taskName,
            'department_id' => $deptId ?: null,
            'executor_id' => $data['executor_id'],
            'auditor_id' => $data['auditor_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'],
            'status' => 'pending',
        ]);

        // 给执行人发待办
        $this->notifier->addTodo(
            (int) $data['executor_id'],
            'rnd_task',
            "研发任务：{$taskName}（项目 #{$projectId}）",
            $data['end_date'],
            'rnd_task',
            $task->id
        );

        return $task;
    }

    public function uploadDocument(int $taskId, int $fileId): RndProjectDocument
    {
        Log::info('上传研发文档', ['user_id' => Auth::id(), 'task_id' => $taskId, 'file_id' => $fileId]);
        return DB::transaction(function () use ($taskId, $fileId) {
            $task = RndProjectTask::findOrFail($taskId);
            $maxVersion = RndProjectDocument::where('task_id', $taskId)->max('version_no') ?? 0;

            $doc = RndProjectDocument::create([
                'project_id' => $task->project_id,
                'task_id' => $taskId,
                'version_no' => $maxVersion + 1,
                'file_id' => $fileId,
                'uploader_id' => Auth::id() ?? 0,
                'audit_status' => 'pending',
                'created_at' => now(),
            ]);

            $task->update(['status' => 'submitted']);

            if ($task->auditor_id) {
                $this->notifier->addTodo(
                    (int) $task->auditor_id,
                    'approval',
                    "研发文档审核：v{$doc->version_no}（任务 #{$taskId}）",
                    null,
                    'rnd_doc_audit',
                    $doc->id
                );
            }
            return $doc;
        });
    }

    public function auditDocument(int $documentId, bool $pass, ?string $remark): RndProjectDocument
    {
        Log::info('研发文档审核', ['user_id' => Auth::id(), 'document_id' => $documentId, 'pass' => $pass]);
        return DB::transaction(function () use ($documentId, $pass, $remark) {
            // P0-05：审核人不是上传人，这里必须绕过"上传人=本人"行级范围（授权已在 Controller/Policy 完成）
            $doc = RndProjectDocument::withoutGlobalScopes()->findOrFail($documentId);
            $doc->update([
                'audit_status' => $pass ? 'approved' : 'rejected',
                'auditor_id' => Auth::id() ?? 0,
                'audit_at' => now(),
                'audit_remark' => $remark,
            ]);

            $task = $doc->task;
            if ($task) {
                // P1-05：项目还没有基线时，第一个审核通过的图档自动成为基线
                if ($pass && ! RndProjectDocument::withoutGlobalScopes()
                    ->where('project_id', $doc->project_id)->where('is_baseline', true)->exists()) {
                    $this->setBaseline($doc->id);
                }
                $task->update(['status' => $pass ? 'approved' : 'rejected']);
                $this->recalcProgress($task->project_id);
                // P0-05：项目行级范围是"创建人=本人"，审核人取不到，必须绕过（授权已在上层完成）
                $project = RndProject::withoutGlobalScopes()->find($task->project_id);
                if ($project) {
                    $this->autoCloseIfComplete($project);
                }
            }
            return $doc;
        });
    }

    /**
     * 每天标记逾期任务（Console 命令调用）
     */
    public function markOverdue(): int
    {
        $count = 0;
        RndProjectTask::whereDate('end_date', '<', now())
            ->whereNotIn('status', ['approved', 'completed', 'cancelled'])
            ->each(function (RndProjectTask $t) use (&$count) {
                $t->update(['is_overdue' => true, 'status' => 'overdue']);
                $count++;
            });
        return $count;
    }

    /**
     * P1-01：延期申请审批通过后回写任务（end_date 顺延、is_overdue 复位、累计 extension_days）。
     * 幂等：已 approved 的申请重复调用不重复顺延。
     */
    public function approveExtension(int $applicationId): \App\Models\Rnd\RndExtensionApplication
    {
        return DB::transaction(function () use ($applicationId) {
            $app = \App\Models\Rnd\RndExtensionApplication::findOrFail($applicationId);
            if ($app->status === 'approved') {
                return $app;
            }
            $task = RndProjectTask::withoutGlobalScopes()->find($app->task_id);
            if (! $task) {
                throw new \DomainException('延期申请对应的研发任务不存在');
            }

            $base = $task->end_date ? \Carbon\Carbon::parse($task->end_date) : now();
            if ($base->isPast()) {
                $base = now(); // 已逾期的任务从今天起顺延，避免"补过去的天数"
            }

            $task->update([
                'end_date' => $base->copy()->addDays((int) $app->apply_days)->toDateString(),
                'is_overdue' => false,
                'status' => in_array($task->status, ['overdue'], true) ? 'in_progress' : $task->status,
                'extension_days' => (int) $task->extension_days + (int) $app->apply_days,
            ]);

            $app->update([
                'status' => 'approved',
                'approver_id' => Auth::id(),
                'approved_at' => now(),
            ]);

            Log::info('研发任务延期已批准', ['application_id' => $applicationId, 'task_id' => $task->id, 'days' => $app->apply_days]);

            return $app;
        });
    }

    /* ==================== P1-05 阶段门 ==================== */

    /**
     * 阶段推进：立项→设计→试产→量产，只允许逐级前进。
     * 闸门：进试产必须有已审核通过的基线图档；进量产不得还有待审批 ECR。
     */
    public function setStage(int $projectId, string $targetStage): RndProject
    {
        if (! array_key_exists($targetStage, RndProject::STAGES)) {
            throw new \InvalidArgumentException("未知研发阶段：{$targetStage}");
        }

        return DB::transaction(function () use ($projectId, $targetStage) {
            $project = RndProject::withoutGlobalScopes()->findOrFail($projectId);
            $current = $project->stageIndex();
            $target = (int) array_search($targetStage, array_keys(RndProject::STAGES), true);

            if ($target === $current) {
                return $project;
            }
            if ($target < $current) {
                throw new \DomainException('研发阶段不允许回退（当前' . $project->stageLabel() . '）');
            }
            if ($target > $current + 1) {
                throw new \DomainException('研发阶段必须逐级推进（当前' . $project->stageLabel() . '）');
            }

            if ($targetStage === 'trial') {
                $baseline = RndProjectDocument::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('is_baseline', true)
                    ->where('audit_status', 'approved')
                    ->first();
                if (! $baseline) {
                    throw new \DomainException('无基线图档不得进试产：请先审核通过并设定基线图纸/图档');
                }
            }

            if ($targetStage === 'mass_production') {
                $pendingEcr = EcrChangeOrder::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('status', EcrChangeOrder::STATUS_PENDING)
                    ->exists();
                if ($pendingEcr) {
                    throw new \DomainException('还有待审批的变更单（ECR），不得进量产');
                }
            }

            $project->update(['stage' => $targetStage, 'stage_changed_at' => now()]);
            Log::info('研发阶段推进', ['project_id' => $project->id, 'from' => $current, 'to' => $targetStage]);

            return $project;
        });
    }

    /**
     * 设定基线图档：同一项目只保留 1 个基线，旧基线标 superseded_by。
     * 只有审核通过（approved）的图档才能成为基线。
     */
    public function setBaseline(int $documentId): RndProjectDocument
    {
        return DB::transaction(function () use ($documentId) {
            $doc = RndProjectDocument::withoutGlobalScopes()->findOrFail($documentId);
            if ($doc->audit_status !== 'approved') {
                throw new \DomainException('只有审核通过的图档才能设为基线');
            }

            // 旧基线让位：标记被本版替代
            RndProjectDocument::withoutGlobalScopes()
                ->where('project_id', $doc->project_id)
                ->where('is_baseline', true)
                ->where('id', '<>', $doc->id)
                ->get()
                ->each(function (RndProjectDocument $old) use ($doc) {
                    $old->update(['is_baseline' => false, 'superseded_by' => $doc->id]);
                });

            $doc->update(['is_baseline' => true, 'baseline_at' => now()]);
            Log::info('研发基线已设定', ['project_id' => $doc->project_id, 'document_id' => $doc->id]);

            return $doc;
        });
    }

    /* ==================== P1-05 简版 ECR/ECO ==================== */

    public function createEcr(array $data): EcrChangeOrder
    {
        return DB::transaction(function () use ($data) {
            $seq = EcrChangeOrder::withoutGlobalScopes()->whereDate('created_at', today())->count() + 1;
            $changeNo = 'ECR-' . now()->format('Ymd') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            $materialIds = array_values(array_map('intval', $data['impacted_material_ids'] ?? []));
            $replacementIds = array_values(array_map('intval', $data['replacement_material_ids'] ?? []));
            $impact = app(\App\Support\EcrImpactService::class)->analyze((int) $data['bom_id'], array_merge($materialIds, $replacementIds));

            $ecr = EcrChangeOrder::create([
                'change_no' => $changeNo,
                'project_id' => (int) $data['project_id'],
                'bom_id' => (int) $data['bom_id'],
                'change_type' => $data['change_type'] ?? 'bom',
                'priority' => $data['priority'] ?? 'normal',
                'effective_date' => $data['effective_date'] ?? null,
                'title' => $data['title'],
                'scope_description' => $data['scope_description'] ?? null,
                'impacted_material_ids' => $materialIds,
                'replacement_material_ids' => $replacementIds,
                'impact_analysis' => $impact,
                'reason' => $data['reason'],
                'applicant_id' => Auth::id() ?? 0,
                'status' => EcrChangeOrder::STATUS_PENDING,
            ]);

            // P1-01：挂研发经理审批；通过后由 applyEcr 生成新 BOM 版本
            app(ApprovalService::class)->start('ecr.change', $ecr->id);

            return $ecr;
        });
    }

    /**
     * ECR 批准：复制受影响 BOM 为一个新版本（旧版本 superseded），变更单记录 new_bom_id。
     * 幂等：已批准且已生成新 BOM 的直接返回。
     */
    public function approveEcr(int $ecrId, ?int $approverId = null): EcrChangeOrder
    {
        return DB::transaction(function () use ($ecrId, $approverId) {
            $ecr = EcrChangeOrder::withoutGlobalScopes()->findOrFail($ecrId);
            if ($ecr->status === EcrChangeOrder::STATUS_APPROVED && $ecr->new_bom_id) {
                return $ecr;
            }

            // P0-08：变更单申请人≠审批人，取 BOM 也要绕过行级范围
            $old = \App\Models\Bom\BomHeader::withoutGlobalScopes()->with('lines')->find($ecr->bom_id);
            if (! $old) {
                throw new \DomainException('变更单关联的 BOM 不存在');
            }

            $nextVersion = 'V' . ((int) preg_replace('/[^0-9]/', '', (string) $old->version) + 1);
            // bom_code 唯一（uk_bom_code），新版本用 "<原编码>-V2" 区分版本
            $newCode = substr($old->bom_code . '-' . $nextVersion, 0, 32);
            $effectiveDate = $ecr->effective_date?->toDateString() ?? now()->toDateString();
            $ecoSeq = EcrChangeOrder::withoutGlobalScopes()->whereDate('eco_executed_at', today())->count() + 1;
            $ecoNo = 'ECO-' . now()->format('Ymd') . '-' . str_pad((string) $ecoSeq, 3, '0', STR_PAD_LEFT);
            $new = \App\Models\Bom\BomHeader::create([
                'bom_code' => $newCode,
                'product_material_id' => $old->product_material_id,
                'version' => $nextVersion,
                'status' => 'active',
                'effective_date' => $effectiveDate,
                'remark' => "ECR {$ecr->change_no}：{$ecr->title}",
                'created_by' => Auth::id() ?? (int) $ecr->applicant_id,
                'approved_by' => $approverId ?? Auth::id(),
                'approved_at' => now(),
            ]);
            $replaceMap = [];
            $impactIds = (array) ($ecr->impacted_material_ids ?? []);
            $replacementIds = (array) ($ecr->replacement_material_ids ?? []);
            if ($impactIds && count($impactIds) === count($replacementIds)) {
                $replaceMap = array_combine($impactIds, $replacementIds);
            }
            foreach ($old->lines as $line) {
                $newMaterialId = $replaceMap[$line->material_id] ?? $line->material_id;
                \App\Models\Bom\BomLine::create([
                    'bom_header_id' => $new->id,
                    'parent_material_id' => $line->parent_material_id,
                    'material_id' => $newMaterialId,
                    'qty_per' => $line->qty_per,
                    'unit' => $line->unit,
                    'loss_rate' => $line->loss_rate,
                    'sort_order' => $line->sort_order,
                    'remark' => $newMaterialId !== $line->material_id ? 'ECR 替代料：' . $line->material_id . '→' . $newMaterialId : 'ECR 版本复制',
                ]);
            }
            $old->update(['status' => 'superseded']);

            $ecr->update([
                'status' => EcrChangeOrder::STATUS_APPROVED,
                'approver_id' => $approverId ?? Auth::id(),
                'approved_at' => now(),
                'new_bom_id' => $new->id,
                'eco_no' => $ecoNo,
                'eco_executed_at' => now(),
            ]);
            Log::info('ECR 已批准并生成新 BOM 版本', ['ecr' => $ecr->change_no, 'eco' => $ecoNo, 'new_bom_id' => $new->id]);

            return $ecr;
        });
    }

    /* ==================== P1-05 我的任务：开始/提交/通过 ==================== */

    public function startTask(int $taskId): RndProjectTask
    {
        $task = RndProjectTask::withoutGlobalScopes()->findOrFail($taskId);
        if (! in_array($task->status, ['pending', 'overdue', 'rejected'], true)) {
            throw new \DomainException("任务状态 {$task->status} 不能开始");
        }
        $task->update([
            'status' => 'in_progress',
            'start_date' => $task->start_date ?? now()->toDateString(),
            'is_overdue' => false,
        ]);

        return $task;
    }

    public function submitTask(int $taskId): RndProjectTask
    {
        $task = RndProjectTask::withoutGlobalScopes()->with('documents')->findOrFail($taskId);
        if (! in_array($task->status, ['pending', 'in_progress', 'overdue', 'rejected'], true)) {
            throw new \DomainException("任务状态 {$task->status} 不能提交");
        }
        if ($task->documents->isEmpty()) {
            throw new \DomainException('尚未上传任何交付物，不能提交审核');
        }
        $task->update(['status' => 'submitted']);

        return $task;
    }

    /**
     * 任务通过/驳回（审核人、项目创建人或研发经理）。
     */
    public function decideTask(int $taskId, bool $pass, ?string $remark = null): RndProjectTask
    {
        return DB::transaction(function () use ($taskId, $pass, $remark) {
            $task = RndProjectTask::withoutGlobalScopes()->findOrFail($taskId);
            if (! in_array($task->status, ['submitted', 'in_progress'], true)) {
                throw new \DomainException("任务状态 {$task->status} 不能审核");
            }
            $task->update([
                'status' => $pass ? 'approved' : 'rejected',
                'is_overdue' => false,
            ]);
            Log::info('研发任务审核', ['task_id' => $taskId, 'pass' => $pass, 'remark' => $remark]);

            $this->recalcProgress((int) $task->project_id);
            $project = RndProject::withoutGlobalScopes()->find($task->project_id);
            if ($project) {
                $this->autoCloseIfComplete($project);
            }

            return $task;
        });
    }

    private function recalcProgress(int $projectId): void
    {
        $project = RndProject::withoutGlobalScopes()->findOrFail($projectId);
        $total = $project->tasks()->count();
        if ($total === 0) {
            return;
        }
        $done = $project->tasks()->where('status', 'approved')->count();
        $project->update(['progress' => (int) round($done / $total * 100)]);
    }

    private function autoCloseIfComplete(RndProject $project): void
    {
        $total = $project->tasks()->count();
        $done = $project->tasks()->where('status', 'approved')->count();
        if ($total > 0 && $done === $total && $project->status === 'in_progress') {
            $project->update(['status' => 'completed', 'progress' => 100]);
        }
    }

    /**
     * 任务审核人只能是任务所属部门的部门经理，且不能是执行人本人。
     * 这是服务层最后一道防线（页面下拉、控制器校验、服务层校验三处一致）。
     */
    private function assertAuditorIsDepartmentManager(int $deptId, mixed $auditorId, mixed $executorId, string $taskName): void
    {
        $manager = $deptId ? Department::find($deptId)?->resolvedManager() : null;

        if (! $manager) {
            throw new \DomainException("任务「{$taskName}」所属部门未配置部门经理，无法指定审核人");
        }
        if ((int) $auditorId !== (int) $manager->id) {
            throw new \DomainException(
                "任务「{$taskName}」的审核人必须是本部门经理（{$manager->identity_label}）"
            );
        }
        if ((int) $auditorId === (int) $executorId) {
            throw new \DomainException("任务「{$taskName}」的审核人不能是执行人本人");
        }
    }
}
