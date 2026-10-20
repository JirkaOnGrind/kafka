# Integration tests with mocked Docker/import/audit boundaries. Never imports SQL or contacts a shop.
param([Parameter(Mandatory=$true)][string]$OutputPath)
$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$fixture=Join-Path $root ('local/private/seo-workflow-test-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff'))
$fixtureScripts=Join-Path $fixture 'scripts'
$lab=Join-Path $fixture 'local/private/lab'
New-Item -ItemType Directory -Path $fixtureScripts,$lab | Out-Null
foreach($name in @('invoke-isolated-import.ps1','run-isolated-batches.ps1','backup-isolated-database.sh')) { Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination $fixtureScripts }
$global:KafkaSeoTeststeps=New-Object System.Collections.Generic.List[string]
$global:KafkaSeoTestinternal=$true
$global:KafkaSeoTestfailAudit=$false
$global:KafkaSeoTestfailImport=$false
$global:KafkaSeoTestfailBackup=$false
$global:KafkaSeoTestfree=8GB
function Get-PSDrive { param([string]$Name) return [pscustomobject]@{Free=$global:KafkaSeoTestfree} }
function docker {
    $global:LASTEXITCODE=0
    $command=$args -join ' '
    if($command -match ' ps -q target$'){return 'mock-isolated-target'}
    if($command -match '^inspect '){return 'mock-internal-network'}
    if($command -match '^network inspect '){if($global:KafkaSeoTestinternal){return 'true'}else{return 'false'}}
    if($command -match 'backup-isolated-database.sh'){$global:KafkaSeoTeststeps.Add('backup');if($global:KafkaSeoTestfailBackup){$global:LASTEXITCODE=3};return}
    if($command -match '/lab/migrate.php batch'){$global:KafkaSeoTeststeps.Add('native-batch');return '{"end":true,"percent":100}'}
    throw 'Unexpected mocked Docker operation.'
}
@'
param([string]$SnapshotPath,[string]$LabPath)
$global:KafkaSeoTeststeps.Add('adapter')
if($global:KafkaSeoTestfailImport){$global:LASTEXITCODE=2}else{$global:LASTEXITCODE=0}
'@ | Set-Content -LiteralPath (Join-Path $fixture 'adapter.ps1') -Encoding UTF8
@'
param([string]$LabPath,[string]$ImportIdentity)
$global:KafkaSeoTeststeps.Add('seo-and-fresh-audit')
if($global:KafkaSeoTestfailAudit){throw 'Simulated actionable SEO failure.'}
$global:LASTEXITCODE=0
'@ | Set-Content -LiteralPath (Join-Path $fixtureScripts 'complete-isolated-import.ps1') -Encoding UTF8
# No SQL statements: the wrapper hashes this inert provenance file; mocked adapter does no import.
Set-Content -LiteralPath (Join-Path $fixture 'snapshot.sql') -Value '-- inert integration-test provenance; never imported' -Encoding ASCII
$results=New-Object System.Collections.Generic.List[object]
function Invoke-MockedImport {
    & (Join-Path $fixtureScripts 'invoke-isolated-import.ps1') -SnapshotPath (Join-Path $fixture 'snapshot.sql') -ImportScript (Join-Path $fixture 'adapter.ps1') -LabPath $lab | Out-Null
}
function Assert-Flow([string]$Name,[string]$Expected,[scriptblock]$Action,[bool]$ExpectFailure=$false) {
    $global:KafkaSeoTeststeps.Clear();$failed=$false
    $failureMessage=''
    try{& $Action}catch{$failed=$true;$failureMessage=$_.Exception.Message}
    $actual=$global:KafkaSeoTeststeps -join ','
    $passed=($failed -eq $ExpectFailure -and $actual -eq $Expected)
    $results.Add(@{name=$Name;passed=$passed;steps=$actual;failure_expected=$ExpectFailure})
    if(!$passed){throw ('Workflow test failed: ' + $Name + '; steps=' + $actual + '; threw=' + $failed + '; reason=' + $failureMessage)}
}
Assert-Flow 'successful authorized adapter always followed by SEO/audit' 'backup,adapter,seo-and-fresh-audit' {Invoke-MockedImport}
$global:KafkaSeoTestfailAudit=$true
Assert-Flow 'SEO failure rejects acceptance' 'backup,adapter,seo-and-fresh-audit' {Invoke-MockedImport} $true
$global:KafkaSeoTestfailAudit=$false;$global:KafkaSeoTestfailImport=$true
Assert-Flow 'failed adapter is not accepted' 'backup,adapter' {Invoke-MockedImport} $true
$global:KafkaSeoTestfailImport=$false;$global:KafkaSeoTestfailBackup=$true
Assert-Flow 'failed backup prevents import' 'backup' {Invoke-MockedImport} $true
$global:KafkaSeoTestfailBackup=$false;$global:KafkaSeoTestinternal=$false
Assert-Flow 'external target network prevents import' '' {Invoke-MockedImport} $true
$global:KafkaSeoTestinternal=$true
Assert-Flow 'existing native batch completion automatically invokes SEO/audit' 'native-batch,seo-and-fresh-audit' {
    & (Join-Path $fixtureScripts 'run-isolated-batches.ps1') -LabPath $lab -MaximumBatches 1 | Out-Null
}
$global:KafkaSeoTestfree=1GB
Assert-Flow 'disk reserve prevents native migration' '' {
    & (Join-Path $fixtureScripts 'run-isolated-batches.ps1') -LabPath $lab -MaximumBatches 1 | Out-Null
} $true
$report=@{passed=$true;tests=$results;mocked_boundaries=$true;sql_imports=0;shop_requests=0;fixture=$fixture}
$report | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
Write-Output ('Workflow integration: ' + $results.Count + ' checks passed; no SQL imported.')
