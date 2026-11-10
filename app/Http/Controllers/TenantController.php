<?php

namespace App\Http\Controllers;

use App\Models\System\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderByDesc('id')->paginate(15);

        return view('tenant.index', compact('tenants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:tenants,code'],
            'name' => ['required', 'string', 'max:128'],
            'contact_name' => ['nullable', 'string', 'max:64'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'expired_at' => ['nullable', 'date'],
        ]);
        $data['status'] = 'active';
        Tenant::create($data);

        return back()->with('success', '租户已创建。');
    }
}
