param(
    [switch]$SkipTests,
    [switch]$SkipLint
)

# DDWL-ERP 本地 CI 门禁（P2-01）
# 顺序：PHP 语法 → route:list → erp:guard:check → 全量 artisan test
# 任一步失败立即以非 0 退出，可直接被 pre-commit / 计划任务 / 流水线调用。

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$php = $env:ERP_PHP
if (-not $php) {
    if (Test-Path 'C:\xampp\php\php.exe') { $php = 'C:\xampp\php\php.exe' }
    elseif (Get-Command php -ErrorAction SilentlyContinue) { $php = (Get-Command php).Source }
    else { Write-Host '[FAIL] 找不到 php，可设置环境变量 ERP_PHP 指向 php.exe' -ForegroundColor Red; exit 1 }
}

Set-Location -LiteralPath $root

function Fail($message) {
    Write-Host "[FAIL] $message" -ForegroundColor Red
    exit 1
}

function Pass($message) {
    Write-Host "[ OK ] $message" -ForegroundColor Green
}

Write-Host "== DDWL-ERP CI 门禁 =="
Write-Host "PHP : $php"
Write-Host "Root: $root"

# 1. PHP 语法
if (-not $SkipLint) {
    $dirs = @('app', 'routes', 'database', 'tests', 'bootstrap', 'config')
    foreach ($dir in $dirs) {
        $path = Join-Path $root $dir
        if (-not (Test-Path -LiteralPath $path)) { continue }
        $files = Get-ChildItem -LiteralPath $path -Filter '*.php' -Recurse -File
        $checked = 0
        $bad = @()
        foreach ($file in $files) {
            & $php -l $file.FullName *> $null
            if ($LASTEXITCODE -ne 0) { $bad += $file.FullName }
            $checked++
        }
        if ($bad.Count -gt 0) {
            $bad | ForEach-Object { & $php -l $_ }
            Fail "PHP 语法错误：$($bad.Count) 个文件（$dir）"
        }
        Pass "PHP 语法 $dir（$checked 个文件）"
    }
} else {
    Write-Host '[SKIP] PHP 语法（-SkipLint）' -ForegroundColor Yellow
}

# 2. 路由注册
& $php artisan route:list *> $null
if ($LASTEXITCODE -ne 0) { Fail 'route:list 失败（路由注册层面有错误）' }
$routeCount = (& $php artisan route:list --json | ConvertFrom-Json).Count
Pass "route:list（$routeCount 条路由）"

# 3. 岗位/菜单/权限守卫
$guardOutput = & $php artisan erp:guard:check 2>&1
if ($LASTEXITCODE -ne 0) {
    $guardOutput | Select-Object -Last 20 | ForEach-Object { Write-Host $_ }
    Fail 'erp:guard:check 失败'
}
$guardSummary = ($guardOutput | Where-Object { $_ -match '权限库' } | Select-Object -Last 1)
$guardPass = ($guardOutput | Where-Object { $_ -match 'M1~M7 全部通过' } | Select-Object -Last 1)
Pass "erp:guard:check：$guardSummary；$guardPass"

# 4. 全量回归
if (-not $SkipTests) {
    & $php artisan test
    if ($LASTEXITCODE -ne 0) { Fail 'artisan test 失败（有测试红）' }
    Pass 'artisan test（全量）'
} else {
    Write-Host '[SKIP] artisan test（-SkipTests）' -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'CI GATE: PASS' -ForegroundColor Green
exit 0
