param(
    [switch]$VerifyOnly,
    [switch]$SkipKey,
    [switch]$SkipMigration,
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
$installer = Join-Path $PSScriptRoot 'install.php'
$php = Get-Command php -ErrorAction SilentlyContinue

if (-not $php -and (Test-Path -LiteralPath 'C:\xampp\php\php.exe')) {
    $php = Get-Item -LiteralPath 'C:\xampp\php\php.exe'
}

if (-not $php) {
    throw 'PHP nao foi encontrado no PATH nem em C:\xampp\php\php.exe.'
}

$arguments = @($installer)
if ($VerifyOnly) { $arguments += '--verify-only' }
if ($SkipKey) { $arguments += '--skip-key' }
if ($SkipMigration) { $arguments += '--skip-migration' }
if ($SkipTests) { $arguments += '--skip-tests' }

$phpPath = if ($php.Source) { $php.Source } else { $php.FullName }
& $phpPath @arguments
exit $LASTEXITCODE
