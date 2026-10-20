param([string]$ArchivePath, [string]$KeyFile, [switch]$Extract)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
if (-not $ArchivePath) { $ArchivePath = (Read-Host 'Path to downloaded kafka-backup.kafkagcm').Trim('"') }
if (-not $KeyFile) { $KeyFile = (Read-Host 'Path to separately saved cloud-recovery KEY file').Trim('"') }
$archive = (Resolve-Path -LiteralPath $ArchivePath).Path
$key = (Resolve-Path -LiteralPath $KeyFile).Path
$pythonRuntime = Join-Path $env:USERPROFILE '.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe'
if (-not (Test-Path -LiteralPath $pythonRuntime)) { throw 'Bundled Python is missing; ask Codex to locate the current runtime.' }
$arguments = @('scripts/cloud-backup.py','--verify',$archive,'--key-file',$key)
if ($Extract) {
    $neededBytes = (Get-Item -LiteralPath $archive).Length * 2 + 512MB
    $freeBytes = (Get-PSDrive -Name ([IO.Path]::GetPathRoot($projectRoot).Substring(0,1))).Free
    if ($freeBytes -lt $neededBytes) { throw 'Not enough free space for a complete cloud extraction. Streaming verification without -Extract still works; ask Codex to prepare the restore on a disk with enough space.' }
    $restoreDir = Join-Path $projectRoot ('local/backups/cloud-download-restore-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    New-Item -ItemType Directory -Path $restoreDir | Out-Null
    $bundlePath = Join-Path $restoreDir 'bundle.tar'
    $arguments += @('--decrypt-to',$bundlePath)
}
& $pythonRuntime @arguments
if ($LASTEXITCODE -ne 0) { throw 'Cloud backup verification failed. No restored shop was started.' }
if (-not $Extract) { Write-Host 'Downloaded encrypted backup verified without creating another large plaintext copy.' }
if ($Extract) {
    & $pythonRuntime scripts/extract-cloud-backup.py --bundle $bundlePath
    if ($LASTEXITCODE -ne 0) { throw 'Cloud archive extraction failed.' }
    Write-Host "Verified restored backup folders: $restoreDir"
    Write-Host 'Ask Codex to start a fresh shop from the downloaded backup. Existing shop volumes are preserved.'
}
