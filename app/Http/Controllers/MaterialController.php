<?php

namespace App\Http\Controllers;

use App\Http\Requests\Material\MaterialStoreRequest;
use App\Http\Requests\Material\MaterialUpdateRequest;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialCategory;
use App\Support\MaterialCodeGenerator;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::query()->with(['mpns', 'category']);

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('material_code', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%")
                    ->orWhere('spec', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('category_code')) {
            $query->where('category_code', $request->input('category_code'));
        }

        $materials = $query->orderBy('material_code')->paginate(20)->withQueryString();
        $categories = MaterialCategory::where('status', 1)->orderBy('sort_order')->get();

        return view('materials.index', compact('materials', 'categories'));
    }

    public function create()
    {
        $categories = MaterialCategory::where('status', 1)->orderBy('sort_order')->get();

        return view('materials.create', compact('categories'));
    }

    public function store(MaterialStoreRequest $request)
    {
        $validated = $request->validated();

        // 自动生成物料编码（永不修改）
        $validated['material_code'] = MaterialCodeGenerator::generate($validated['category_code']);
        $validated['rohs_status'] = $validated['rohs_status'] ?? 1;
        $validated['status'] = 1;

        Material::create($validated);

        return redirect()->route('materials.index')->with('success', "物料 {$validated['material_code']} 创建成功。");
    }

    public function edit(Material $material)
    {
        $categories = MaterialCategory::where('status', 1)->orderBy('sort_order')->get();

        return view('materials.edit', compact('material', 'categories'));
    }

    public function update(MaterialUpdateRequest $request, Material $material)
    {
        $validated = $request->validated();

        $material->update($validated);

        return redirect()->route('materials.index')->with('success', "物料 {$material->material_code} 更新成功。");
    }
}
