param(
    [ValidateSet('migration','thumbnails')][string]$Kind='migration',
    [int]$MaximumBatches=2000,
    [ValidatePattern('^[a-z0-9-]+[.]jsonl$')][string]$LogName='',
    [string]$LabPath=''
)
$ErrorActionPreference='Stop'
if (!$LabPath) { $LabPath=Join-Path (Split-Path -Parent $PSScriptRoot) 'local/private/migration-lab-20261018' }
$lab=(Resolve-Path -LiteralPath $LabPath).Path
$privateRoot=[IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $PSScriptRoot) 'local/private')) + [IO.Path]::DirectorySeparatorChar
if (!$lab.StartsWith($privateRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Isolated lab must stay in local/private.' }
$dc=@('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
$container=(& docker @dc ps -q target | Out-String).Trim()
if($LASTEXITCODE -ne 0 -or !$container){throw 'Start the isolated migration target first.'}
$networks=& docker inspect $container --format '{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{println}}{{end}}'
if($LASTEXITCODE -ne 0){throw 'Cannot inspect target networks.'}
foreach($network in $networks){
    $internal=& docker network inspect $network.Trim() --format '{{.Internal}}'
    if($LASTEXITCODE -ne 0 -or $internal -ne 'true'){throw 'Native migration requires internal application networks.'}
}
$log=Join-Path $lab $(if($LogName){$LogName}else{$Kind+'-progress.jsonl'})
for($batch=1;$batch -le $MaximumBatches;$batch++) {
    if ((Get-PSDrive C).Free -lt 4GB) { throw 'Stopped to preserve at least 4 GiB host disk reserve.' }
    $helper=if($Kind -eq 'migration'){'/lab/migrate.php'}else{'/lab/thumbnails.php'}
    [string[]]$tail=if($Kind -eq 'migration'){@('batch')}else{@()}
    $raw=& docker @dc exec -T --user www-data target php $helper @tail
    if($LASTEXITCODE -ne 0){throw 'Isolated native batch failed; private logs retained.'}
    $value=($raw -join "`n") | ConvertFrom-Json
    [ordered]@{time=(Get-Date).ToString('o');batch=$batch;result=$value} | ConvertTo-Json -Compress -Depth 8 | Add-Content -LiteralPath $log -Encoding UTF8
    if($batch -eq 1 -or $batch % 20 -eq 0 -or $value.end){ Write-Output ("{0} batch {1}, progress {2}" -f $Kind,$batch,$value.percent) }
    if($value.end){
        if($Kind -eq 'migration') {
            # Mandatory acceptance after every completed native migration, including Mode 4 repeats.
            & (Join-Path $PSScriptRoot 'complete-isolated-import.ps1') -LabPath $lab -ImportIdentity ('native-migration:' + (Split-Path -Leaf $log))
            if ($LASTEXITCODE -ne 0) { throw 'Migration completed but SEO acceptance failed.' }
        }
        Write-Output "$Kind finished and required acceptance completed."
        exit 0
    }
}
throw 'Bounded batch limit reached; state retained for inspection.'
