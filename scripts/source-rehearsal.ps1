$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$statePath = Join-Path $projectRoot 'local/source-state.json'
$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
$sourceRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot 'local/source'))
$sourcePath = (Resolve-Path -LiteralPath $state.path).Path
if (-not $sourcePath.StartsWith($sourceRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Source clone must stay inside local/source.' }
if ($state.status -ne 'prepared-not-started') { throw 'Source clone has already been used. Preserve it and prepare a fresh clone before another restore.' }
$sourceEnv = Join-Path $sourcePath '.env'
New-Item -ItemType Directory -Path (Join-Path $sourcePath 'logs') -Force | Out-Null
if (-not (Select-String -LiteralPath $sourceEnv -Pattern '^SOURCE_ADMIN_PASSWORD=' -Quiet)) {
    Add-Content -LiteralPath $sourceEnv -Value ('SOURCE_ADMIN_PASSWORD=' + [Guid]::NewGuid().ToString('N')) -Encoding ASCII
}
if (Get-NetTCPConnection -LocalPort 8082 -State Listen -ErrorAction SilentlyContinue) { throw 'Port 8082 is occupied.' }
$sourceProject = 'kafka-source-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
$env:KAFKA_SOURCE_PATH = $sourcePath.Replace('\','/')
$originalBackup = (Resolve-Path -LiteralPath $state.original_backup).Path
$backupRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot 'local/backups'))
if (-not $originalBackup.StartsWith($backupRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Original source backup must stay inside local/backups.' }
$env:KAFKA_SOURCE_BACKUP = $originalBackup.Replace('\','/')
$composeArgs = @('compose','--project-name',$sourceProject,'--env-file',(Join-Path $sourcePath '.env'),'-f','compose.source.yaml')
function Invoke-SourceDocker {
    param([string[]]$DockerArgs)
    & docker @composeArgs @DockerArgs
    if ($LASTEXITCODE -ne 0) { throw 'Isolated source rehearsal failed. Original FTP and SQL are unchanged.' }
}
if ((docker volume ls --filter "label=com.docker.compose.project=$sourceProject" --format '{{.Name}}')) { throw 'Source project already exists.' }
$state | Add-Member -NotePropertyName project -NotePropertyValue $sourceProject
$state.status = 'preparing'
$state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
try {
    Invoke-SourceDocker @('build','shop')
    Invoke-SourceDocker @('up','-d','--wait','db')
    $internal = docker network inspect ($sourceProject + '_default') --format '{{.Internal}}'
    if ($LASTEXITCODE -ne 0 -or $internal -ne 'true') { throw 'Source network must be internal.' }
    Invoke-SourceDocker @('exec','-T','db','sh','/isolation/import-source.sh')
    Invoke-SourceDocker @('run','--rm','--no-deps','--entrypoint','sh','shop','/isolation/copy-source.sh')
    Invoke-SourceDocker @('run','--rm','--no-deps','--entrypoint','sh','shop','/isolation/configure-source.sh')
    Invoke-SourceDocker @('run','--rm','--no-deps','--entrypoint','chown','shop','-R','www-data:www-data','/var/www/html')
    Invoke-SourceDocker @('up','-d','shop','preview')
    $state.status = 'running-isolated-awaiting-verification'
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    Write-Host 'Source copy: http://localhost:8082/ - admin: /admin-source/. Payments, emails and external traffic are disabled.'
} catch {
    $state.status = 'failed'
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    & docker @composeArgs stop shop preview | Out-Null
    throw
}
