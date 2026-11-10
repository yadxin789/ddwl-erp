# P1-04 端到端验收：一条命令跑完三段链路
#
#   段一 计划→完工入库：工单下达 → 领料 → 报工 → 完工入库
#   段二 订单→收款    ：订单确认 → 出货 → 开票 500 → 收款核销 → AR=0
#   段三 采购→付款    ：采购确认 → 收货 → IQC 合格 → 账单 500 → 付款核销
#
# 用法（项目根目录）：
#   pwsh -ExecutionPolicy Bypass -File .\acceptance-smoke.ps1
#   pwsh -ExecutionPolicy Bypass -File .\acceptance-smoke.ps1 -KeepData
#
# 说明：
#   1) 先跑 AcceptanceSeeder（按固定编码 ACC-* 前置清理，重复跑不会累积脏数据）；
#   2) 再跑 tests/Feature/E2E/BusinessChainTest，断言 AR=0、库存=流水、完工入库 1 笔；
#   3) 任一段失败会以非 0 退出码返回，可直接接 CI；
#   4) 默认跑完清理验收数据（避免留在演示库影响其他测试）；加 -KeepData 可保留，供手工点页面。

param(
    [switch]$KeepData
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) { $php = 'php' }

Set-Location -LiteralPath $root

Write-Host '== 1/3 重建验收种子数据（AcceptanceSeeder）==' -ForegroundColor Cyan
& $php artisan db:seed --class="Database\Seeders\AcceptanceSeeder" --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "验收种子失败，exit=$LASTEXITCODE" -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host '== 2/3 跑三段链路（BusinessChainTest）==' -ForegroundColor Cyan
& $php artisan test --filter=BusinessChainTest
$code = $LASTEXITCODE

if (-not $KeepData) {
    Write-Host '== 3/3 清理验收数据（erp:acceptance:clean）==' -ForegroundColor Cyan
    & $php artisan erp:acceptance:clean
    if ($LASTEXITCODE -ne 0) {
        Write-Host "验收数据清理失败，exit=$LASTEXITCODE" -ForegroundColor Red
    }
} else {
    Write-Host '已保留验收数据（ACC-*），可按 SO-ACC-001 / PO-ACC-001 / WO-ACC-001 手工验收。' -ForegroundColor Yellow
}

if ($code -eq 0) {
    Write-Host '端到端验收通过：AR=0、库存=流水、工单完工入库 1 笔。' -ForegroundColor Green
} else {
    Write-Host "端到端验收失败，exit=$code" -ForegroundColor Red
}
exit $code
