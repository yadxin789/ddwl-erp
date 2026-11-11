<?php

namespace App\Support;

use App\Models\System\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * 通用编号生成器：所有业务单据（SO/PO/WO/GR/IN 等）统一调用
 * 输出格式：前缀 + 日期 + 流水号，如 SO20261103001
 * 加数据库行锁（SELECT ... FOR UPDATE）防并发跳号
 */
class NumberGenerator
{
    /**
     * 生成业务单号
     *
     * @param string $ruleCode 编号规则编码：sales_order/purchase_order/work_order/goods_receipt/shipment/inventory_adjust/bom_code/bom_import
     */
    public static function generate(string $ruleCode): string
    {
        return DB::transaction(function () use ($ruleCode) {
            /** @var NumberSequence $seq */
            $seq = NumberSequence::where('rule_code', $ruleCode)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                throw new \RuntimeException("编号规则未初始化或已停用: {$ruleCode}，请检查基础数据 → 单据编号规则");
            }

            $next = $seq->current_seq + 1;
            $seq->update(['current_seq' => $next]);

            $serial = str_pad((string) $next, $seq->seq_length, '0', STR_PAD_LEFT);

            // 支持 BOM 编号这类无日期前缀规则：BOM000001
            if ($seq->date_format) {
                return $seq->prefix . now()->format($seq->date_format) . $serial;
            }

            return $seq->prefix . $serial;
        });
    }
}
