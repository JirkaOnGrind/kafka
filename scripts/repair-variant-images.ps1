param([switch]$Apply)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
function Invoke-VariantDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Variant repair failed; private backups are preserved.' }
    return $result
}
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
$target = (Invoke-VariantDocker ($dc + @('ps','-q','target')) | Out-String).Trim()
$database = (Invoke-VariantDocker ($dc + @('ps','-q','target-db')) | Out-String).Trim()
if (!$target -or !$database) { throw 'Start the isolated target first.' }
foreach ($network in (Invoke-VariantDocker @('inspect',$target,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-VariantDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
foreach ($file in @('apply-variant-images.php','variant-image-map.json','register-variant-image-hooks.php','check-variant-images.php')) {
    Invoke-VariantDocker @('cp',(Join-Path $PSScriptRoot $file),($target + ':/tmp/' + $file)) | Out-Null
}
Invoke-VariantDocker @('exec',$target,'php','-l','/tmp/apply-variant-images.php') | Out-Null
Invoke-VariantDocker @('exec',$target,'php','/tmp/apply-variant-images.php','--map=/tmp/variant-image-map.json')
if (!$Apply) { return }
$run = 'import-variant-images-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$private = Join-Path $lab ('import-runs/' + $run)
New-Item -ItemType Directory -Path $private | Out-Null
$containerDir = '/lab/import-runs/' + $run
Invoke-VariantDocker @('cp',(Join-Path $PSScriptRoot 'backup-variant-images.sh'),($database + ':/tmp/backup-variant-database.sh')) | Out-Null
$databaseDir = '/tmp/kafka-' + $run
Invoke-VariantDocker @('exec',$database,'sh','/tmp/backup-variant-database.sh',$databaseDir) | Out-Null
Invoke-VariantDocker @('cp',($database + ':' + $databaseDir + '/database-before.sql'),(Join-Path $private 'database-before.sql')) | Out-Null
Invoke-VariantDocker @('exec',$target,'tar','-czf',($containerDir + '/frontend-before.tar.gz'),'-C','/var/www/html','modules/kafkahome','themes/kafka') | Out-Null
Get-FileHash -LiteralPath (Join-Path $private 'database-before.sql'),(Join-Path $private 'frontend-before.tar.gz') -Algorithm SHA256 | Select-Object Hash,Path | ConvertTo-Json | Set-Content (Join-Path $private 'sha256.json') -Encoding UTF8
Invoke-VariantDocker @('exec',$target,'mkdir','-p','/var/www/html/modules/kafkahome/classes') | Out-Null
Invoke-VariantDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome/kafkahome.php'),($target + ':/var/www/html/modules/kafkahome/kafkahome.php')) | Out-Null
Invoke-VariantDocker @('cp',(Join-Path $root 'prestashop/modules/kafkahome/classes/VariantImages.php'),($target + ':/var/www/html/modules/kafkahome/classes/VariantImages.php')) | Out-Null
Invoke-VariantDocker @('cp',(Join-Path $root 'prestashop/themes/kafka/assets/css/kafka.css'),($target + ':/var/www/html/themes/kafka/assets/css/kafka.css')) | Out-Null
Invoke-VariantDocker @('cp',(Join-Path $root 'prestashop/themes/kafka/templates/_partials/head.tpl'),($target + ':/var/www/html/themes/kafka/templates/_partials/head.tpl')) | Out-Null
Invoke-VariantDocker @('exec',$target,'php','-l','/var/www/html/modules/kafkahome/kafkahome.php') | Out-Null
Invoke-VariantDocker @('exec',$target,'php','-l','/var/www/html/modules/kafkahome/classes/VariantImages.php') | Out-Null
Invoke-VariantDocker @('exec',$target,'php','/tmp/apply-variant-images.php','--map=/tmp/variant-image-map.json',('--backup=' + $containerDir + '/links-before.json'),'--apply')
Invoke-VariantDocker @('exec',$target,'php','/tmp/register-variant-image-hooks.php')
$validation = Invoke-VariantDocker @('exec',$target,'php','/tmp/check-variant-images.php','--map=/tmp/variant-image-map.json')
$validation | Set-Content (Join-Path $private 'native-checks.json') -Encoding UTF8
$checked = ($validation -join "`n") | ConvertFrom-Json
$checked | Select-Object products,combinations_checked,native_presentations_checked,failures | ConvertTo-Json -Depth 4
Write-Output ('Private database/module/link backup: ' + $private)
