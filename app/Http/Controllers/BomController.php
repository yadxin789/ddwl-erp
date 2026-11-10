<?php

namespace App\Http\Controllers;

use App\Models\Bom\BomHeader;
use App\Models\MasterData\Material;
use Illuminate\Http\Request;

class BomController extends Controller
{
    public function index(Request $request)
    {
        $query = BomHeader::query()->with(['product']);

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('bom_code', 'like', "%{$kw}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$kw}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $boms = $query->withCount('lines')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('bom.index', compact('boms'));
    }

    /**
     * P0-06：BOM 待审批台（无参入口，替代原指向 bom.show 的带参映射）
     */
    public function pending()
    {
        $boms = BomHeader::with('product')
            ->withCount('lines')
            ->where('status', 'draft')
            ->orderByDesc('id')
            ->paginate(20);

        return view('bom.pending', compact('boms'));
    }

    public function show(BomHeader $bom)
    {
        $bom->load(['product', 'lines.material']);

        return view('bom.show', compact('bom'));
    }
}
