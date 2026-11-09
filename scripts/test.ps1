$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
php scripts/prepare-local-cache.php
if ($LASTEXITCODE -ne 0) { throw 'Cache Laravel belum siap.' }
$config = [System.IO.File]::ReadAllText((Join-Path (Get-Location) 'phpunit.xml'))
$config = $config.Replace('bootstrap="vendor/autoload.php"', '')
[System.IO.File]::WriteAllText((Join-Path (Get-Location) '.phpunit.local.xml'), $config)
php -d auto_prepend_file=vendor/autoload.php vendor/phpunit/phpunit/phpunit --configuration .phpunit.local.xml
if ($LASTEXITCODE -ne 0) { throw 'Ada pengujian yang belum lulus.' }
