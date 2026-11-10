<?php

namespace App\Services;

use App\Models\Crm\CrmFollowUp;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmOpportunity;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * CRM 服务：线索 / 商机 / 跟进
 * P1-03：业务逻辑（单号生成、默认状态、负责人）从 Controller 下沉到 Service
 */
class CrmService
{
    public function createLead(array $data): CrmLead
    {
        Log::info('CRM 线索创建', ['user_id' => Auth::id(), 'company' => $data['company_name']]);
        $data['lead_no'] = NumberGenerator::generate('crm_lead');
        $data['status'] = 'new';
        $data['owner_id'] = Auth::id();

        return CrmLead::create($data);
    }

    public function createOpportunity(array $data): CrmOpportunity
    {
        Log::info('CRM 商机创建', ['user_id' => Auth::id(), 'name' => $data['name']]);
        $data['op_no'] = NumberGenerator::generate('crm_op');
        $data['stage'] = 'prospecting';
        $data['owner_id'] = Auth::id();

        return CrmOpportunity::create($data);
    }

    public function createFollowUp(array $data): CrmFollowUp
    {
        Log::info('CRM 跟进记录', ['user_id' => Auth::id(), 'type' => $data['type'] ?? null]);
        $data['user_id'] = Auth::id();
        $data['followed_at'] = now();

        return CrmFollowUp::create($data);
    }
}
