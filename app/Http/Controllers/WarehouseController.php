<?php

namespace App\Http\Controllers;

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Http\Request;

/**
 * 仓库与库位（G3）：仓管查看本仓库位、维护库位启停（v1.3 §4.21）
 */
class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::query()
            ->with(['locations' => fn ($q) => $q->orderBy('location_code')])
            ->orderBy('warehouse_code')
            ->get();

        return view('warehouse.index', compact('warehouses'));
    }

    public function locationStore(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_code' => ['required', 'string', 'max:32', 'unique:warehouse_locations,location_code'],
            'zone' => ['nullable', 'string', 'max:32'],
        ]);

        WarehouseLocation::create([
            'warehouse_id' => (int) $data['warehouse_id'],
            'location_code' => strtoupper(trim($data['location_code'])),
            'zone' => $data['zone'] ?? null,
            'status' => 1,
        ]);

        return back()->with('success', '库位已创建：' . $data['location_code']);
    }

    public function locationToggle(WarehouseLocation $location)
    {
        $location->update(['status' => $location->status ? 0 : 1]);

        return back()->with('success', sprintf('库位 %s 已%s', $location->location_code, $location->status ? '启用' : '停用'));
    }
}
