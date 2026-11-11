<?php

namespace App\Services;

use App\Models\Organization\User;
use App\Models\Security\OperationLog;
use App\Support\TenancyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 普通 CRUD 操作流水。
 *
 * 设计原则：
 *  - 只记录“有登录用户”的写操作，保证每条流水都能追到个人；
 *  - 通过 Eloquent 全局事件捕获新增/修改/删除，业务代码无需逐个埋点；
 *  - 记录操作人 ID + 姓名快照（避免用户改名后历史失真）；
 *  - 密码、令牌、密钥、银行账号、身份证、薪资等字段一律脱敏；
 *  - 日志表自身、登录日志、安全事件、队列/会话等基础表不重复记录。
 */
class OperationLogger
{
    /** 不写入操作流水的技术表（避免自记录、重复记录和高频噪声）。 */
    private const EXCLUDED_TABLES = [
        'operation_logs',
        'audit_logs',
        'login_logs',
        'login_failures',
        'security_events',
        'sensitive_access_logs',
        'authorization_logs',
        'authorization_failures',
        'account_lockouts',
        'audit_approval_flows',
        'audit_export_logs',
        'audit_order_statuses',
        'audit_product_changes',
        'audit_reconciliation_diffs',
        'order_status_logs',
        'activity_log',
        'personal_access_tokens',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'notifications',
        'outbox_events',
        'sync_logs',
    ];

    /** 敏感字段：只留“已脱敏”，不落明文/哈希。 */
    private const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'api_token',
        'secret',
        'token',
        'credential',
        'bank_account',
        'bank_no',
        'id_card',
        'id_number',
        'salary',
        'private_key',
    ];

    private const MAX_FIELD_VALUE_LENGTH = 500;

    private const MAX_CHANGED_FIELDS = 80;

    private static bool $tableReady = false;

    /**
     * Eloquent 全局事件入口（由 AppServiceProvider 注册）。
     */
    public function handle(string $eventName, mixed $model): void
    {
        try {
            $this->capture($eventName, $model);
        } catch (\Throwable $e) {
            // 审计失败绝不阻断业务写入。
            Log::warning('operation_logs 捕获失败', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function capture(string $eventName, mixed $model): void
    {
        if (! $model instanceof Model) {
            return;
        }

        if (! preg_match('/^eloquent\.(created|updated|deleted):\s*(.+)$/', $eventName, $matches)) {
            return;
        }

        $action = $matches[1];

        if ($model instanceof OperationLog) {
            return;
        }

        if (in_array($model->getTable(), self::EXCLUDED_TABLES, true)) {
            return;
        }

        // 无登录用户 = 系统/命令行动作，不产生“个人”流水。
        if (! Auth::hasUser()) {
            return;
        }

        $changes = $this->buildChanges($model, $action);

        // 修改但没有任何有效字段变化（例如只动了 updated_at）时不留空日志。
        if ($action === 'updated' && empty($changes['after'])) {
            return;
        }

        $this->record(
            $action,
            $model->getTable(),
            (string) $model->getKey(),
            $this->targetLabel($model),
            $changes,
            Auth::user(),
            $model->getAttribute('tenant_id')
        );
    }

    /**
     * 显式记录一条操作流水；供无法通过 Eloquent 事件覆盖的写操作调用。
     */
    public function record(
        string $action,
        string $targetType,
        ?string $targetId = null,
        ?string $targetLabel = null,
        array $changes = [],
        ?User $actor = null,
        mixed $tenantId = null
    ): ?OperationLog {
        $actor ??= Auth::user();

        if (! $actor instanceof User) {
            return null;
        }

        if (! $this->tableReady()) {
            return null;
        }

        try {
            $request = $this->currentRequest();

            return OperationLog::create([
                'tenant_id' => $tenantId ?: app(TenancyContext::class)->id(),
                'user_id' => $actor->getKey(),
                'actor_name' => $actor->identity_label,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_label' => $targetLabel !== null ? Str::limit($targetLabel, 255, '') : null,
                'changes' => $changes ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent() !== null
                    ? Str::limit($request->userAgent(), 255, '')
                    : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // 审计失败不阻断业务，只落框架日志。
            Log::warning('operation_logs 写入失败', [
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function tableReady(): bool
    {
        if (self::$tableReady) {
            return true;
        }

        return self::$tableReady = Schema::hasTable('operation_logs');
    }

    private function currentRequest(): ?\Illuminate\Http\Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        try {
            return request();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 生成 before/after；只保留实际变化字段。
     */
    private function buildChanges(Model $model, string $action): array
    {
        $changes = [];

        if ($action === 'updated') {
            foreach ($model->getChanges() as $key => $value) {
                if (in_array($key, ['updated_at', 'created_at', 'deleted_at'], true)) {
                    continue;
                }

                $changes['before'][$key] = $this->safeValue($key, $model->getOriginal($key));
                $changes['after'][$key] = $this->safeValue($key, $value);
            }
        } else {
            foreach ($model->getAttributes() as $key => $value) {
                if (in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }

                $bucket = $action === 'deleted' ? 'before' : 'after';
                $changes[$bucket][$key] = $this->safeValue($key, $value);
            }
        }

        foreach (['before', 'after'] as $bucket) {
            if (isset($changes[$bucket]) && count($changes[$bucket]) > self::MAX_CHANGED_FIELDS) {
                $changes[$bucket] = array_slice($changes[$bucket], 0, self::MAX_CHANGED_FIELDS, true);
                $changes[$bucket]['__truncated__'] = true;
            }
        }

        return $changes;
    }

    private function safeValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitive($key)) {
            return '[已脱敏]';
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::MAX_FIELD_VALUE_LENGTH, '…');
        }

        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return Str::limit((string) $value, self::MAX_FIELD_VALUE_LENGTH, '…');
        }

        if (is_string($value)) {
            if (! mb_check_encoding($value, 'UTF-8')) {
                return '[二进制数据]';
            }

            return Str::limit($value, self::MAX_FIELD_VALUE_LENGTH, '…');
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取一个人类可读的对象标识，优先名称/单号/编码。
     */
    private function targetLabel(Model $model): ?string
    {
        $candidates = [
            'name', 'title', 'subject', 'project_name', 'task_name', 'doc_name',
            'material_name', 'supplier_name', 'customer_name', 'company_name', 'dept_name', 'pos_name',
            'username', 'code', 'adjustment_no', 'project_no', 'order_no', 'req_no',
            'quote_no', 'invoice_no', 'bill_no', 'contract_no', 'room_no',
            'file_name', 'original_name',
        ];

        foreach ($candidates as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return Str::limit((string) $value, 255, '');
            }
        }

        foreach ($model->getAttributes() as $key => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            if (str_ends_with($key, '_no') || str_ends_with($key, '_code') || str_ends_with($key, '_number')) {
                return Str::limit((string) $value, 255, '');
            }
        }

        return class_basename($model) . '#' . $model->getKey();
    }
}
