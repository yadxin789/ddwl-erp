<?php

namespace Tests\Feature;

use App\Models\Bom\BomHeader;
use App\Models\MasterData\Material;
use App\Models\Production\ApsPlan;
use App\Models\Production\ApsPlanEntry;
use App\Models\Production\ProductionOrder;
use App\Models\Production\Routing;
use App\Models\Production\RoutingOperation;
use App\Models\Production\WorkCenter;
use App\Models\Organization\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** P3-2 APS 有限产能排程。 */
class ApsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_finite_capacity_schedule_never_exceeds_daily_capacity(): void
    {
        $product = Material::where('material_code', 'ACC-FG01')->firstOrFail();
        $bom = BomHeader::where('bom_code', 'ACC-BOM1')->firstOrFail();
        $center = WorkCenter::create([
            'code' => 'WC-APS-T',
            'name' => 'APS 测试工作中心',
            'capacity_per_day' => 8,
            'status' => true,
        ]);
        $routing = Routing::create([
            'routing_code' => 'RT-APS-T',
            'product_material_id' => $product->id,
            'version' => 'v1.0',
            'status' => 'approved',
        ]);
        RoutingOperation::create([
            'routing_id' => $routing->id,
            'operation_seq' => 1,
            'operation_name' => 'APS 测试工序',
            'work_center_id' => $center->id,
            'standard_hours' => 1,
        ]);

        $start = today()->toDateString();
        $end = today()->addDays(5)->toDateString();
        $orders = [];
        foreach (['APS-T-001', 'APS-T-002'] as $no) {
            $orders[] = ProductionOrder::create([
                'order_no' => $no,
                'product_material_id' => $product->id,
                'bom_id' => $bom->id,
                'routing_id' => $routing->id,
                'plan_qty' => 8,
                'completed_qty' => 0,
                'defective_qty' => 0,
                'status' => 'planned',
                'start_date' => $start,
                'end_date' => today()->addDays(4)->toDateString(),
            ]);
        }

        $user = User::where('username', '吴十')->firstOrFail();
        $response = $this->actingAs($user)->post(route('production.aps.schedule'), [
            'horizon_start' => $start,
            'horizon_end' => $end,
        ]);

        $response->assertRedirect(route('production.aps'));
        $response->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('production.aps'))->assertOk()->assertSee('产能排程');

        $plan = ApsPlan::latest('id')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, (int) ($plan->summary_json['orders'] ?? 0));
        $this->assertContains($plan->status, ['draft', 'published']);

        $entries = ApsPlanEntry::where('plan_id', $plan->id)->get();
        $this->assertGreaterThan(0, $entries->count());
        $this->assertSame(2, $entries->whereIn('production_order_id', collect($orders)->pluck('id'))->pluck('production_order_id')->unique()->count());

        $daily = $entries->groupBy(fn ($e) => $e->work_center_id . '|' . $e->work_date->toDateString())
            ->map(fn ($rows) => (float) $rows->sum('hours'));
        foreach ($daily as $key => $hours) {
            [$centerId, $date] = explode('|', $key);
            $cap = (float) (WorkCenter::find($centerId)?->capacity_per_day ?: 8);
            $this->assertLessThanOrEqual($cap + 0.001, $hours, "工作中心 {$centerId} 在 {$date} 超出日产能");
        }

        foreach ($orders as $order) {
            $orderEntries = $entries->where('production_order_id', $order->id);
            $this->assertSame(8.0, round((float) $orderEntries->sum('hours'), 2), '每张工单应排满 8 小时');
            $this->assertSame(1, $orderEntries->pluck('work_date')->unique()->count(), '8 小时正好占满 1 天日产');
            $this->assertTrue($orderEntries->min('work_date')->lte($orderEntries->max('work_date')));
        }

        $first = $entries->where('production_order_id', $orders[0]->id)->max('work_date');
        $second = $entries->where('production_order_id', $orders[1]->id)->min('work_date');
        $this->assertTrue($second->gt($first), '第二张工单应在第一张之后再占用同一条产线');
    }

    public function test_schedule_without_routing_orders_is_rejected(): void
    {
        ProductionOrder::withoutGlobalScopes()->update(['routing_id' => null]);

        $user = User::where('username', '吴十')->firstOrFail();
        $response = $this->actingAs($user)->post(route('production.aps.schedule'), [
            'horizon_start' => today()->toDateString(),
            'horizon_end' => today()->addDays(2)->toDateString(),
        ]);

        $response->assertSessionHasErrors('aps');
    }
}
