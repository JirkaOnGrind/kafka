param([ValidateSet('Start','Stop','Status')][string]$Action='Start', [switch]$NoBrowser)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
foreach ($kind in @('restore','source')) {
    $stateFile = Join-Path $projectRoot ("local/$kind-state.json")
    if (-not (Test-Path -LiteralPath $stateFile)) { continue }
    $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
    if (-not $state.project -or $state.project -notmatch "^kafka-$kind-\d{8}-\d{6}$") { throw 'Invalid rehearsal project.' }
    if ($kind -eq 'restore') {
        $dataPath = $state.backup
        $env:KAFKA_BACKUP_PATH = $dataPath.Replace('\','/')
        $file = 'compose.restore.yaml'
        $allowedRoot = Join-Path $projectRoot 'local/backups'
    } else {
        $dataPath = $state.path
        $env:KAFKA_SOURCE_PATH = $dataPath.Replace('\','/')
        $originalBackup = (Resolve-Path -LiteralPath $state.original_backup).Path
        if (-not $originalBackup.StartsWith([IO.Path]::GetFullPath((Join-Path $projectRoot 'local/backups')) + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid source backup path.' }
        $env:KAFKA_SOURCE_BACKUP = $originalBackup.Replace('\','/')
        $file = 'compose.source.yaml'
        $allowedRoot = Join-Path $projectRoot 'local/source'
    }
    $resolvedPath = (Resolve-Path -LiteralPath $dataPath).Path
    if (-not $resolvedPath.StartsWith([IO.Path]::GetFullPath($allowedRoot) + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid private rehearsal path.' }
    $composeArgs = @('compose','--project-name',$state.project,'--env-file',(Join-Path $resolvedPath '.env'),'-f',$file)
    switch ($Action) {
        'Start' {
            if ($state.status -notlike '*verified*' -and $state.status -notlike 'running*') { throw 'Rehearsal must finish preparation before restart.' }
            $internal = docker network inspect ($state.project + '_default') --format '{{.Internal}}'
            if ($LASTEXITCODE -ne 0 -or $internal -ne 'true') { throw 'Rehearsal network is not isolated.' }
            & docker @composeArgs start
            if ($LASTEXITCODE -ne 0) { throw 'Could not restart rehearsal.' }
            $ready = $false
            $deadline = (Get-Date).AddMinutes(2)
            while ((Get-Date) -lt $deadline) {
                try {
                    $response = Invoke-WebRequest -Uri $state.url -UseBasicParsing -TimeoutSec 10
                    if ($response.StatusCode -eq 200) { $ready=$true; break }
                } catch { }
                Start-Sleep -Seconds 2
            }
            if (-not $ready) { throw "Preview is not ready: $($state.url). Existing data is preserved." }
            if (-not $NoBrowser) {
                Start-Process $state.url
                $admin = if ($kind -eq 'restore') { 'admin-dev/' } else { 'admin-source/' }
                Start-Process ($state.url + $admin)
            }
        }
        'Stop' { & docker @composeArgs stop }
        'Status' { & docker @composeArgs ps -a }
    }
    if ($LASTEXITCODE -ne 0) { throw 'Rehearsal command failed.' }
}
