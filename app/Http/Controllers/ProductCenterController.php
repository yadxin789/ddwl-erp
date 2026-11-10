<?php

namespace App\Http\Controllers;

use App\Models\Product\ProductBrand;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductSpu;
use App\Services\NumberGenerator;
use Illuminate\Http\Request;

class ProductCenterController extends Controller
{
    public function categories()
    {
        $cats = ProductCategory::orderBy('sort')->paginate(20);

        return view('product.categories', compact('cats'));
    }

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:product_categories,code'],
            'name' => ['required', 'string', 'max:64'],
        ]);
        ProductCategory::create($data);

        return back()->with('success', '类目已添加。');
    }

    public function brands()
    {
        $brands = ProductBrand::orderBy('id')->paginate(20);

        return view('product.brands', compact('brands'));
    }

    public function brandStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:product_brands,code'],
            'name' => ['required', 'string', 'max:64'],
        ]);
        ProductBrand::create($data);

        return back()->with('success', '品牌已添加。');
    }

    public function spus(Request $request)
    {
        $spus = ProductSpu::with(['category', 'brand'])->orderByDesc('id')->paginate(15);
        $cats = ProductCategory::orderBy('sort')->get();
        $brands = ProductBrand::orderBy('id')->get();

        return view('product.spus', compact('spus', 'cats', 'brands'));
    }

    public function spuStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:product_brands,id'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:16'],
        ]);
        $data['spu_no'] = NumberGenerator::generate('spu');
        $data['status'] = 'draft';
        ProductSpu::create($data);

        return back()->with('success', '商品SPU已创建。');
    }
}
