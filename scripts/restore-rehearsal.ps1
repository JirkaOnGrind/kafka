param([Parameter(Mandatory=$true)][string]$BackupPath)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$backupRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot 'local/backups'))
$resolvedBackup = (Resolve-Path -LiteralPath $BackupPath).Path
if (-not $resolvedBackup.StartsWith($backupRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Backup must be inside local/backups.' }
foreach ($file in @('database.sql','shopfiles.tar.gz','.env','checksums.csv')) {
    if (-not (Test-Path -LiteralPath (Join-Path $resolvedBackup $file))) { throw "Missing backup component: $file" }
}
foreach ($entry in (Import-Csv -LiteralPath (Join-Path $resolvedBackup 'checksums.csv'))) {
    $fileName = Split-Path -Leaf $entry.Path
    $actual = (Get-FileHash -LiteralPath (Join-Path $resolvedBackup $fileName) -Algorithm SHA256).Hash
    if ($actual -ne $entry.Hash) { throw "Backup checksum mismatch: $fileName" }
}
$restoreProject = 'kafka-restore-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
$env:KAFKA_BACKUP_PATH = $resolvedBackup.Replace('\','/')
$composeArgs = @('compose','--project-name',$restoreProject,'--env-file',(Join-Path $resolvedBackup '.env'),'-f','compose.restore.yaml')
function Invoke-RestoreDocker {
    param([string[]]$DockerArgs)
    & docker @composeArgs @DockerArgs
    if ($LASTEXITCODE -ne 0) { throw 'Restore operation failed; original local shop is unchanged. The isolated volumes are preserved.' }
}
# A fresh project is mandatory: never extract or import into an existing volume.
if ((docker volume ls --filter "label=com.docker.compose.project=$restoreProject" --format '{{.Name}}')) { throw 'Restore project already exists.' }
if (Get-NetTCPConnection -LocalPort 8081 -State Listen -ErrorAction SilentlyContinue) { throw 'Port 8081 is already in use.' }
$state = [ordered]@{ project=$restoreProject; backup=$resolvedBackup; url='http://localhost:8081/'; status='preparing'; created=(Get-Date).ToString('o') }
$statePath = Join-Path $projectRoot 'local/restore-state.json'
$state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
try {
    Invoke-RestoreDocker @('up','-d','--wait','db')
    $network = docker network inspect ($restoreProject + '_default') --format '{{.Internal}}'
    if ($LASTEXITCODE -ne 0 -or $network -ne 'true') { throw 'Restore network must be internal.' }
    Invoke-RestoreDocker @('run','--rm','--no-deps','--entrypoint','tar','shop','-xzf','/backup/shopfiles.tar.gz','-C','/var/www/html')
    Invoke-RestoreDocker @('exec','-T','db','sh','/rehearsal/import.sh')
    Invoke-RestoreDocker @('run','--rm','--no-deps','--entrypoint','php','shop','/rehearsal/configure.php')
    Invoke-RestoreDocker @('run','--rm','--no-deps','--entrypoint','chown','shop','-R','www-data:www-data','/var/www/html')
    Invoke-RestoreDocker @('up','-d','shop','preview')
    $state.status = 'running-awaiting-browser-verification'
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    Write-Host 'Isolated restored shop: http://localhost:8081/ - admin: /admin-dev/'
} catch {
    $state.status = 'failed'
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    & docker @composeArgs stop shop preview | Out-Null
    throw
}
