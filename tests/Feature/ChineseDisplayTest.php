<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Support\DisplayText;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 单语言（中文）界面回归：数据库中的英文代码在页面必须显示中文。
 */
class ChineseDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->actingAs(User::where('username', '张三')->firstOrFail());
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_role_options_on_user_edit_page_are_chinese(): void
    {
        $user = User::where('username', '李四')->firstOrFail();

        $this->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('工程师')
            ->assertSee('采购专员')
            ->assertDontSee('>engineer<', false)
            ->assertDontSee('>purchase_specialist<', false);
    }

    public function test_role_and_permission_list_pages_are_chinese(): void
    {
        $this->get(route('roles.index'))
            ->assertOk()
            ->assertSee('工程师')
            ->assertSee('系统·用户查看')
            ->assertDontSee('>engineer<', false)
            ->assertDontSee('system.user.view');
    }

    public function test_display_text_reads_translation_dictionary(): void
    {
        $this->assertSame('工程师', __('display.roles.engineer'));
        $this->assertSame('物料清单（BOM）', __('display.permission_modules.bom'));
        $this->assertSame('查看', __('display.permission_actions.view'));
        $this->assertSame('待处理', __('display.statuses.pending'));
        $this->assertSame('原材料', __('display.types.raw_material'));
    }

    public function test_display_text_maps_codes_to_chinese(): void
    {
        $this->assertSame('研发经理', DisplayText::role('role_rnd_manager'));
        $this->assertSame('系统管理员', DisplayText::role('system_admin'));
        $this->assertSame('系统·用户查看', DisplayText::permission('system.user.view'));
        $this->assertSame('待处理', DisplayText::status('pending'));
        $this->assertSame('原材料', DisplayText::type('raw_material'));
        $this->assertSame('来料检验（IQC）', DisplayText::stage('IQC'));
        $this->assertSame('custom_role_code', DisplayText::role('custom_role_code'));
    }
}
