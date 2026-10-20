param([switch]$Apply)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$OutputEncoding = [Console]::OutputEncoding
$env:PYTHONUTF8 = '1'
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
function Invoke-CatalogDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Category membership operation failed; private backup/diagnostics retained.' }
    return $result
}
$container = (Invoke-CatalogDocker ($dc + @('ps','-q','target')) | Out-String).Trim()
$database = (Invoke-CatalogDocker ($dc + @('ps','-q','target-db')) | Out-String).Trim()
if (!$container -or !$database) { throw 'Start the isolated target first.' }
foreach ($network in (Invoke-CatalogDocker @('inspect',$container,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-CatalogDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
$run = 'import-category-products-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $lab ('import-runs/' + $run)
$runtime = '/lab/import-runs/' + $run
New-Item -ItemType Directory -Path $private | Out-Null
foreach ($name in @('export-category-audit.php','apply-category-assignments.php','backup-client-categories.sh','plan-category-assignments.py','ensure-category-compatibility.php')) { Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination $private }
Invoke-CatalogDocker @('exec',$container,'php','-l',($runtime + '/apply-category-assignments.php')) | Out-Null
if ($Apply) {
    # Existing backup helper accepts the same fixed /tmp/kafka-import-categories- prefix.
    $dbRuntime = '/tmp/kafka-import-categories-products-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
    Invoke-CatalogDocker @('exec',$database,'sh',($runtime + '/backup-client-categories.sh'),$dbRuntime) | Out-Null
    Invoke-CatalogDocker @('cp',($database + ':' + $dbRuntime + '/database-before.sql'),(Join-Path $private 'database-before.sql')) | Out-Null
    Get-FileHash -LiteralPath (Join-Path $private 'database-before.sql') -Algorithm SHA256 | Select-Object Hash |
        ConvertTo-Json | Set-Content -LiteralPath (Join-Path $private 'backup-sha256.json') -Encoding UTF8
    Invoke-CatalogDocker @('exec',$container,'tar','-czf',($runtime + '/kafkahome-before.tar.gz'),'-C','/var/www/html','modules/kafkahome') | Out-Null
    Invoke-CatalogDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome'),($container + ':/var/www/html/modules/')) | Out-Null
    Invoke-CatalogDocker @('exec',$container,'php',($runtime + '/ensure-category-compatibility.php')) | Set-Content -LiteralPath (Join-Path $private 'compatibility-hook.json') -Encoding UTF8
}
$catalog = Invoke-CatalogDocker @('exec',$container,'php',($runtime + '/export-category-audit.php'))
[IO.File]::WriteAllText((Join-Path $private 'catalog-before.json'), ($catalog -join "`n"), (New-Object System.Text.UTF8Encoding($false)))
& python (Join-Path $private 'plan-category-assignments.py') --catalog (Join-Path $private 'catalog-before.json') --output (Join-Path $private 'assignment-plan.json')
if ($LASTEXITCODE -ne 0) { throw 'Classification contains unresolved products or invalid source identities; nothing applied.' }
Write-Output ('Private category analysis: ' + $private)
if ($Apply) {
    $result = Invoke-CatalogDocker @('exec',$container,'php',($runtime + '/apply-category-assignments.php'))
    $result | Set-Content -LiteralPath (Join-Path $private 'application-result.json') -Encoding UTF8
    $after = Invoke-CatalogDocker @('exec',$container,'php',($runtime + '/export-category-audit.php'))
    [IO.File]::WriteAllText((Join-Path $private 'catalog-after.json'), ($after -join "`n"), (New-Object System.Text.UTF8Encoding($false)))
    & python (Join-Path $private 'plan-category-assignments.py') --catalog (Join-Path $private 'catalog-after.json') --output (Join-Path $private 'repeat-plan.json')
    if ($LASTEXITCODE -ne 0) { throw 'Post-application classification audit failed.' }
    $repeat = Get-Content -LiteralPath (Join-Path $private 'repeat-plan.json') -Raw | ConvertFrom-Json
    if ($repeat.assignments.Count -ne 0 -or $repeat.missing_navigation_after -ne 0) { throw 'Classification is not complete/idempotent.' }
    # Keep original before/after count evidence; the repeat plan writes its own adjacent CSVs.
    & python (Join-Path $private 'plan-category-assignments.py') --catalog (Join-Path $private 'catalog-before.json') --output (Join-Path $private 'assignment-plan.json')
    if ($LASTEXITCODE -ne 0) { throw 'Original evidence regeneration failed.' }
    & (Join-Path $PSScriptRoot 'complete-isolated-import.ps1') -LabPath $lab -ImportIdentity $run
}
