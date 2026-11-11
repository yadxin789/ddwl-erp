<?php

namespace Tests\Feature;

use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrSalary;
use App\Models\MasterData\Customer;
use App\Models\Organization\User;
use App\Support\MaskingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P2-04 敏感字段掩码：手机号前3后4、银行账号后4、薪资金额全掩码；列表页必须输出掩码而非明文。
 */
class MaskingTest extends TestCase
{
    public function test_service_rules(): void
    {
        $this->assertSame('138****0000', MaskingService::phone('13800000000'));
        $this->assertSame('138****0000', MaskingService::phone('+86 138-0000-0000'));
        $this->assertSame('****', MaskingService::phone('123'));
        $this->assertSame('-', MaskingService::phone(null));
        $this->assertSame('-', MaskingService::phone(''));

        $this->assertSame('****1234', MaskingService::bankAccount('6222021234567891234'));
        $this->assertSame('****1234', MaskingService::bankAccount('6222 0212 3456 7891 234'));
        $this->assertSame('****', MaskingService::bankAccount('12'));

        $this->assertSame('****', MaskingService::salary(12345.67));
        $this->assertSame('****', MaskingService::salary('0.00'));
        $this->assertSame('-', MaskingService::salary(null));
    }

    public function test_customer_list_masks_phone(): void
    {
        DB::beginTransaction();
        try {
            $sales = User::where('username', '钱七')->firstOrFail();
            $code = 'ZZMASK' . substr((string) time(), -6);

            Customer::create([
                'customer_code' => $code,
                'customer_name' => '掩码测试客户',
                'contact_person' => '张三',
                'contact_phone' => '13800000000',
                'status' => 1,
                'owner_id' => $sales->id,
                'department_id' => $sales->department_id ?? 1,
            ]);

            $response = $this->actingAs($sales)->get('/customers?keyword=' . $code);
            $response->assertStatus(200);
            $response->assertSee('138****0000');
            $response->assertDontSee('13800000000');
        } finally {
            DB::rollBack();
        }
    }

    public function test_salary_list_masks_amounts(): void
    {
        DB::beginTransaction();
        try {
            $hr = User::where('username', '苏琴')->firstOrFail();
            $emp = HrEmployee::create([
                'emp_no' => 'MASK' . substr((string) time(), -6),
                'name' => '掩码测试员工',
                'status' => 'active',
            ]);

            HrSalary::create([
                'employee_id' => $emp->id,
                'period' => '202611',
                'base' => 12345.67,
                'bonus' => 2345.67,
                'deduction' => 345.67,
                'net' => 14345.67,
            ]);

            $response = $this->actingAs($hr)->get('/hr-adv/salaries');
            $response->assertStatus(200);
            $response->assertSee('****');
            $response->assertDontSee('12,345.67');
            $response->assertDontSee('14,345.67');
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_account_is_never_rendered_in_full(): void
    {
        // 现库没有银行账号列，服务层先定死口径：后 4 位，输入全号绝不原样返回
        $full = '6222021234567891234';
        $masked = MaskingService::bankAccount($full);
        $this->assertStringNotContainsString($full, $masked);
        $this->assertStringEndsWith('1234', $masked);
    }
}
