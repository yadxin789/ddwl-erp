<?php

namespace App\Services;

use App\Models\Hr\HrDepartment;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrLeave;
use App\Support\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * HR 服务：部门 / 员工 / 请假
 * P1-03：业务逻辑（单号、默认状态、请假天数计算）下沉到 Service
 */
class HrService
{
    public function createDepartment(array $data): HrDepartment
    {
        Log::info('HR 部门创建', ['user_id' => Auth::id(), 'code' => $data['code']]);

        return HrDepartment::create($data);
    }

    public function createEmployee(array $data): HrEmployee
    {
        Log::info('HR 员工入职', ['user_id' => Auth::id(), 'emp_no' => $data['emp_no'], 'name' => $data['name']]);
        $data['status'] = 'active';

        return HrEmployee::create($data);
    }

    public function createLeave(array $data): HrLeave
    {
        Log::info('HR 请假申请', ['user_id' => Auth::id(), 'employee_id' => $data['employee_id'], 'type' => $data['type']]);
        $data['leave_no'] = NumberGenerator::generate('leave');
        $data['days'] = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        $data['status'] = 'pending';

        return HrLeave::create($data);
    }
}
