param(
    [ValidateSet('Start','Stop','Status','Logs','Backup')][string]$Action = 'Start',
    [switch]$NoBrowser
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$localMutex = New-Object System.Threading.Mutex($false, 'Local\KafkaMonsterLocalControl')
$hasLock = $false
try {
    $hasLock = $localMutex.WaitOne(0)
    if (-not $hasLock) { throw 'Another Kafka local operation is running. Wait for it to finish before starting a second one.' }
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Docker Desktop is not installed. Install and start Docker Desktop first.'
    }
    docker info --format '{{.ServerVersion}}' 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Start Docker Desktop and wait until its engine is running, then run start.bat again.' }
    $envFile = Join-Path $projectRoot '.env'
    if (-not (Test-Path -LiteralPath $envFile)) {
        if ($Action -ne 'Start') { throw 'Local environment is not initialized. Run start.bat first.' }
        $dbPassword = [Guid]::NewGuid().ToString('N')
        $rootPassword = [Guid]::NewGuid().ToString('N')
        $adminPassword = 'Kafka!' + [Guid]::NewGuid().ToString('N').Substring(0,20)
        @("DB_PASSWORD=$dbPassword", "DB_ROOT_PASSWORD=$rootPassword", 'ADMIN_EMAIL=admin@kafka.local', "ADMIN_PASSWORD=$adminPassword") |
            Set-Content -LiteralPath $envFile -Encoding ASCII
        Write-Host 'Created local credentials in .env (excluded from Git).'
    }
    switch ($Action) {
        'Stop' {
            docker compose stop
            if ($LASTEXITCODE -ne 0) { throw 'Docker could not stop the local services.' }
            Write-Host 'Stopped. Database and shop files are preserved.'
        }
        'Status' {
            docker compose ps -a
            if ($LASTEXITCODE -ne 0) { throw 'Docker status failed.' }
        }
        'Logs' {
            docker compose logs --tail 100 shop db reference
            if ($LASTEXITCODE -ne 0) { throw 'Could not read Docker logs.' }
        }
        'Backup' {
            $shopWasRunning = (docker compose ps --status running --services) -contains 'shop'
            $backupDir = Join-Path $projectRoot ('local/backups/' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
            New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
            docker compose up -d --wait db
            if ($LASTEXITCODE -ne 0) { throw 'Could not start database for backup.' }
            try {
                docker compose stop shop
                if ($LASTEXITCODE -ne 0) { throw 'Could not pause shop writes for backup.' }
                docker compose exec -T db sh -c 'mysqldump -u kafka -p"$MYSQL_PASSWORD" --single-transaction --no-tablespaces kafka > /tmp/kafka-backup.sql'
                if ($LASTEXITCODE -ne 0) { throw 'Database export failed.' }
                docker compose cp db:/tmp/kafka-backup.sql (Join-Path $backupDir 'database.sql')
                if ($LASTEXITCODE -ne 0) { throw 'Copying database export failed.' }
                docker compose run --rm --no-deps --volume ('{0}:/backup' -f $backupDir) --entrypoint tar shop -czf /backup/shopfiles.tar.gz --exclude=./var/cache -C /var/www/html .
                if ($LASTEXITCODE -ne 0) { throw 'Shop files archive failed.' }
                Copy-Item -LiteralPath $envFile -Destination (Join-Path $backupDir '.env')
                Copy-Item -LiteralPath (Join-Path $projectRoot 'compose.yaml') -Destination $backupDir
                # Include untracked custom code as well as Git history. Exclude private
                # backup/key folders to prevent recursive archives or leaking cloud keys.
                & tar.exe -czf (Join-Path $backupDir 'project.tar.gz') --exclude=./local/backups --exclude=./local/private --exclude=./local/source --exclude=./.playwright-cli --exclude=./migration/__pycache__ -C $projectRoot .
                if ($LASTEXITCODE -ne 0) { throw 'Project source archive failed.' }
                $backupFiles = @(Get-ChildItem -LiteralPath $backupDir -File -Force | Where-Object Name -ne 'checksums.csv')
                $backupHashes = @($backupFiles | Get-FileHash -Algorithm SHA256 | Select-Object Path,Hash)
                $backupHashes | Export-Csv -LiteralPath (Join-Path $backupDir 'checksums.csv') -NoTypeInformation -Encoding UTF8
                Write-Host "Backup saved: $backupDir"
                Write-Host 'Contains private credentials and shop data. Keep it private. Restore must be tested separately.'
            } finally {
                if ($shopWasRunning) { docker compose start shop }
            }
        }
        'Start' {
            docker compose up -d
            if ($LASTEXITCODE -ne 0) { throw 'Startup failed. Read the Docker error above; existing data was not reset.' }
            Write-Host 'Waiting for installation. The first start may take several minutes.'
            $ready = $false
            $deadline = (Get-Date).AddMinutes(15)
            while ((Get-Date) -lt $deadline) {
                try {
                    $response = Invoke-WebRequest -Uri 'http://localhost:8080/' -UseBasicParsing -TimeoutSec 10
                    if ($response.StatusCode -eq 200 -and $response.Content -match '<html' -and $response.Content -notmatch 'install-kafka') {
                        $ready = $true
                        break
                    }
                } catch { }
                Start-Sleep -Seconds 5
            }
            if (-not $ready) { throw 'Shop is not ready yet. Run logs.bat to inspect installation, then start.bat again. Do not delete volumes.' }
            docker compose cp scripts/configure-local.php shop:/tmp/kafka-configure-local.php
            if ($LASTEXITCODE -ne 0) { throw 'Could not copy the local configuration helper.' }
            docker compose exec -T --user www-data shop php /tmp/kafka-configure-local.php
            if ($LASTEXITCODE -ne 0) { throw 'Local configuration failed. Read the error above.' }
            Write-Host 'SHOP:       http://localhost:8080/'
            Write-Host 'ADMIN:      http://localhost:8080/admin-dev/'
            Write-Host 'REFERENCE:  http://localhost:4173/'
            Write-Host 'Admin login: ADMIN_EMAIL and ADMIN_PASSWORD in .env'
            Write-Host 'Instructions: JAK-PRACOVAT.md'
            if (-not $NoBrowser) {
                Start-Process 'http://localhost:8080/'
                Start-Process 'http://localhost:8080/admin-dev/'
                Start-Process 'http://localhost:4173/'
            }
        }
    }
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
} finally {
    if ($hasLock) { $localMutex.ReleaseMutex() }
    $localMutex.Dispose()
}
