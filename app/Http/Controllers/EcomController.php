<?php

namespace App\Http\Controllers;

use App\Models\Ecom\EcomOrder;
use App\Models\Ecom\EcomPlatform;
use Illuminate\Http\Request;

class EcomController extends Controller
{
    public function platforms()
    {
        $platforms = EcomPlatform::orderBy('id')->paginate(15);

        return view('ecom.platforms', compact('platforms'));
    }

    public function platformStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:ecom_platforms,code'],
            'name' => ['required', 'string', 'max:64'],
            'shop_url' => ['nullable', 'string', 'max:256'],
        ]);
        $data['status'] = true;
        EcomPlatform::create($data);

        return back()->with('success', '平台已添加。');
    }

    public function orders(Request $request)
    {
        $orders = EcomOrder::with('platform')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->q, fn($q, $kw) => $q->where('ecom_order_no', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        return view('ecom.orders', compact('orders'));
    }
}
