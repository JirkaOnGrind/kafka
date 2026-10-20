param([switch]$Apply)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$OutputEncoding = [Console]::OutputEncoding
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
function Invoke-LocalDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Local category operation failed; backups are preserved.' }
    return $result
}
$container = (Invoke-LocalDocker ($dc + @('ps','-q','target')) | Out-String).Trim()
$database = (Invoke-LocalDocker ($dc + @('ps','-q','target-db')) | Out-String).Trim()
if (!$container -or !$database) { throw 'Start the isolated target first.' }
foreach ($network in (Invoke-LocalDocker @('inspect',$container,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-LocalDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
$run = 'import-categories-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $lab ('import-runs/' + $run)
New-Item -ItemType Directory -Path $private | Out-Null
$runtime = '/lab/import-runs/' + $run
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'apply-client-categories.php') -Destination $private
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'check-client-categories.php') -Destination $private
Copy-Item -LiteralPath (Join-Path $root 'prestashop/modules/kafkahome/data/sections.json') -Destination $private
$baseline = Get-Content -LiteralPath (Join-Path $root 'migration/evidence/seo-20261019/source-public-catalog.json') -Raw | ConvertFrom-Json
$reserved = @($baseline.rows | Where-Object { $_.entity -eq 'category' } | ForEach-Object { [int]$_.id } | Sort-Object -Unique)
$outside = Import-Csv -LiteralPath (Join-Path $root 'migration/evidence/seo-20261019/live-entities-outside-snapshot.csv')
foreach ($row in $outside) {
    $path = ([Uri]$row.source_url).AbsolutePath
    if ($path -notlike '*.html' -and $path -match '^/(?:cs|sk|de)/(\d+)-[^/]+$') { $reserved += [int]$Matches[1] }
}
$reserved = @($reserved | Sort-Object -Unique)
[IO.File]::WriteAllText((Join-Path $private 'reserved-category-ids.json'), ($reserved | ConvertTo-Json), (New-Object System.Text.UTF8Encoding($false)))
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'backup-client-categories.sh') -Destination $private
Invoke-LocalDocker @('exec',$container,'php','-l',($runtime + '/apply-client-categories.php')) | Out-Null
if ($Apply) {
    $databaseRuntime = '/tmp/kafka-' + $run
    Invoke-LocalDocker @('exec',$database,'sh',($runtime + '/backup-client-categories.sh'),$databaseRuntime) | Out-Null
    Invoke-LocalDocker @('cp',($database + ':' + $databaseRuntime + '/database-before.sql'),(Join-Path $private 'database-before.sql')) | Out-Null
    Invoke-LocalDocker @('exec',$container,'tar','-czf',($runtime + '/frontend-before.tar.gz'),'-C','/var/www/html','modules/kafkahome','themes/kafka') | Out-Null
    Get-FileHash -LiteralPath (Join-Path $private 'database-before.sql'),(Join-Path $private 'frontend-before.tar.gz') -Algorithm SHA256 |
        Select-Object @{Name='file';Expression={Split-Path -Leaf $_.Path}},Hash | ConvertTo-Json | Set-Content (Join-Path $private 'backup-sha256.json') -Encoding UTF8
}
if ($Apply) {
    Invoke-LocalDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome'),($container + ':/var/www/html/modules/')) | Out-Null
    Invoke-LocalDocker @('cp',(Join-Path $root 'prestashop/themes/kafka'),($container + ':/var/www/html/themes/')) | Out-Null
}
$arguments = @('exec',$container,'php',($runtime + '/apply-client-categories.php'))
if ($Apply) { $arguments += '--apply' }
$result = Invoke-LocalDocker $arguments
$result | Set-Content (Join-Path $private 'category-result.json') -Encoding UTF8
Write-Output ('Category result: ' + (Join-Path $private 'category-result.json'))
if ($Apply) {
    $checks = Invoke-LocalDocker @('exec',$container,'php',($runtime + '/check-client-categories.php'))
    $checks | Set-Content (Join-Path $private 'category-checks.json') -Encoding UTF8
    # Complete the taxonomy together with product associations; that procedure always ends in fresh SEO acceptance.
    & (Join-Path $PSScriptRoot 'repair-category-memberships.ps1') -Apply
}
