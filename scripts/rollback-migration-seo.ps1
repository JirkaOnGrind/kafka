param(
    [Parameter(Mandatory=$true)][ValidatePattern('^seo-[0-9-]+$')][string]$Run,
    [switch]$Apply,
    [string]$LabPath=''
)
$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
if (!$LabPath) { $LabPath=Join-Path $root 'local/private/migration-lab-20261018' }
$lab=(Resolve-Path -LiteralPath $LabPath).Path
if (!$lab.StartsWith([IO.Path]::GetFullPath((Join-Path $root 'local/private')) + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Not a private project lab.' }
$backup=Join-Path $lab ('seo-runs/' + $Run)
foreach($file in @('settings-before.json','files-before.tar.gz','files-before.list')) { if(!(Test-Path -LiteralPath (Join-Path $backup $file))) {throw "Missing rollback component: $file"} }
$dc=@('compose','--env-file',(Join-Path $lab '.env'),'-f',(Join-Path $lab 'compose.yaml'))
function Invoke-RollbackDocker([string[]]$Arguments) { & docker @dc @Arguments; if($LASTEXITCODE -ne 0){throw 'SEO rollback stopped; backup retained.'} }
$runtime='/lab/seo-runs/' + $Run
foreach($file in @('rollback-migration-seo.php','restore-seo-files.php','clear-local-seo-cache.php')) {Copy-Item -LiteralPath (Join-Path $PSScriptRoot $file) -Destination $backup -Force}
Invoke-RollbackDocker @('exec','-T','target','tar','-tzf',($runtime + '/files-before.tar.gz'))
$arguments=@('exec','-T','target','php',($runtime + '/rollback-migration-seo.php'),('--backup=' + $runtime))
if($Apply){$arguments += '--apply'}
Invoke-RollbackDocker $arguments
if(!$Apply){Write-Output 'Read-only rollback plan verified. Use -Apply only when reverting this SEO change is intended.';exit 0}
Invoke-RollbackDocker @('exec','-T','target','php',($runtime + '/apply-migration-seo.php'),('--restore=' + $runtime + '/settings-before.json'))
Invoke-RollbackDocker @('exec','-T','target','php',($runtime + '/restore-seo-files.php'),$runtime)
Invoke-RollbackDocker @('exec','-T','target','tar','-xzf',($runtime + '/files-before.tar.gz'),'-C','/var/www/html')
Invoke-RollbackDocker @('exec','-T','target','php',($runtime + '/clear-local-seo-cache.php'))
Write-Output 'Managed SEO configuration, module state and backed-up files restored. Catalog and Docker volumes were not restored or reset.'
