<?php

namespace Tests\Feature;

use App\Models\Ai\AiAttachment;
use App\Models\Ai\AiConversation;
use App\Models\Ai\AiSkill;
use App\Models\Organization\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiChatFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_page_exposes_reference_features_and_imported_skills(): void
    {
        $user = User::where('username', '钱七')->firstOrFail();

        $this->actingAs($user)->get(route('ai.chat'))
            ->assertOk()
            ->assertSee('新建会话')
            ->assertSee('搜索历史会话')
            ->assertSee('添加图片')
            ->assertSee('添加文件')
            ->assertSee('发送并保存')
            ->assertSee('内置业务问答（ERP 数据）')
            ->assertSee('提问技巧')
            ->assertSee('PM 数据问答')
            ->assertSee('数据变更助理');

        $this->assertSame(6, AiSkill::where('enabled', true)->count());
        $pmQa = AiSkill::where('code', 'pm-qa')->firstOrFail();
        $this->assertSame('agent', $pmQa->type);
        $this->assertContains('QueryProjects', $pmQa->config['tools']);
    }

    public function test_conversation_can_be_created_renamed_exported_and_deleted(): void
    {
        $user = User::where('username', '钱七')->firstOrFail();
        $this->actingAs($user);

        $store = $this->post(route('ai.conversations.store'));
        $store->assertRedirect();
        $conversation = AiConversation::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->patch(route('ai.conversations.update', $conversation), ['title' => '销售复盘'])
            ->assertRedirect();
        $this->assertSame('销售复盘', $conversation->fresh()->title);

        $this->get(route('ai.conversations.export', $conversation))
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8');

        $this->delete(route('ai.conversations.destroy', $conversation))->assertRedirect();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_chat_saves_uploaded_file_and_exposes_attachment_download(): void
    {
        Storage::fake('local');
        $user = User::where('username', '钱七')->firstOrFail();
        $this->actingAs($user);

        $response = $this->post(route('ai.chat.send'), [
            'content' => '请查看附件中的销售数据',
            'skill_code' => 'pm-qa',
            'files' => [UploadedFile::fake()->create('销售明细.txt', 6, 'text/plain')],
        ]);
        $response->assertRedirect();

        $attachment = AiAttachment::firstOrFail();
        $this->assertSame('销售明细.txt', $attachment->file_name);
        $this->assertSame('text/plain', $attachment->mime_type);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->get(route('ai.attachments.download', $attachment))->assertOk();

        $conversation = AiConversation::findOrFail($attachment->conversation_id);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('销售明细.txt', $conversation->messages()->first()->attachments()->first()->file_name);
    }
}
