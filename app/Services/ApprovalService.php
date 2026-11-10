<?php

namespace App\Services;

use App\Models\Approval\ApprovalFlow;
use App\Models\Approval\ApprovalInstance;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Organization\User;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\CustomerReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 审批引擎（P1-01）
 *
 * 规则（§9.2 / D-21）：
 * - 申请人 ≠ 审批人：同一人兼任两岗时，第一步若由申请人本人审批 → 自动跳到下一级；
 *   若没有下一级且没有代批人 → 直接拒绝（不生成"自己批自己"的实例）。
 * - 代批：审批节点的 delegate_ids 里的人可以批，但必须写理由，日志与实例都标 delegated=true。
 * - 审批期间业务不执行：start 只挂实例，业务动作在最终通过后由 apply() 回调执行。
 * - 通过/驳回/代批写 audit_logs（audit_logs 表不存在时降级为日志文件，不阻断审批）。
 */
class ApprovalService
{
    /** 代批月度占比告警线（D-21） */
    public const DELEGATE_ALERT_RATIO = 0.30;

    public function __construct(protected NotificationService $notifier)
    {
    }

    /**
     * 发起审批。返回 null 表示该业务未配置审批流（视为无需审批）。
     *
     * @param  array<string, mixed>  $payload  业务动作上下文
     */
    public function start(string $businessType, int $businessId, ?int $applicantId = null, array $payload = []): ?ApprovalInstance
    {
        $flow = ApprovalFlow::where('business_type', $businessType)->where('status', true)->first();
        if (! $flow) {
            return null; // 无需审批的业务
        }

        $applicant = (int) ($applicantId ?? Auth::id() ?? 0);
        $steps = $flow->steps_json ?? [];
        if ($steps === []) {
            throw new \DomainException("审批流 {$businessType} 未配置审批节点");
        }

        // 申请人≠审批人：跳过"申请人本人审批"的步骤；但如果该步骤有可用的代批人，则保留（由代批人审）
        $stepNo = 1;
        while ($stepNo <= count($steps) && (int) ($steps[$stepNo - 1]['approver_id'] ?? 0) === $applicant) {
            $delegates = array_map('intval', $steps[$stepNo - 1]['delegate_ids'] ?? []);
            $usableDelegate = array_filter($delegates, fn (int $d) => $d !== $applicant);
            if ($usableDelegate !== []) {
                break; // 有代批人，保留本步骤
            }
            $stepNo++;
        }
        if ($stepNo > count($steps)) {
            throw new \DomainException('申请人不得审批自己提交的单据：该审批流没有可用的其他审批人');
        }


        return DB::transaction(function () use ($flow, $businessType, $businessId, $applicant, $payload, $steps, $stepNo) {
            $instance = ApprovalInstance::create([
                'flow_id' => $flow->id,
                'business_type' => $businessType,
                'business_id' => $businessId,
                'current_step' => $stepNo,
                'status' => 'pending',
                'applicant_id' => $applicant,
                'logs_json' => [],
                'payload_json' => $payload,
            ]);

            $this->notifyStep($instance, $steps[$stepNo - 1] ?? null);

            return $instance;
        });
    }

    /**
     * 审批决策。
     *
     * @param  int|null  $actorId  指定操作人（测试/服务内部调用用），默认当前登录用户
     */
    public function decide(int $instanceId, bool $approve, ?string $remark = null, ?int $actorId = null): ApprovalInstance
    {
        return DB::transaction(function () use ($instanceId, $approve, $remark, $actorId) {
            $instance = ApprovalInstance::findOrFail($instanceId);
            if ($instance->status !== 'pending') {
                throw new \DomainException('审批实例已处理');
            }
            $flow = ApprovalFlow::findOrFail($instance->flow_id);
            $steps = $flow->steps_json ?? [];
            $currentStep = $steps[$instance->current_step - 1] ?? null;
            if (! $currentStep) {
                throw new \DomainException('审批节点配置缺失，无法继续');
            }

            $actor = (int) ($actorId ?? Auth::id() ?? 0);
            $expectedApprover = (int) ($currentStep['approver_id'] ?? 0);
            $delegates = array_map('intval', $currentStep['delegate_ids'] ?? []);
            $isDelegate = $actor !== $expectedApprover && in_array($actor, $delegates, true);

            // ① 自批硬校验（申请人本人不许批自己的单据，代批也不行）
            if ($actor === (int) $instance->applicant_id) {
                Log::warning('审批自批被拒绝', ['instance_id' => $instanceId, 'user_id' => $actor]);
                throw new \DomainException('申请人不得审批自己提交的单据');
            }

            // ② 越权校验：只认本步骤指定审批人或其代批人
            if ($actor !== $expectedApprover && ! $isDelegate) {
                Log::warning('审批越权尝试被拒绝', [
                    'instance_id' => $instanceId,
                    'current_step' => $instance->current_step,
                    'expected_approver_id' => $expectedApprover,
                    'delegate_ids' => $delegates,
                    'actual_user_id' => $actor,
                ]);
                throw new \DomainException('您不是该步骤的指定审批人');
            }

            // ③ 代批必须写理由（D-21）
            if ($isDelegate && trim((string) $remark) === '') {
                throw new \InvalidArgumentException('代批必须填写理由');
            }

            $logs = $instance->logs_json ?? [];
            $logs[] = [
                'step' => $instance->current_step,
                'approver_id' => $expectedApprover,
                'decision' => $approve ? 'approved' : 'rejected',
                'actor_id' => $actor,
                'delegated' => $isDelegate,
                'remark' => $remark,
                'decided_at' => now()->toDateTimeString(),
            ];

            if (! $approve) {
                $instance->update([
                    'status' => 'rejected',
                    'logs_json' => $logs,
                    'delegated' => $isDelegate || $instance->delegated,
                ]);
                $this->audit($instance, $actor, 'approval.reject', $isDelegate, $remark);

                return $instance;
            }

            $isLast = $instance->current_step >= count($steps);
            if (! $isLast) {
                $nextStepNo = $instance->current_step + 1;
                // 下一级若还是申请人本人 → 继续跳过（避免自己批自己）
                while ($nextStepNo <= count($steps)
                    && (int) ($steps[$nextStepNo - 1]['approver_id'] ?? 0) === (int) $instance->applicant_id) {
                    $nextStepNo++;
                }
                if ($nextStepNo > count($steps)) {
                    $isLast = true; // 后面没有可用的审批人了，本步即终审
                }
            }

            if (! $isLast) {
                $instance->update([
                    'current_step' => $nextStepNo,
                    'logs_json' => $logs,
                    'delegated' => $isDelegate || $instance->delegated,
                ]);
                $this->notifyStep($instance, $steps[$nextStepNo - 1] ?? null);
                $this->audit($instance, $actor, 'approval.pass', $isDelegate, $remark);

                return $instance;
            }

            $instance->update([
                'status' => 'approved',
                'logs_json' => $logs,
                'delegated' => $isDelegate || $instance->delegated,
                'approved_at' => now(),
            ]);
            $this->audit($instance, $actor, 'approval.approve', $isDelegate, $remark);
            $this->apply($instance);

            if ($isDelegate) {
                $this->warnIfDelegateRatioTooHigh($actor);
            }

            Log::info('审批决策已记录', [
                'instance_id' => $instance->id,
                'business_type' => $instance->business_type,
                'business_id' => $instance->business_id,
                'decision' => 'approved',
                'delegated' => $isDelegate,
            ]);

            return $instance;
        });
    }

    /**
     * 业务动作是否已被审批放行（付款/开票/采购确认等"执行前闸门"用）。
     */
    public function isApproved(string $businessType, int $businessId): bool
    {
        return ApprovalInstance::where('business_type', $businessType)
            ->where('business_id', $businessId)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * 最终通过后执行对应业务动作。
     */
    protected function apply(ApprovalInstance $instance): void
    {
        $payload = $instance->payload_json ?? [];

        match ($instance->business_type) {
            'purchase_order.confirm' => $this->applyPurchaseOrder($instance, $payload),
            'invoice.issue' => $this->applyInvoice($instance),
            'payment.execute' => $this->applyPayment($instance),
            'rnd.extension' => $this->applyRndExtension($instance),
            'ecr.change' => $this->applyEcrChange($instance),
            'sales_return.approve' => $this->applySalesReturn($instance),
            'stocktake.adjust' => $this->applyStocktakeAdjust($instance),
            // stocktake.adjust 的库存变动由 InventoryAdjustmentService 负责（它自己幂等），此处不重复执行
            default => Log::info('审批通过，无业务回调', ['business_type' => $instance->business_type]),
        };
    }

    protected function applyPurchaseOrder(ApprovalInstance $instance, array $payload): void
    {
        $po = PurchaseOrder::find($instance->business_id);
        if (! $po) {
            throw new \DomainException('采购订单不存在，无法执行审批结果');
        }
        $service = app(PurchaseOrderService::class);
        if (($payload['action'] ?? 'confirm') === 'close') {
            $service->closeOrder($po->id);
        } else {
            $service->confirm($po->id);
        }
    }

    protected function applyInvoice(ApprovalInstance $instance): void
    {
        $invoice = CustomerInvoice::find($instance->business_id);
        if (! $invoice) {
            throw new \DomainException('发票不存在，无法执行审批结果');
        }
        if ($invoice->status === 'pending_approval') {
            $invoice->update(['status' => 'unpaid']);
        }
    }

    protected function applyPayment(ApprovalInstance $instance): void
    {
        $payment = Payment::find($instance->business_id);
        if (! $payment) {
            throw new \DomainException('收付款单不存在，无法执行审批结果');
        }
        $payment->update(['status' => 'approved']);
    }

    protected function applyRndExtension(ApprovalInstance $instance): void
    {
        app(RndProjectService::class)->approveExtension($instance->business_id);
    }

    /** P1-05：ECR 批准后才生成新 BOM 版本（旧版本 superseded） */
    protected function applyEcrChange(ApprovalInstance $instance): void
    {
        app(RndProjectService::class)->approveEcr(
            (int) $instance->business_id,
            (int) (($instance->logs_json[count($instance->logs_json) - 1]['actor_id'] ?? 0) ?: Auth::id())
        );
    }

    protected function applySalesReturn(ApprovalInstance $instance): void
    {
        app(SalesReturnService::class)->apply($instance->business_id);
    }

    protected function applyStocktakeAdjust(ApprovalInstance $instance): void
    {
        app(InventoryAdjustmentService::class)
            ->approve($instance->business_id, (int) ($instance->logs_json[count($instance->logs_json) - 1]['actor_id'] ?? 0));
    }

    protected function notifyStep(ApprovalInstance $instance, ?array $step): void
    {
        $approver = (int) ($step['approver_id'] ?? 0);
        $delegates = array_map('intval', $step['delegate_ids'] ?? []);
        foreach (array_filter(array_merge([$approver], $delegates)) as $uid) {
            if ($uid === (int) $instance->applicant_id) {
                continue; // 不通知申请人自己审自己
            }
            $this->notifier->addTodo(
                $uid,
                'approval',
                "待审批：{$instance->business_type} #{$instance->business_id}",
                null,
                $instance->business_type,
                $instance->business_id
            );
        }
    }

    /**
     * 写审计流水（表不存在时只落日志，不阻断业务）。
     */
    protected function audit(ApprovalInstance $instance, int $actorId, string $action, bool $delegated, ?string $remark): void
    {
        $detail = [
            'approval_instance_id' => $instance->id,
            'business_type' => $instance->business_type,
            'business_id' => $instance->business_id,
            'actor_id' => $actorId,
            'delegated' => $delegated,
            'remark' => $remark,
        ];
        Log::info("audit:{$action}", $detail);

        if (! \Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            return;
        }
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $actorId,
                'action' => $action,
                'target_type' => 'approval_instance',
                'target_id' => $instance->id,
                'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('audit_logs 写入失败（不影响审批）', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 代批月度占比 > 30% → 告警（写日志 + 审计），防止"代批变常态"（D-21）。
     */
    public function warnIfDelegateRatioTooHigh(int $actorId): ?float
    {
        $monthStart = now()->startOfMonth();
        $total = ApprovalInstance::where('status', 'approved')
            ->where('approved_at', '>=', $monthStart)
            ->count();
        if ($total === 0) {
            return null;
        }
        $delegated = ApprovalInstance::where('status', 'approved')
            ->where('approved_at', '>=', $monthStart)
            ->where('delegated', true)
            ->count();
        $ratio = $delegated / $total;

        if ($ratio > self::DELEGATE_ALERT_RATIO) {
            Log::warning('代批月度占比超阈值', [
                'month' => now()->format('Y-m'),
                'delegated' => $delegated,
                'total' => $total,
                'ratio' => round($ratio, 4),
                'actor_id' => $actorId,
            ]);
        }

        return $ratio;
    }

    /**
     * 当前用户待我审的实例（主审 + 代批）。
     */
    public function pendingForUser(int $userId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $candidates = ApprovalInstance::with('flow')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ApprovalInstance $i) => $this->canDecide($i, $userId));

        $page = (int) request()->input('page', 1);
        $perPage = 15;
        $items = $candidates->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $candidates->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * 该用户能否审批此实例（含代批）。
     */
    public function canDecide(ApprovalInstance $instance, int $userId): bool
    {
        if ($instance->status !== 'pending' || $userId === (int) $instance->applicant_id) {
            return false;
        }
        $steps = $instance->flow->steps_json ?? [];
        $step = $steps[$instance->current_step - 1] ?? [];
        if ((int) ($step['approver_id'] ?? 0) === $userId) {
            return true;
        }

        return in_array($userId, array_map('intval', $step['delegate_ids'] ?? []), true);
    }
}
