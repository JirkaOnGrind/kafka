param([switch]$NoBrowser)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$lab = Join-Path $root 'local/private/migration-lab-20261018'
function Invoke-MigrationDocker([string[]]$Arguments) {
    $result = & docker @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Docker operation failed. Start Docker Desktop; existing data are preserved.' }
    return $result
}
$dc = @('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
# Start existing accepted containers only. Never initialize a replacement database.
$target = (Invoke-MigrationDocker ($dc + @('ps','-a','-q','target')) | Out-String).Trim()
if (!$target) { throw 'Existing migration target is missing. Ask Codex to inspect; no new shop was created.' }
foreach ($network in (Invoke-MigrationDocker @('inspect',$target,'--format','{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'))) {
    if ($network.Trim() -and (Invoke-MigrationDocker @('network','inspect',$network.Trim(),'--format','{{.Internal}}')) -ne 'true') { throw 'Target must stay isolated.' }
}
Invoke-MigrationDocker ($dc + @('start','target-db','target','preview')) | Out-Null
$ready = $false
for ($attempt = 0; $attempt -lt 60; $attempt++) {
    try {
        $response = Invoke-WebRequest 'http://localhost:8083/cs/' -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200 -and $response.Headers['X-Robots-Tag'] -match 'noindex') { $ready = $true; break }
    } catch { }
    Start-Sleep -Seconds 2
}
if (!$ready) { throw 'Migration preview did not become ready with noindex protection.' }
Write-Host 'Current migration shop: http://localhost:8083/cs/'
if (!$NoBrowser) { Start-Process 'http://localhost:8083/cs/' }
