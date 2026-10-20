param(
    [Parameter(Mandatory=$true)][string]$SnapshotPath,
    [Parameter(Mandatory=$true)][string]$ImportScript,
    [string[]]$ImportArguments=@(),
    [string]$LabPath=''
)
# Use only for an import already authorized by the user. This wrapper grants no migration/production authority.
# The chosen existing adapter remains responsible for PS1.6 -> PS9 schema migration and source isolation.
$ErrorActionPreference='Stop'
if ((Get-PSDrive C).Free -lt 4GB) { throw 'Import not started: preserve at least 4 GiB host disk reserve.' }
$root=Split-Path -Parent $PSScriptRoot
if (!$LabPath) { $LabPath=Join-Path $root 'local/private/migration-lab-20261018' }
$lab=(Resolve-Path -LiteralPath $LabPath).Path
$privateRoot=[IO.Path]::GetFullPath((Join-Path $root 'local/private')) + [IO.Path]::DirectorySeparatorChar
if (!$lab.StartsWith($privateRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Lab must stay in local/private.' }
$snapshot=(Resolve-Path -LiteralPath $SnapshotPath).Path
$adapter=(Resolve-Path -LiteralPath $ImportScript).Path
if (![IO.Path]::GetExtension($snapshot).Equals('.sql',[StringComparison]::OrdinalIgnoreCase) -or !$adapter.StartsWith($root + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetExtension($adapter) -ne '.ps1') { throw 'Supply the private SQL snapshot and an existing project PowerShell import adapter.' }
$adapterCommand=Get-Command -Name $adapter
if (!$adapterCommand.Parameters.ContainsKey('SnapshotPath') -or !$adapterCommand.Parameters.ContainsKey('LabPath')) { throw 'The import adapter must declare SnapshotPath and LabPath parameters so the recorded input is the actual supplied source.' }
$dc=@('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
$container=(& docker @dc ps -q target | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or !$container) { throw 'Target must be running.' }
$networks=& docker inspect $container --format '{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'
if($LASTEXITCODE -ne 0){throw 'Cannot inspect target networks.'}
foreach($network in $networks){
    $internal=& docker network inspect $network.Trim() --format '{{.Internal}}'
    if($LASTEXITCODE -ne 0 -or $internal -ne 'true'){throw 'All application networks must remain internal.'}
}
$run='import-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
$backup=Join-Path $lab ('import-runs/' + $run)
New-Item -ItemType Directory -Path $backup | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'backup-isolated-database.sh') -Destination $backup
$identity=(Get-FileHash -LiteralPath $snapshot -Algorithm SHA256).Hash
[ordered]@{snapshot=$snapshot;sha256=$identity;adapter=$adapter;started=(Get-Date).ToString('o')} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $backup 'input.json') -Encoding UTF8
& docker @dc exec -T target-db sh ('/lab/import-runs/' + $run + '/backup-isolated-database.sh') ('/lab/import-runs/' + $run)
if($LASTEXITCODE -ne 0){throw 'Private pre-import database backup failed; import was not started.'}
if ((Get-PSDrive C).Free -lt 4GB) { throw 'Backup retained; import not started because the 4 GiB disk reserve would be breached.' }
# Parameters are passed as an array, never evaluated as shell text. The same hashed input is supplied to the adapter.
$global:LASTEXITCODE=0
& $adapter -SnapshotPath $snapshot -LabPath $lab @ImportArguments
if ($LASTEXITCODE -ne 0) { throw 'Import adapter failed; private pre-import checkpoint retained.' }
if ((Get-FileHash -LiteralPath $snapshot -Algorithm SHA256).Hash -ne $identity) { throw 'The original supplied SQL changed during import; acceptance stopped.' }
& (Join-Path $PSScriptRoot 'complete-isolated-import.ps1') -LabPath $lab -ImportIdentity ('sql-sha256:' + $identity)
if ($LASTEXITCODE -ne 0) { throw 'Imported data did not pass mandatory fresh SEO acceptance.' }
