<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Rnd\RndCollabDocument;
use App\Models\Rnd\RndProject;
use App\Services\CollabOfficeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * OnlyOffice 文件型协同：模板、JWT、旧正文转换、回调版本与历史快照。
 */
class CollabOfficeTest extends TestCase
{
    private CollabOfficeService $office;
    private int $projectId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        Storage::fake('collab_templates');
        Storage::fake('file_repository');

        $this->office = app(CollabOfficeService::class);
        $this->userId = (int) User::where('username', '王明')->value('id');
        $this->projectId = (int) RndProject::create([
            'project_code' => 'PRJ-OO-' . substr((string) uniqid(), -5),
            'project_name' => 'OnlyOffice 测试项目',
            'product_model' => 'OO-01',
            'creator_id' => $this->userId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'in_progress',
            'stage' => 'initiation',
            'progress' => 0,
        ])->id;
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_templates_are_generated_as_valid_office_zip_packages(): void
    {
        $this->office->ensureTemplates();

        foreach (['docx', 'xlsx', 'pptx'] as $ext) {
            $path = Storage::disk('collab_templates')->path('template.' . $ext);
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path) === true, $ext . ' 不是有效 zip');
            $zip->close();
            $this->assertGreaterThan(0, Storage::disk('collab_templates')->size('template.' . $ext));
        }
    }

    public function test_jwt_is_hs256_and_rejects_tampering(): void
    {
        config(['collab.jwt_secret' => 'test-secret']);

        $token = $this->office->generateJwt(['document' => ['key' => 'abc']]);
        $this->assertTrue($this->office->verifyJwt($token));
        $this->assertFalse($this->office->verifyJwt($token . 'x'));
    }

    public function test_legacy_content_is_converted_to_real_docx_and_callback_increments_version(): void
    {
        $doc = RndCollabDocument::create([
            'project_id' => $this->projectId,
            'title' => '旧正文转换',
            'doc_type' => 'doc',
            'content' => '第一行' . PHP_EOL . '第二行',
            'version' => 1,
            'creator_id' => $this->userId,
            'updater_id' => $this->userId,
        ]);

        $this->office->ensureRepositoryFile($doc);

        $this->assertSame('docx', $doc->extension);
        $this->assertTrue($doc->hasRepositoryFile());
        $this->assertNotEmpty($doc->doc_key);
        $this->assertGreaterThan(0, $doc->size);

        $this->office->storeCallbackContent($doc, 'callback content');

        $this->assertSame(2, (int) $doc->version);
        $this->assertSame(16, (int) $doc->size);
        $this->assertTrue(Storage::disk('file_repository')->exists(
            'collab/' . $this->projectId . '/history/doc_' . $doc->id . '_v1.docx'
        ));
    }

    public function test_editor_config_exposes_onlyoffice_urls_and_document_key(): void
    {
        config([
            'collab.pm_internal_url' => 'http://host.docker.internal:8000',
            'collab.jwt_secret' => '',
        ]);

        $doc = RndCollabDocument::create([
            'project_id' => $this->projectId,
            'title' => '配置测试',
            'doc_type' => 'doc',
            'content' => '',
            'version' => 1,
            'creator_id' => $this->userId,
            'updater_id' => $this->userId,
        ]);
        $this->office->createNewFile($doc);

        $config = $this->office->buildEditorConfig($doc, User::find($this->userId), true);

        $this->assertSame('word', $config['documentType']);
        $this->assertSame('docx', $config['document']['fileType']);
        $this->assertStringContainsString('host.docker.internal:8000', $config['document']['url']);
        $this->assertStringContainsString('/rnd/collab/callback/', $config['editorConfig']['callbackUrl']);
        $this->assertSame($doc->doc_key, $config['document']['key']);
        $this->assertTrue($config['document']['permissions']['edit']);
    }
}
