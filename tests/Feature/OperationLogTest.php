<?php

namespace Tests\Feature;

use App\Models\Organization\Position;
use App\Models\Organization\User;
use App\Models\Rnd\RndTaskType;
use App\Models\Security\OperationLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 普通 CRUD 个人追溯（第 3、4 项）：
 *  - 新增/修改/删除都写 operation_logs，并带操作人 ID + 姓名快照；
 *  - 同岗位两个账号分别操作，日志能区分到具体人；
 *  - 密码等敏感字段只落脱敏标记。
 */
class OperationLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crud_writes_are_attributed_to_logged_in_user(): void
    {
        $actor = User::where('username', '张三')->firstOrFail();
        $this->actingAs($actor);

        $type = RndTaskType::create([
            'code' => 'TMP_OPLOG',
            'name' => '日志测试类型',
            'department_id' => 5,
            'status' => 1,
        ]);
        $type->update(['name' => '日志测试类型-已改名']);
        $type->delete();

        $logs = OperationLog::where('target_type', 'rnd_task_types')
            ->where('target_id', (string) $type->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $logs);
        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('action')->all());
        $this->assertSame($actor->id, $logs[0]->user_id);
        $this->assertSame('张三', $logs[0]->actor_name);
        $this->assertSame('日志测试类型', $logs[0]->target_label);
        $this->assertSame('日志测试类型', $logs[1]->changes['before']['name']);
        $this->assertSame('日志测试类型-已改名', $logs[1]->changes['after']['name']);
    }

    public function test_sensitive_fields_are_masked_in_changes(): void
    {
        $actor = User::where('username', '张三')->firstOrFail();
        $target = User::where('username', '钱七')->firstOrFail();
        $this->actingAs($actor);

        $target->update(['password' => 'new-secret-123']);

        $log = OperationLog::where('target_type', 'users')
            ->where('target_id', (string) $target->id)
            ->where('action', 'updated')
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('[已脱敏]', $log->changes['after']['password']);
        $this->assertStringNotContainsString('new-secret-123', json_encode($log->changes, JSON_UNESCAPED_UNICODE));
    }

    public function test_two_accounts_in_same_position_are_traceable_to_different_people(): void
    {
        $position = Position::where('pos_name', '销售专员')->firstOrFail();
        $role = Role::findByName('role_sal_specialist');

        $first = User::create([
            'username' => '测试销售甲',
            'name' => '测试销售甲',
            'email' => 'oplog-sales-a@demo.com',
            'password' => Hash::make('123456'),
            'department_id' => $position->department_id,
            'position_id' => $position->id,
            'status' => 1,
        ]);
        $first->assignRole($role);

        $second = User::create([
            'username' => '测试销售乙',
            'name' => '测试销售乙',
            'email' => 'oplog-sales-b@demo.com',
            'password' => Hash::make('123456'),
            'department_id' => $position->department_id,
            'position_id' => $position->id,
            'status' => 1,
        ]);
        $second->assignRole($role);

        $this->assertSame($first->position_id, $second->position_id);

        $this->actingAs($first)->post(route('crm.leads.store'), [
            'company_name' => '同岗位追溯-甲客户',
            'contact_name' => '甲联系人',
        ])->assertRedirect();

        $this->actingAs($second)->post(route('crm.leads.store'), [
            'company_name' => '同岗位追溯-乙客户',
            'contact_name' => '乙联系人',
        ])->assertRedirect();

        $logs = OperationLog::where('target_type', 'crm_leads')
            ->where('action', 'created')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame([$second->id, $first->id], [$logs[0]->user_id, $logs[1]->user_id]);
        $this->assertSame(['测试销售乙', '测试销售甲'], [$logs[0]->actor_name, $logs[1]->actor_name]);
        $this->assertSame($second->id, $logs[0]->changes['after']['owner_id']);
        $this->assertSame($first->id, $logs[1]->changes['after']['owner_id']);
        $this->assertSame('同岗位追溯-乙客户', $logs[0]->target_label);
        $this->assertSame('同岗位追溯-甲客户', $logs[1]->target_label);
    }

    public function test_operation_log_tab_is_visible_with_actor_and_target(): void
    {
        $actor = User::where('username', '张三')->firstOrFail();
        $this->actingAs($actor);

        $type = RndTaskType::create([
            'code' => 'TMP_OPLOG_PAGE',
            'name' => '页面可见性测试',
            'department_id' => 5,
            'status' => 1,
        ]);

        $this->get('/security/logs?tab=operations')
            ->assertOk()
            ->assertSee('操作日志')
            ->assertSee('张三')
            ->assertSee('rnd_task_types')
            ->assertSee('页面可见性测试');
    }
}
