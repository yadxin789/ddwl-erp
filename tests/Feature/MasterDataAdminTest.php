<?php

namespace Tests\Feature;

use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MasterDataDomain;
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

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->manager = User::where('username', 'rnd-manager')->firstOrFail();
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
        $executorId = User::where('username', 'engineer')->value('id') ?: $this->manager->id;

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
}
