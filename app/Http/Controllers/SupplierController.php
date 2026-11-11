<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\SupplierStoreRequest;
use App\Http\Requests\Supplier\SupplierUpdateRequest;
use App\Models\Finance\PaymentTerm;
use App\Models\MasterData\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('supplier_code', 'like', "%{$kw}%")
                    ->orWhere('supplier_name', 'like', "%{$kw}%")
                    ->orWhere('contact_person', 'like', "%{$kw}%");
            });
        }

        $suppliers = $query->orderBy('supplier_code')->paginate(20)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create', ['paymentTerms' => $this->paymentTerms()]);
    }

    public function store(SupplierStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = 1;

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', '供应商创建成功。');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', [
            'supplier' => $supplier,
            'paymentTerms' => $this->paymentTerms(),
        ]);
    }

    public function update(SupplierUpdateRequest $request, Supplier $supplier)
    {
        $validated = $request->validated();

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', '供应商更新成功。');
    }

    private function paymentTerms()
    {
        return PaymentTerm::query()
            ->where('status', 1)
            ->orderBy('days')
            ->orderBy('name')
            ->get();
    }
}
