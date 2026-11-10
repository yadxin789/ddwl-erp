<?php

namespace App\Services;

use App\Enums\BomImportStatus;
use App\Enums\BomMatchStatus;
use App\Enums\BomStatus;
use App\Models\Bom\BomHeader;
use App\Models\Bom\BomImport;
use App\Models\Bom\BomImportRow;
use App\Models\Bom\BomLine;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialMpn;
use App\Support\MaterialCodeGenerator;
use App\Support\NumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * BOM Excel 导入服务
 * 流程：上传 → 解析（列映射）→ MPN 三态匹配 → 预览 → 确认（写 draft BOM）→ 审核（approved）
 * MPN 匹配规则：
 *   - 精确 1 条 → matched（自动带出内部料号）
 *   - 匹配多条 → multiple（候选人工选择）
 *   - 匹配 0 条 → unmatched → 自动生成新料号申请（不允许跳过）
 */
class BomImportService
{
    /**
     * 解析 Excel：读取 + MPN 匹配 + 落库导入任务与临时行
     *
     * @param UploadedFile $file Excel 文件
     * @param int $productMaterialId 目标成品料号 ID
     * @param array $columnMap 列映射 ['mpn' => '列名', 'qty' => ..., 'name' => ..., 'spec' => ..., 'ref_des' => ..., 'manufacturer' => ...]
     */
    public function parseExcel(UploadedFile $file, int $productMaterialId, array $columnMap): BomImport
    {
        Log::info('BOM Excel 导入解析', ['user_id' => Auth::id(), 'product_material_id' => $productMaterialId, 'file' => $file->getClientOriginalName()]);
        // 校验目标成品
        $product = Material::findOrFail($productMaterialId);

        // 文件扩展名校验
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
            throw new \InvalidArgumentException('仅支持 xls/xlsx/csv 格式的 BOM 文件');
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('文件不能超过 20MB');
        }

        // 读取 Excel 全部数据（首行为表头）
        $rows = Excel::toArray(new \stdClass(), $file)[0] ?? [];
        if (count($rows) < 2) {
            throw new \InvalidArgumentException('Excel 文件没有数据行');
        }

        // 表头 → 列索引映射
        $header = array_map(fn ($v) => trim((string) $v), $rows[0]);
        $colIdx = [];
        foreach ($columnMap as $field => $colName) {
            if (! $colName) {
                continue;
            }
            $found = array_search(trim($colName), $header, true);
            if ($found === false) {
                throw new \InvalidArgumentException("列映射失败：找不到列 {$colName}");
            }
            $colIdx[$field] = $found;
        }

        // 创建导入任务
        $import = DB::transaction(function () use ($file, $product, $rows, $colIdx) {
            $import = BomImport::create([
                'import_no' => NumberGenerator::generate('bom_import'),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => 'uploads/bom/' . now()->format('YmdHis') . '_' . $file->getClientOriginalName(),
                'product_material_id' => $product->id,
                'total_rows' => count($rows) - 1,
                'status' => BomImportStatus::Uploaded->value,
                'created_by' => Auth::id() ?? 0,
            ]);

            $success = 0;
            $error = 0;

            foreach ($rows as $idx => $row) {
                if ($idx === 0) {
                    continue; // 跳过表头
                }
                $row = array_values($row);

                $rawMpn = $this->cell($row, $colIdx, 'mpn');
                $rawName = $this->cell($row, $colIdx, 'name');
                $rawSpec = $this->cell($row, $colIdx, 'spec');
                $rawQty = $this->cell($row, $colIdx, 'qty');
                $rawRefDes = $this->cell($row, $colIdx, 'ref_des');
                $rawManufacturer = $this->cell($row, $colIdx, 'manufacturer');

                // MPN 三态匹配
                [$matchStatus, $matchedMaterialId, $message] = $this->matchMpn($rawMpn, $rawManufacturer);

                $action = ($matchStatus === BomMatchStatus::Matched->value) ? 'import' : 'new_material';
                if ($matchStatus === BomMatchStatus::Matched->value) {
                    $success++;
                } else {
                    $error++;
                }

                BomImportRow::create([
                    'import_id' => $import->id,
                    'row_no' => $idx + 1,
                    'raw_mpn' => $rawMpn,
                    'raw_name' => $rawName,
                    'raw_spec' => $rawSpec,
                    'raw_qty' => $rawQty,
                    'raw_ref_des' => $rawRefDes,
                    'raw_manufacturer' => $rawManufacturer,
                    'matched_material_id' => $matchedMaterialId,
                    'match_status' => $matchStatus,
                    'action' => $action,
                    'error_message' => $message,
                ]);
            }

            $import->update([
                'success_rows' => $success,
                'error_rows' => $error,
                'status' => BomImportStatus::Parsed->value,
            ]);

            return $import;
        });

        return $import;
    }

    /**
     * MPN 匹配：精确 1 条 matched；多条 multiple；0 条 unmatched
     */
    private function matchMpn(?string $mpn, ?string $manufacturer): array
    {
        if (! $mpn) {
            return [BomMatchStatus::Unmatched->value, null, '缺少 MPN'];
        }

        $query = MaterialMpn::query()->whereRaw('LOWER(mpn) = ?', [mb_strtolower(trim($mpn))]);
        if ($manufacturer) {
            $query->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower(trim($manufacturer))]);
        }

        $matches = $query->get();

        return match (true) {
            $matches->count() === 1 => [BomMatchStatus::Matched->value, $matches->first()->material_id, null],
            $matches->count() > 1 => [BomMatchStatus::Multiple->value, null, '匹配到多个物料，请人工选择'],
            default => [BomMatchStatus::Unmatched->value, null, '无匹配 MPN，需申请新料号'],
        };
    }

    /**
     * 确认导入：所有行必须为 import 动作（matched），写 bom_headers(draft) + bom_lines
     * 存在 unmatched/multiple 行时阻塞，提示先处理
     */
    public function confirm(int $importId): BomHeader
    {
        Log::info('确认 BOM 导入', ['user_id' => Auth::id(), 'import_id' => $importId]);
        return DB::transaction(function () use ($importId) {
            $import = BomImport::with('rows')->findOrFail($importId);

            if (! in_array($import->status, [BomImportStatus::Parsed->value, BomImportStatus::Imported->value], true)) {
                throw new \DomainException("当前导入状态 {$import->status} 不允许确认");
            }

            // 校验未匹配行
            $unresolved = $import->rows->whereIn('action', ['new_material'])->whereNull('new_material_request_id');
            if ($unresolved->isNotEmpty()) {
                throw new \DomainException("存在 {$unresolved->count()} 行未匹配（新料号未申请完成），不允许确认导入");
            }

            $product = Material::findOrFail($import->product_material_id);

            // 生成 BOM 编号：BOM-{产品料号}-V1.0（唯一）
            $bomCode = 'BOM-' . $product->material_code . '-V1.0';
            if (BomHeader::where('bom_code', $bomCode)->exists()) {
                throw new \DomainException("该产品的 BOM 已存在：{$bomCode}，请走版本升级流程");
            }

            $bom = BomHeader::create([
                'bom_code' => $bomCode,
                'product_material_id' => $product->id,
                'version' => 'V1.0',
                'status' => BomStatus::Draft->value,
                'created_by' => Auth::id() ?? 0,
            ]);

            $sort = 0;
            foreach ($import->rows as $row) {
                if ($row->action !== 'import' || ! $row->matched_material_id) {
                    continue;
                }
                BomLine::create([
                    'bom_header_id' => $bom->id,
                    'parent_material_id' => $product->id,
                    'material_id' => $row->matched_material_id,
                    'qty_per' => (float) ($row->raw_qty ?: 1),
                    'unit' => 'PCS',
                    'ref_des' => $row->raw_ref_des,
                    'loss_rate' => 0,
                    'is_alternative' => 0,
                    'sort_order' => $sort,
                ]);
                $sort++;
            }

            $import->update([
                'status' => BomImportStatus::Imported->value,
                'success_rows' => $sort,
                'error_rows' => $import->error_rows,
            ]);

            return $bom;
        });
    }

    /**
     * BOM 审核：draft → approved；已审核不可改
     */
    public function approve(int $bomId): BomHeader
    {
        Log::info('BOM 审核通过', ['user_id' => Auth::id(), 'bom_id' => $bomId]);
        return DB::transaction(function () use ($bomId) {
            $bom = BomHeader::findOrFail($bomId);

            if ($bom->status !== BomStatus::Draft->value) {
                throw new \DomainException("BOM 状态为 {$bom->status}，只有 draft 状态可以审核");
            }

            $bom->update([
                'status' => BomStatus::Approved->value,
                'approved_by' => Auth::id() ?? 0,
                'approved_at' => now(),
            ]);

            return $bom;
        });
    }

    /**
     * 为未匹配行生成新料号申请（status=0 待审核的物料）
     */
    public function createNewMaterialRequest(int $rowId, ?string $categoryCode = null): BomImportRow
    {
        Log::info('生成新料号申请', ['user_id' => Auth::id(), 'row_id' => $rowId, 'category_code' => $categoryCode]);
        return DB::transaction(function () use ($rowId, $categoryCode) {
            $row = BomImportRow::findOrFail($rowId);

            if ($row->match_status !== BomMatchStatus::Unmatched->value) {
                throw new \DomainException("该行匹配状态为 {$row->match_status}，无需申请新料号");
            }
            if ($row->new_material_request_id) {
                return $row;
            }

            $categoryCode = $categoryCode ?? 'EL'; // 默认电子元器件，可按需调整
            $material = Material::create([
                'material_code' => MaterialCodeGenerator::generate($categoryCode),
                'category_code' => $categoryCode,
                'name' => $row->raw_name ?: ($row->raw_mpn ?: '待确认物料'),
                'spec' => $row->raw_spec ?: ($row->raw_mpn ?: ''),
                'unit' => 'PCS',
                'status' => 0, // 待审核
            ]);

            // 若申请单里有 MPN，直接建 MPN 关联
            if ($row->raw_mpn) {
                MaterialMpn::create([
                    'material_id' => $material->id,
                    'mpn' => $row->raw_mpn,
                    'manufacturer' => $row->raw_manufacturer,
                    'is_primary' => 1,
                    'is_alternative' => 0,
                    'approved_by' => 'pending',
                ]);
            }

            $row->update([
                'matched_material_id' => $material->id,
                'action' => 'import',
                'match_status' => BomMatchStatus::Manual->value,
                'new_material_request_id' => $material->id,
                'error_message' => "新料号申请已生成：{$material->material_code}（待审核）",
            ]);

            return $row;
        });
    }

    private function cell(array $row, array $colIdx, string $field): ?string
    {
        if (! isset($colIdx[$field])) {
            return null;
        }
        $v = $row[$colIdx[$field]] ?? null;

        return ($v === null) ? null : trim((string) $v);
    }
}
