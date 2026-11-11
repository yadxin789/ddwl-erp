<?php

namespace Tests\Feature;

use App\Models\Approval\ApprovalFlow;
use App\Models\Finance\PaymentTerm;
use App\Models\Finance\TaxEntity;
use App\Models\Finance\TaxRate;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Supplier;
use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MasterDataDomain;
use App\Models\System\NumberSequence;
use App\Models\System\Unit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 基础数据归口管理：
 * - 后台台账与研发任务类型维护仅系统管理员可达
 * - 任务类型可新增/改名/停用/删除
 * - 已被项目任务引用的类型不得删除
 */
class MasterDataAdminTest extends TestCase
{
    private User $admin;
    private User $manager;
    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->admin = User::where('username', '张三')->firstOrFail();
        $this->manager = User::where('username', '王明')->firstOrFail();
        $this->departmentId = Department::where('dept_code', 'D-RND')->value('id');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_master_data_registry_page_is_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master-data'))
            ->assertOk()
            ->assertSee('基础数据归口管理')
            ->assertSee('研发任务类型');

        $this->actingAs($this->manager)
            ->get(route('admin.master-data'))
            ->assertForbidden();
    }

    public function test_task_type_admin_page_is_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master-data.task-types'))
            ->assertOk()
            ->assertSee('研发任务类型维护')
            ->assertSee('RND-TT-001');

        $this->actingAs($this->manager)
            ->get(route('admin.master-data.task-types'))
            ->assertForbidden();
    }

    public function test_domain_owner_can_be_reassigned(): void
    {
        $domain = MasterDataDomain::firstOrFail();
        $newOwner = Department::where('dept_code', 'D-QC')->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.master-data.domains.update', $domain), [
                'owner_department_id' => $newOwner,
                'status' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('master_data_domains', [
            'id' => $domain->id,
            'owner_department_id' => $newOwner,
        ]);
    }

    public function test_task_type_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.master-data.task-types.store'), [
                'name' => '归口测试任务类型',
                'department_id' => $this->departmentId,
                'description' => '测试新增',
                'is_required' => 1,
                'status' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $type = RndTaskType::where('name', '归口测试任务类型')->firstOrFail();
        $this->assertNotEmpty($type->code);
        $this->assertTrue($type->status);

        $this->actingAs($this->admin)
            ->put(route('admin.master-data.task-types.update', $type), [
                'name' => '归口测试任务类型（改名）',
                'department_id' => $this->departmentId,
                'description' => '测试改名',
                'status' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rnd_task_types', [
            'id' => $type->id,
            'name' => '归口测试任务类型（改名）',
            'status' => 0,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.task-types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rnd_task_types', ['id' => $type->id]);
    }

    public function test_referenced_task_type_cannot_be_deleted(): void
    {
        $type = RndTaskType::firstOrFail();
        $executorId = User::where('username', '孙八')->value('id') ?: $this->manager->id;

        $projectId = DB::table('rnd_projects')->insertGetId([
            'project_code' => 'TEST-MD-DEL-' . uniqid(),
            'project_name' => '引用保护测试项目',
            'creator_id' => $this->manager->id,
            'status' => 'in_progress',
            'progress' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rnd_project_tasks')->insert([
            'project_id' => $projectId,
            'task_type_id' => $type->id,
            'name' => '引用保护测试任务',
            'department_id' => $this->departmentId,
            'executor_id' => $executorId,
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.task-types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHasErrors('task_type');

        $this->assertDatabaseHas('rnd_task_types', ['id' => $type->id]);
    }

    public function test_disabled_task_type_is_hidden_from_project_create(): void
    {
        $type = RndTaskType::create([
            'code' => 'RND-TT-TEST',
            'name' => '停用后不应出现在创建页',
            'department_id' => $this->departmentId,
            'description' => null,
            'is_required' => true,
            'sort_order' => 999,
            'status' => false,
        ]);

        $this->actingAs($this->manager)
            ->get(route('rnd.projects.create'))
            ->assertOk()
            ->assertDontSee($type->name);
    }

    public function test_master_data_dictionary_pages_are_admin_only(): void
    {
        $pages = [
            'admin.master-data.units' => '计量单位维护',
            'admin.master-data.tax-terms' => '税率与结算方式',
            'admin.master-data.approval-flows' => '审批流程维护',
            'admin.master-data.number-sequences' => '单据编号规则',
        ];

        foreach ($pages as $routeName => $title) {
            $this->actingAs($this->admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee($title);

            $this->actingAs($this->manager)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_unit_can_be_created_and_used_unit_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.master-data.units.store'), [
                'unit_code' => 'TSTU',
                'unit_name' => '测试单位',
                'conversion_factor' => 1,
                'status' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $unit = Unit::where('unit_code', 'TSTU')->firstOrFail();
        $this->actorDelete(route('admin.master-data.units.destroy', $unit))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);

        // 已被物料引用（materials.unit 存的是单位编码）的单位只能停用
        $used = Unit::where('unit_code', 'PCS')->firstOrFail();
        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.units.destroy', $used))
            ->assertRedirect()
            ->assertSessionHasErrors('unit');
        $this->assertDatabaseHas('units', ['id' => $used->id]);
    }

    public function test_tax_entity_rate_and_payment_term_can_be_maintained(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.master-data.tax-entities.store'), [
                'code' => 'TEST_TAX',
                'name' => '测试税目',
                'type' => 'vat',
                'status' => 1,
            ])->assertRedirect()->assertSessionHas('success');

        $entity = TaxEntity::where('code', 'TEST_TAX')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.master-data.tax-rates.store'), [
                'tax_entity_id' => $entity->id,
                'tax_type' => '测试税率',
                'rate' => 5.5,
                'status' => 1,
            ])->assertRedirect()->assertSessionHas('success');

        $rate = TaxRate::where('tax_entity_id', $entity->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.tax-entities.destroy', $entity))
            ->assertRedirect()
            ->assertSessionHasErrors('tax_entity');
        $this->assertDatabaseHas('tax_entities', ['id' => $entity->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.tax-rates.destroy', $rate))
            ->assertRedirect();
        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.tax-entities.destroy', $entity))
            ->assertRedirect();
        $this->assertDatabaseMissing('tax_entities', ['id' => $entity->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.master-data.payment-terms.store'), [
                'code' => 'TEST_NET',
                'name' => '测试结算',
                'days' => 15,
                'description' => '测试',
                'status' => 1,
            ])->assertRedirect()->assertSessionHas('success');

        $term = PaymentTerm::where('code', 'TEST_NET')->firstOrFail();
        $this->actingAs($this->admin)
            ->put(route('admin.master-data.payment-terms.update', $term), [
                'code' => 'TEST_NET',
                'name' => '测试结算（改）',
                'days' => 20,
                'description' => '测试改名',
                'status' => 0,
            ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('payment_terms', ['id' => $term->id, 'name' => '测试结算（改）', 'status' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.payment-terms.destroy', $term))
            ->assertRedirect();
        $this->assertDatabaseMissing('payment_terms', ['id' => $term->id]);
    }

    public function test_approval_flow_can_be_created_and_deleted(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master-data.approval-flows.create'))
            ->assertOk()
            ->assertSee('审批节点');

        $this->actingAs($this->admin)
            ->post(route('admin.master-data.approval-flows.store'), [
                'flow_code' => 'flow_test_admin',
                'flow_name' => '测试审批流',
                'business_type' => 'test.admin',
                'status' => 1,
                'steps' => [[
                    'name' => '管理员审批',
                    'permission' => 'admin.masterdata.manage',
                    'approver_id' => $this->admin->id,
                    'delegate_ids' => [],
                ]],
            ])->assertRedirect(route('admin.master-data.approval-flows'));

        $flow = ApprovalFlow::where('flow_code', 'flow_test_admin')->firstOrFail();
        $this->assertSame(1, $flow->steps_json[0]['step']);
        $this->assertSame($this->admin->id, $flow->steps_json[0]['approver_id']);

        $this->actingAs($this->admin)
            ->get(route('admin.master-data.approval-flows.edit', $flow))
            ->assertOk()
            ->assertSee('测试审批流');

        $this->actingAs($this->admin)
            ->delete(route('admin.master-data.approval-flows.destroy', $flow))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('approval_flows', ['id' => $flow->id]);
    }

    public function test_number_sequence_can_be_updated_and_disabled(): void
    {
        $rule = NumberSequence::query()->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.master-data.number-sequences.update', $rule), [
                'prefix' => 'T',
                'date_format' => 'Ymd',
                'seq_length' => 6,
                'current_seq' => 12,
                'description' => '测试规则',
                'status' => 0,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('number_sequences', [
            'id' => $rule->id,
            'prefix' => 'T',
            'seq_length' => 6,
            'current_seq' => 12,
            'status' => 0,
        ]);
    }

    public function test_customer_and_supplier_forms_use_payment_term_dictionary(): void
    {
        $sales = User::where('username', '钱七')->firstOrFail();
        $purchase = User::where('username', '赵六')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('月结30天')
            ->assertSee('月结90天');

        $this->actingAs($purchase)
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('货到付款')
            ->assertSee('预付100%');

        $this->actingAs($sales)
            ->post(route('customers.store'), [
                'customer_code' => 'TST-CUST-' . uniqid(),
                'customer_name' => '结算方式下拉测试客户',
                'payment_terms' => '月结30天',
            ])
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'customer_name' => '结算方式下拉测试客户',
            'payment_terms' => '月结30天',
        ]);

        $this->actingAs($purchase)
            ->post(route('suppliers.store'), [
                'supplier_code' => 'TST-SUP-' . uniqid(),
                'supplier_name' => '结算方式下拉测试供应商',
                'payment_terms' => '货到付款',
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'supplier_name' => '结算方式下拉测试供应商',
            'payment_terms' => '货到付款',
        ]);
    }

    public function test_inactive_payment_term_is_not_offered_and_existing_value_is_kept(): void
    {
        $term = PaymentTerm::where('code', 'NET60')->firstOrFail();
        $term->update(['status' => false]);

        $sales = User::where('username', '钱七')->firstOrFail();
        $this->actingAs($sales)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertDontSee('月结60天');

        $customer = Customer::create([
            'customer_code' => 'TST-CUST-LEGACY-' . uniqid(),
            'customer_name' => '历史结算方式客户',
            'payment_terms' => '月结60天',
            'owner_id' => $sales->id,
            'department_id' => $sales->department_id,
            'status' => 1,
        ]);

        $this->actingAs($sales)
            ->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('月结60天（历史值，不在当前字典）');
    }

    private function actorDelete(string $url)
    {
        return $this->actingAs($this->admin)->delete($url);
    }
}
