<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MediaFile;
use App\Services\FileService;
use App\Support\NumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0-07 / B-02 / B-03：文件统一下载入口 + 按业务归属授权 + 存储口径统一（local 盘）
 *
 * 验收：未登录 302；非参与者下载研发图档 403；参与者 200 且 Content-Disposition 正确；
 * 新上传不再落 public 盘（直链失效），老资料文件仍可从 public 盘读出（兼容）。
 */
class FileDownloadTest extends TestCase
{
    private User $creator;
    private User $executor;
    private User $outsider;

    private MediaFile $rndFile;
    private MediaFile $resourceFile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        DB::beginTransaction();

        $this->creator = User::where('username', 'rnd-manager')->firstOrFail();
        $this->executor = User::where('username', 'engineer')->firstOrFail();
        $this->outsider = User::where('username', 'eng-sw')->firstOrFail();

        $project = RndProject::create([
            'project_code' => NumberGenerator::generate('rnd_project'),
            'project_name' => 'P0-07 文件授权测试项目',
            'creator_id' => $this->creator->id,
            'status' => 'in_progress',
            'progress' => 0,
        ]);
        $type = RndTaskType::firstOrCreate(
            ['name' => 'P0-07 测试任务类型'],
            ['description' => '测试用', 'is_required' => false, 'sort_order' => 998]
        );
        $task = RndProjectTask::create([
            'project_id' => $project->id,
            'task_type_id' => $type->id,
            'executor_id' => $this->executor->id,
            'auditor_id' => $this->creator->id,
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        $this->executor->id && $this->actingAs($this->executor);
        $this->rndFile = app(FileService::class)
            ->upload(UploadedFile::fake()->create('p0-07-rnd.pdf', 10, 'application/pdf'), 'rnd_doc', 'rnd_document', $task->id);

        RndProjectDocument::create([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'version_no' => 1,
            'file_id' => $this->rndFile->id,
            'uploader_id' => $this->executor->id,
            'audit_status' => 'pending',
        ]);

        $this->resourceFile = app(FileService::class)
            ->upload(UploadedFile::fake()->create('p0-07-shared.pdf', 5, 'application/pdf'), 'resource');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // setUp 里为了上传造数已 actingAs，这里必须真正清掉登录态
        \Illuminate\Support\Facades\Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->assertGuest();
        $this->get("/files/{$this->resourceFile->id}/download")->assertRedirect(route('login'));
    }

    public function test_shared_resource_is_downloadable_by_any_position(): void
    {
        $user = User::where('username', 'sales')->firstOrFail();
        $this->actingAs($user);

        $response = $this->get("/files/{$this->resourceFile->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('p0-07-shared', $response->headers->get('content-disposition'));
    }

    public function test_rnd_document_is_limited_to_participants(): void
    {
        $this->actingAs($this->executor);
        $this->get("/files/{$this->rndFile->id}/download")->assertStatus(200);

        $this->actingAs($this->creator);   // 项目创建人 / 审核人
        $this->get("/files/{$this->rndFile->id}/download")->assertStatus(200);

        $this->actingAs($this->outsider);  // 与项目无关的工程师
        $this->get("/files/{$this->rndFile->id}/download")->assertStatus(403);
    }

    public function test_uploads_go_to_private_disk_not_public_storage(): void
    {
        $this->assertStringStartsWith('uploads/', $this->resourceFile->file_path, '新文件应落 local 盘 uploads/');
        $this->assertFalse(
            Storage::disk('public')->exists($this->resourceFile->file_path),
            'public 盘不应存在该文件（否则 storage 直链可绕过授权）'
        );
        $this->assertTrue(Storage::disk('local')->exists($this->resourceFile->file_path));
    }

    public function test_legacy_public_resource_file_still_downloads(): void
    {
        // 老数据：文件在 public 盘 resources/ 下（P0-07 之前 uploadFile 的落盘方式）
        Storage::disk('public')->put('resources/legacy.pdf', 'legacy-content');
        $legacy = MediaFile::create([
            'file_name' => 'legacy.pdf',
            'file_path' => 'resources/legacy.pdf',
            'file_size' => 14,
            'mime_type' => 'application/pdf',
            'category' => 'resource',
            'uploader_id' => $this->creator->id,
            'created_at' => now(),
        ]);

        $this->actingAs($this->executor);
        $this->get("/files/{$legacy->id}/download")
            ->assertStatus(200)
            ->assertHeader('content-disposition');
    }
}
