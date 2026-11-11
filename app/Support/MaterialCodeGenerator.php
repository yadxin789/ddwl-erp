<?php

namespace App\Support;

use App\Models\System\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * 物料编码自动生成器
 * 规则：2位分类码 + 6位流水号，零填充；不使用 O/I/S/Z 字符
 * 通过 number_sequences 表取流水号，加行锁（SELECT ... FOR UPDATE）防并发重复
 * 新物料建档时自动调用，编码永不修改；废弃物料置 status=inactive，不删除记录
 */
class MaterialCodeGenerator
{
    /**
     * 不允许出现在流水号中的字符（避免与数字混淆）
     */
    private const EXCLUDED_CHARS = ['O', 'I', 'S', 'Z'];

    /**
     * 生成物料编码
     *
     * @param string $categoryCode 物料大类编码（2位，如 EL/ST/PB）
     */
    public static function generate(string $categoryCode): string
    {
        $categoryCode = strtoupper(trim($categoryCode));
        if (strlen($categoryCode) !== 2) {
            throw new \InvalidArgumentException("物料大类编码必须是2位字符: {$categoryCode}");
        }

        // 在事务中行锁取号，防止并发重复；每种分类独立计数
        return DB::transaction(function () use ($categoryCode) {
            /** @var NumberSequence $seq */
            $seq = NumberSequence::where('rule_code', 'material_code:' . $categoryCode)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                throw new \RuntimeException('编号规则未初始化或已停用: material_code:' . $categoryCode . '，请检查基础数据 → 单据编号规则');
            }

            $next = $seq->current_seq + 1;

            // 跳过含禁用字符的流水号
            while (self::containsExcludedChar($next)) {
                $next++;
            }

            $seq->update(['current_seq' => $next]);

            return $categoryCode . str_pad((string) $next, $seq->seq_length, '0', STR_PAD_LEFT);
        });
    }

    /**
     * 判断数字的字符串表示是否包含禁用字符
     */
    private static function containsExcludedChar(int $number): bool
    {
        $digits = str_split((string) $number);

        foreach ($digits as $digit) {
            if (in_array($digit, self::EXCLUDED_CHARS, true)) {
                return true;
            }
        }

        return false;
    }
}
