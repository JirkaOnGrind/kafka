param([switch]$Apply)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
function Invoke-FormDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw ('Checkout deployment failed: ' + ($Arguments -join ' ') + '. Private backups are preserved.') }
    return $result
}
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
$target = (Invoke-FormDocker ($dc + @('ps','-q','target')) | Out-String).Trim()
$db = (Invoke-FormDocker ($dc + @('ps','-q','target-db')) | Out-String).Trim()
if (!$target -or !$db) { throw 'Run start-migrace.bat first.' }
foreach ($network in (Invoke-FormDocker @('inspect',$target,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-FormDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
Invoke-FormDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome/kafkahome.php'),($target + ':/tmp/kafka-checkout-module.php')) | Out-Null
Invoke-FormDocker @('exec',$target,'php','-l','/tmp/kafka-checkout-module.php') | Out-Null
if (!$Apply) { Write-Output 'Plan: private DB/frontend backup, deploy Kafka forms to isolated 8083, disable partner offers, register native hooks, clear cache. No import.'; return }
$run = 'checkout-ux-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $lab ('import-runs/' + $run)
New-Item -ItemType Directory -Path $private | Out-Null
$containerDir = '/lab/import-runs/' + $run
Invoke-FormDocker @('cp',(Join-Path $PSScriptRoot 'backup-checkout-ux.sh'),($db + ':/tmp/backup-checkout.sh')) | Out-Null
$dbDir = '/tmp/kafka-' + $run
Invoke-FormDocker @('exec',$db,'sh','/tmp/backup-checkout.sh',$dbDir) | Out-Null
Invoke-FormDocker @('cp',($db + ':' + $dbDir + '/database-before.sql'),(Join-Path $private 'database-before.sql')) | Out-Null
Invoke-FormDocker @('exec',$target,'tar','-czf',($containerDir + '/frontend-before.tar.gz'),'-C','/var/www/html','modules/kafkahome','themes/kafka') | Out-Null
Get-FileHash -LiteralPath (Join-Path $private 'database-before.sql'),(Join-Path $private 'frontend-before.tar.gz') -Algorithm SHA256 | Select-Object Hash,Path | ConvertTo-Json | Set-Content (Join-Path $private 'sha256.json') -Encoding UTF8
Invoke-FormDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome'),($target + ':/var/www/html/modules/')) | Out-Null
Invoke-FormDocker @('cp',(Join-Path $root 'prestashop/themes/kafka'),($target + ':/var/www/html/themes/')) | Out-Null
Invoke-FormDocker @('cp',(Join-Path $PSScriptRoot 'configure-checkout-ux.php'),($target + ':/tmp/configure-checkout-ux.php')) | Out-Null
Invoke-FormDocker @('exec',$target,'php','-l','/var/www/html/modules/kafkahome/kafkahome.php') | Out-Null
Invoke-FormDocker @('exec',$target,'php','/tmp/configure-checkout-ux.php')
Write-Output ('Private backup: ' + $private)
