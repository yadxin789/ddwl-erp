<?php

namespace App\Services;

use App\Models\Rnd\RndCollabDocument;
use App\Models\Organization\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * OnlyOffice DocumentServer 集成：JWT 签名、编辑器配置、Office 模板、
 * 文件仓库读写、下载/保存回调与版本快照。
 */
class CollabOfficeService
{
    private const KEEP_VERSIONS = 2;

    public function enabled(): bool
    {
        return (bool) config('collab.enabled', true);
    }

    public function serverUrl(): string
    {
        return rtrim((string) config('collab.server_url'), '/');
    }

    public function jwtSecret(): string
    {
        return (string) config('collab.jwt_secret');
    }

    public function pmInternalUrl(): string
    {
        $url = rtrim((string) config('collab.pm_internal_url'), '/');

        return $url !== '' ? $url : rtrim((string) config('app.url'), '/');
    }

    /* ---------------- JWT（HS256） ---------------- */

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function generateJwt(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $header . '.' . $body, $this->jwtSecret(), true);

        return $header . '.' . $body . '.' . $this->base64UrlEncode($signature);
    }

    /** 校验 DocumentServer 回调 token；未配置 secret 时视为未启用 JWT */
    public function verifyJwt(string $token): bool
    {
        if ($this->jwtSecret() === '') {
            return true;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $body, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, $this->jwtSecret(), true));

        return hash_equals($expected, $signature);
    }

    /* ---------------- 编辑器配置 ---------------- */

    public function documentType(string $extension): string
    {
        return match (strtolower($extension)) {
            'xlsx' => 'cell',
            'pptx' => 'slide',
            default => 'word',
        };
    }

    public function buildEditorConfig(RndCollabDocument $doc, User $user, bool $canEdit): array
    {
        $config = [
            'type' => 'desktop',
            'documentType' => $this->documentType($doc->fileExtension()),
            'document' => [
                'fileType' => $doc->fileExtension(),
                'key' => $doc->doc_key,
                'title' => $doc->fileName(),
                'url' => $this->downloadUrl($doc),
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'review' => true,
                    'comment' => true,
                ],
            ],
            'editorConfig' => [
                'mode' => $canEdit ? 'edit' : 'view',
                'callbackUrl' => $this->callbackUrl($doc),
                'lang' => 'zh-CN',
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name ?: $user->username,
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'compactHeader' => false,
                    'hideRightMenu' => false,
                    'chat' => true,
                    'comments' => true,
                ],
            ],
        ];

        if ($this->jwtSecret() !== '') {
            $config['token'] = $this->generateJwt([
                'document' => $config['document'],
                'editorConfig' => $config['editorConfig'],
                'documentType' => $config['documentType'],
            ]);
        }

        return $config;
    }

    /* ---------------- URL ---------------- */

    public function downloadUrl(RndCollabDocument $doc): string
    {
        return $this->pmInternalUrl() . route('rnd.collab.officeDownload', [
            'doc' => $doc->id,
            'k' => $doc->doc_key,
        ], false);
    }

    public function callbackUrl(RndCollabDocument $doc): string
    {
        return $this->pmInternalUrl() . route('rnd.collab.officeCallback', [
            'doc' => $doc->id,
            'k' => $doc->doc_key,
        ], false);
    }

    /* ---------------- 文件仓库 ---------------- */

    /**
     * 旧正文数据首次打开时转换为 Office 文件，并补齐 storage_path/doc_key/size。
     */
    public function ensureRepositoryFile(RndCollabDocument $doc): void
    {
        if ($doc->hasRepositoryFile()) {
            return;
        }

        $ext = $doc->fileExtension();
        $path = 'collab/' . $doc->project_id . '/doc_' . $doc->id . '_' . uniqid() . '.' . $ext;
        $content = $this->buildInitialFile($doc, $ext);

        Storage::disk('file_repository')->put($path, $content);

        $doc->extension = $ext;
        $doc->storage_path = $path;
        $doc->size = Storage::disk('file_repository')->size($path);
        if (! $doc->doc_key) {
            $doc->refreshDocKey();
        }
        $doc->save();
    }

    /** 新建文档：从内置模板生成真实 Office 文件 */
    public function createNewFile(RndCollabDocument $doc): void
    {
        $ext = $doc->fileExtension();
        $path = 'collab/' . $doc->project_id . '/doc_' . $doc->id . '_' . uniqid() . '.' . $ext;

        Storage::disk('file_repository')->put($path, $this->buildInitialFile($doc, $ext));

        $doc->extension = $ext;
        $doc->storage_path = $path;
        $doc->size = Storage::disk('file_repository')->size($path);
        $doc->refreshDocKey();
        $doc->save();
    }

    /** 上传现有 Office 文件 */
    public function storeUploadedFile(RndCollabDocument $doc, string $tmpPath, string $ext): void
    {
        $ext = strtolower($ext);
        $path = 'collab/' . $doc->project_id . '/doc_' . $doc->id . '_' . uniqid() . '.' . $ext;

        Storage::disk('file_repository')->put($path, file_get_contents($tmpPath));

        $doc->extension = $ext;
        $doc->storage_path = $path;
        $doc->size = Storage::disk('file_repository')->size($path);
        $doc->refreshDocKey();
        $doc->save();
    }

    private function buildInitialFile(RndCollabDocument $doc, string $ext): string
    {
        $title = (string) $doc->title;
        $content = trim((string) $doc->content);

        return match ($ext) {
            'xlsx' => $this->buildXlsx($this->sheetRows($content, $title)),
            'pptx' => $this->templateBytes('pptx'),
            default => $this->buildDocx($content !== '' ? $content : $title),
        };
    }

    private function sheetRows(string $content, string $title): array
    {
        if ($content === '') {
            return [[$title]];
        }

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $rows[] = str_getcsv($line, "\t");
        }

        return $rows ?: [[$title]];
    }

    /* ---------------- 内置模板 ---------------- */

    public function ensureTemplates(): void
    {
        $disk = Storage::disk('collab_templates');
        foreach (['docx', 'xlsx', 'pptx'] as $ext) {
            $path = 'template.' . $ext;
            if (! $disk->exists($path)) {
                $disk->put($path, $this->templateBytes($ext));
            }
        }
    }

    public function templateBytes(string $ext): string
    {
        return match (strtolower($ext)) {
            'docx' => $this->buildDocx('协同办公文档'),
            'xlsx' => $this->buildXlsx([['协同办公表格']]),
            'pptx' => $this->pptxTemplate(),
            default => throw new \InvalidArgumentException("不支持的模板类型: {$ext}"),
        };
    }

    private function pptxTemplate(): string
    {
        $path = resource_path('templates/template.pptx');
        if (! is_file($path)) {
            throw new \RuntimeException("PPT 模板文件不存在：{$path}");
        }

        return file_get_contents($path);
    }

    private function zip(array $files): string
    {
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'collab_tpl_');
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建 Office 模板 zip');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildDocx(string $text): string
    {
        $paragraphs = '';
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $paragraphs .= '<w:p><w:r><w:t xml:space="preserve">' . $this->xml($line) . '</w:t></w:r></w:p>';
        }
        if ($paragraphs === '') {
            $paragraphs = '<w:p/>';
        }

        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                . '</Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                . $paragraphs
                . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
                . '</w:body></w:document>',
        ]);
    }

    private function buildXlsx(array $rows): string
    {
        $sheetRows = '';
        foreach ($rows as $r => $cells) {
            $rowNumber = $r + 1;
            $sheetRows .= '<row r="' . $rowNumber . '">';
            foreach (array_values($cells) as $c => $value) {
                $ref = $this->columnName($c + 1) . $rowNumber;
                $sheetRows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xml((string) $value) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                . $sheetRows
                . '</sheetData></worksheet>',
        ]);
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /* ---------------- 回调保存 ---------------- */

    /**
     * 保存 OnlyOffice 回调文件，并保留最近 KEEP_VERSIONS 个历史快照。
     */
    public function saveFromUrl(RndCollabDocument $doc, string $url): bool
    {
        if (! $this->isSafeCallbackUrl($url)) {
            return false;
        }

        $response = Http::withoutVerifying()->timeout(60)->get($url);
        if (! $response->successful()) {
            return false;
        }

        return $this->storeCallbackContent($doc, $response->body());
    }

    /** 只允许 http/https，拒绝云元数据与带账号密码的回调地址 */
    private function isSafeCallbackUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return ! in_array($host, ['169.254.169.254', 'metadata.google.internal'], true);
    }

    public function storeCallbackContent(RndCollabDocument $doc, string $content): bool
    {
        $disk = Storage::disk('file_repository');

        if ($doc->hasRepositoryFile()) {
            $history = 'collab/' . $doc->project_id . '/history/doc_' . $doc->id . '_v' . $doc->version . '.' . $doc->fileExtension();
            $disk->copy($doc->storage_path, $history);
            $this->pruneHistory($doc);
        }

        $disk->put($doc->storage_path, $content);
        $doc->size = $disk->size($doc->storage_path);
        $doc->version = (int) $doc->version + 1;
        $doc->save();

        return true;
    }

    private function pruneHistory(RndCollabDocument $doc): void
    {
        $disk = Storage::disk('file_repository');
        $dir = 'collab/' . $doc->project_id . '/history';
        if (! $disk->exists($dir)) {
            return;
        }

        $prefix = 'doc_' . $doc->id . '_v';
        $snapshots = [];
        foreach ($disk->files($dir) as $file) {
            $name = basename($file);
            if (! str_starts_with($name, $prefix)) {
                continue;
            }
            $version = (int) explode('.', substr($name, strlen($prefix)))[0];
            if ($version > 0) {
                $snapshots[$version] = $file;
            }
        }

        if (count($snapshots) <= self::KEEP_VERSIONS) {
            return;
        }

        krsort($snapshots);
        foreach (array_slice($snapshots, self::KEEP_VERSIONS) as $obsolete) {
            $disk->delete($obsolete);
        }
    }
}
