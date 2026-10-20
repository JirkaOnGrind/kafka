param(
    [string]$LabPath = '',
    [string]$ImportIdentity = 'existing-local-data-no-import',
    [string]$Python = 'python'
)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$OutputEncoding = [Console]::OutputEncoding
$projectRoot = Split-Path -Parent $PSScriptRoot
if (!$LabPath) { $LabPath=Join-Path $projectRoot 'local/private/migration-lab-20261018' }
$lab = (Resolve-Path -LiteralPath $LabPath).Path
$allowed = [IO.Path]::GetFullPath((Join-Path $projectRoot 'local/private')) + [IO.Path]::DirectorySeparatorChar
if (!$lab.StartsWith($allowed, [StringComparison]::OrdinalIgnoreCase)) { throw 'Lab must be inside local/private.' }
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
function Invoke-SeoDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Local SEO operation failed; private backups and partial evidence are retained.' }
    return $result
}
$container = (Invoke-SeoDocker ($dc + @('ps','-q','target')) | Out-String).Trim()
if (!$container) { throw 'Start the isolated target first.' }
$networkNames = Invoke-SeoDocker @('inspect',$container,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}')
foreach ($network in $networkNames) {
    if ($network.Trim() -and (Invoke-SeoDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target application network must be internal.' }
}
# Validate domain and public-only export BEFORE deploying anything. No SQL is imported by this procedure.
$run = 'seo-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $lab ('seo-runs/' + $run)
$evidence = Join-Path $projectRoot ('migration/evidence/' + $run)
New-Item -ItemType Directory -Path $private,$evidence | Out-Null
trap {
    if ($evidence -and (Test-Path -LiteralPath $evidence)) {
        if (!(Test-Path -LiteralPath (Join-Path $evidence 'REPORT.md'))) {
            @('# Local SEO acceptance failed', '', ('Run: ' + $run), ('Target: http://localhost:8083/'), '',
              'The procedure stopped before acceptance. No successful audit is claimed.',
              ('Failure: ' + $_.Exception.Message), ('Private backup and diagnostics: ' + $private),
              'Fix the reported step and run again into a new evidence folder; preserve this failed run.') |
              Set-Content -LiteralPath (Join-Path $evidence 'REPORT.md') -Encoding UTF8
        }
    }
    break
}
$runtime = '/lab/seo-runs/' + $run
$helpers = @('export-seo-catalog.php','apply-migration-seo.php','migration-seo-policy.json','localize-page-titles.php','generate-local-sitemaps.php','backup-seo-files.sh','clear-local-seo-cache.php','check-local-seo-runtime.php')
foreach ($helper in $helpers) { Copy-Item -LiteralPath (Join-Path $PSScriptRoot $helper) -Destination $private }
$utf8 = New-Object System.Text.UTF8Encoding($false)
$before = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/export-seo-catalog.php'))
[IO.File]::WriteAllText((Join-Path $private 'catalog-before.json'), ($before -join "`n"), $utf8)
Invoke-SeoDocker @('exec',$container,'sh',($runtime + '/backup-seo-files.sh'),$runtime) | Out-Null
Invoke-SeoDocker @('cp',(Join-Path $projectRoot 'prestashop/modules/kafkaseo'),($container + ':/var/www/html/modules/')) | Out-Null
Invoke-SeoDocker @('exec',$container,'php','-l','/var/www/html/modules/kafkaseo/kafkaseo.php') | Out-Null
$applied = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/apply-migration-seo.php'),'--apply',('--backup=' + $runtime + '/settings-before.json'))
[IO.File]::WriteAllText((Join-Path $evidence 'managed-settings.json'), ($applied -join "`n"), $utf8)
$titles = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/localize-page-titles.php'),'--apply',('--backup=' + $runtime + '/czech-titles-before.json'))
[IO.File]::WriteAllText((Join-Path $evidence 'czech-titles.json'), ($titles -join "`n"), $utf8)
# A second process proves no managed setting remains to be changed.
$repeat = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/apply-migration-seo.php'))
[IO.File]::WriteAllText((Join-Path $evidence 'idempotence.json'), ($repeat -join "`n"), $utf8)
$repeatState=($repeat -join "`n") | ConvertFrom-Json
if ($repeatState.count -ne 0 -or !$repeatState.hooks_ready -or !$repeatState.link_override_loaded) { throw 'Managed settings, hooks or compatibility override are not ready/idempotent.' }
# Clear only compiled Smarty templates; preserve catalog, sessions and every Docker volume.
Invoke-SeoDocker @('exec',$container,'php',($runtime + '/clear-local-seo-cache.php')) | Out-Null
$sitemaps = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/generate-local-sitemaps.php'))
[IO.File]::WriteAllText((Join-Path $evidence 'sitemap-generation.json'), ($sitemaps -join "`n"), $utf8)
$nativeChecks = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/check-local-seo-runtime.php'))
[IO.File]::WriteAllText((Join-Path $evidence 'native-runtime-checks.json'), ($nativeChecks -join "`n"), $utf8)
$after = Invoke-SeoDocker @('exec',$container,'php',($runtime + '/export-seo-catalog.php'))
[IO.File]::WriteAllText((Join-Path $evidence 'target-public-catalog.json'), ($after -join "`n"), $utf8)
$manifest = [ordered]@{run=$run;started=(Get-Date).ToString('o');target='http://localhost:8083/';import_identity=$ImportIdentity;private_backup=$private;baseline='migration/evidence/seo-20261019';status='auditing';helpers=@()}
foreach ($helper in $helpers) { $manifest.helpers += @{path=('scripts/' + $helper);sha256=(Get-FileHash -LiteralPath (Join-Path $PSScriptRoot $helper) -Algorithm SHA256).Hash} }
foreach ($relative in @('scripts/complete-isolated-import.ps1','scripts/audit-local-seo.py','scripts/audit-seo-migration.py','prestashop/modules/kafkaseo/kafkaseo.php','prestashop/modules/kafkaseo/override/classes/Link.php')) {
    $manifest.helpers += @{path=$relative;sha256=(Get-FileHash -LiteralPath (Join-Path $projectRoot $relative) -Algorithm SHA256).Hash}
}
$manifest.baseline_sha256=@()
foreach ($name in @('source-public-catalog.json','source-http.jsonl','crawl-plan.json','pagination-checks.json')) {
    $manifest.baseline_sha256 += @{path=('migration/evidence/seo-20261019/' + $name);sha256=(Get-FileHash -LiteralPath (Join-Path $projectRoot ('migration/evidence/seo-20261019/' + $name)) -Algorithm SHA256).Hash}
}
$backupHashes=@()
foreach ($name in @('settings-before.json','files-before.tar.gz','files-before.list','catalog-before.json','czech-titles-before.json')) {
    $file=Join-Path $private $name
    if(Test-Path -LiteralPath $file){$backupHashes += @{file=$name;sha256=(Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash}}
}
$backupHashes | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $private 'backup-sha256.json') -Encoding UTF8
$manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $evidence 'run.json') -Encoding UTF8
$auditStderr = Join-Path $private 'audit-stderr.log'
$previousErrorPreference = $ErrorActionPreference
try {
    $ErrorActionPreference = 'Continue'
    & $Python -B (Join-Path $PSScriptRoot 'audit-local-seo.py') --output $evidence --before (Join-Path $private 'catalog-before.json') 2> $auditStderr
    $auditExit = $LASTEXITCODE
} finally { $ErrorActionPreference = $previousErrorPreference }
$manifest.audit_exit_code = $auditExit
$manifest.status = if ($auditExit -eq 0) { 'passed' } else { 'failed-see-report' }
$manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $evidence 'run.json') -Encoding UTF8
Write-Output ('Fresh SEO report: ' + (Join-Path $evidence 'REPORT.md'))
Write-Output ('Private rollback backup: ' + $private)
if ($auditExit -ne 0) { throw ('Fresh SEO audit did not pass (exit ' + $auditExit + '). Import is not accepted; inspect REPORT.md and private diagnostics ' + $auditStderr) }
