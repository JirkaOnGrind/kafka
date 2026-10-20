param([switch]$Apply)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
function Invoke-QuoteDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Custom production deployment failed. Existing backups are preserved.' }
    return $result
}
$container = (Invoke-QuoteDocker @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'),'ps','-q','target') | Out-String).Trim()
if (!$container) { throw 'Start the isolated target first.' }
foreach ($network in (Invoke-QuoteDocker @('inspect',$container,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-QuoteDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
if (!$Apply) { Write-Output 'Plan: privately back up frontend, deploy Kafka module/theme to isolated 8083, register quote hook and clear templates. No catalog import.'; return }
$run = 'custom-production-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $root ('local/private/' + $run)
New-Item -ItemType Directory -Path $private | Out-Null
$archive = '/tmp/' + $run + '.tar.gz'
Invoke-QuoteDocker @('exec',$container,'tar','-czf',$archive,'-C','/var/www/html','modules/kafkahome','themes/kafka') | Out-Null
Invoke-QuoteDocker @('cp',($container + ':' + $archive),(Join-Path $private 'frontend-before.tar.gz')) | Out-Null
Get-FileHash -LiteralPath (Join-Path $private 'frontend-before.tar.gz') -Algorithm SHA256 | Select-Object Hash | ConvertTo-Json | Set-Content (Join-Path $private 'sha256.json') -Encoding UTF8
Invoke-QuoteDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome'),($container + ':/var/www/html/modules/')) | Out-Null
Invoke-QuoteDocker @('cp',(Join-Path $root 'prestashop/themes/kafka'),($container + ':/var/www/html/themes/')) | Out-Null
Invoke-QuoteDocker @('cp',(Join-Path $PSScriptRoot 'deploy-custom-production.php'),($container + ':/tmp/deploy-custom-production.php')) | Out-Null
Invoke-QuoteDocker @('exec',$container,'php','-l','/var/www/html/modules/kafkahome/classes/CustomProduction.php') | Out-Null
Invoke-QuoteDocker @('exec',$container,'php','/tmp/deploy-custom-production.php')
Write-Output ('Private frontend backup: ' + $private)
