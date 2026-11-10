<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bom\BomImportUploadRequest;
use App\Http\Requests\Bom\MaterialRequestRequest;
use App\Models\Bom\BomHeader;
use App\Models\Bom\BomImport;
use App\Models\Bom\BomImportRow;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialMpn;
use App\Services\BomImportService;
use Illuminate\Http\Request;

class BomImportController extends Controller
{
    public function __construct(
        protected BomImportService $service
    ) {
    }

    /**
     * GET /bom/import 上传页
     */
    public function showUpload()
    {
        $products = Material::whereIn('category_code', ['FG', 'SM'])->where('status', 1)->orderBy('material_code')->get();

        return view('bom.import', compact('products'));
    }

    /**
     * POST /bom/import 上传并解析
     */
    public function upload(BomImportUploadRequest $request)
    {
        $validated = $request->validated();

        try {
            $import = $this->service->parseExcel(
                $request->file('file'),
                (int) $validated['product_material_id'],
                [
                    'mpn' => $validated['mpn_column'],
                    'qty' => $validated['qty_column'],
                    'name' => $validated['name_column'] ?? null,
                    'spec' => $validated['spec_column'] ?? null,
                    'ref_des' => $validated['refdes_column'] ?? null,
                    'manufacturer' => $validated['manufacturer_column'] ?? null,
                ]
            );

            return redirect()->route('bom.import.preview', $import->id)
                ->with('success', "解析完成：共 {$import->total_rows} 行，匹配成功 {$import->success_rows} 行，需处理 {$import->error_rows} 行。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    /**
     * GET /bom/import/{import}/preview 预览（三色状态）
     */
    public function preview(BomImport $import)
    {
        $import->load(['rows.matchedMaterial', 'product']);

        return view('bom.preview', compact('import'));
    }

    /**
     * POST /bom/import/{row}/request-material 未匹配行 → 生成新料号申请
     */
    public function requestMaterial(MaterialRequestRequest $request, BomImportRow $row)
    {
        try {
            $this->service->createNewMaterialRequest($row->id, $request->validated()['category_code']);

            return back()->with('success', '新料号申请已生成，可继续确认导入。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['row' => $e->getMessage()]);
        }
    }

    /**
     * POST /bom/import/{import}/confirm 确认导入 → 写 draft BOM
     */
    public function confirm(BomImport $import)
    {
        try {
            $bom = $this->service->confirm($import->id);

            return redirect()->route('bom.show', $bom)->with('success', "BOM {$bom->bom_code} 已生成（draft），请审核。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }
    }

    /**
     * POST /bom/{bom}/approve BOM 审核
     */
    public function approve(BomHeader $bom)
    {
        try {
            $this->service->approve($bom->id);

            return back()->with('success', "BOM {$bom->bom_code} 已审核通过。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }
    }
}
