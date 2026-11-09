param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
php scripts/prepare-local-cache.php
if ($LASTEXITCODE -ne 0) { throw 'Cache Laravel belum siap.' }
php artisan serve --host=127.0.0.1 --port=$Port --no-reload
