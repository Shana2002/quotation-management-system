<#
    install_tcpdf.ps1
    ------------------------------------------------------------------
    Downloads the TCPDF library directly from GitHub and vendors it into
    libs/tcpdf/ WITHOUT requiring Composer.

    QMS uses TCPDF for PDF generation and its NATIVE 2D-barcode engine
    for QR codes (no GD extension required).

    Usage (from project root or anywhere):
        powershell -ExecutionPolicy Bypass -File scripts\install_tcpdf.ps1
#>

$ErrorActionPreference = "Stop"

# Resolve project root (parent of this scripts/ folder)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root      = Split-Path -Parent $ScriptDir
$LibsDir   = Join-Path $Root "libs"
$Target    = Join-Path $LibsDir "tcpdf"
$Version   = "6.7.5"
$Url       = "https://github.com/tecnickcom/TCPDF/archive/refs/tags/$Version.zip"
$ZipPath   = Join-Path $env:TEMP "tcpdf-$Version.zip"
$ExtractTo = Join-Path $env:TEMP "tcpdf-extract-$Version"

Write-Host "QMS :: TCPDF installer" -ForegroundColor Cyan
Write-Host "Target: $Target"

if (Test-Path (Join-Path $Target "tcpdf.php")) {
    Write-Host "TCPDF already installed at $Target (tcpdf.php found). Nothing to do." -ForegroundColor Green
    exit 0
}

Write-Host "Downloading TCPDF $Version ..." -ForegroundColor Yellow
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
Invoke-WebRequest -Uri $Url -OutFile $ZipPath -UseBasicParsing

Write-Host "Extracting ..." -ForegroundColor Yellow
if (Test-Path $ExtractTo) { Remove-Item -Recurse -Force $ExtractTo }
Expand-Archive -Path $ZipPath -DestinationPath $ExtractTo -Force

# The archive extracts to TCPDF-<version>/
$Inner = Join-Path $ExtractTo "TCPDF-$Version"
if (-not (Test-Path $Inner)) {
    # fall back to first subdirectory
    $Inner = (Get-ChildItem -Directory $ExtractTo | Select-Object -First 1).FullName
}

if (Test-Path $Target) { Remove-Item -Recurse -Force $Target }
Move-Item -Path $Inner -Destination $Target

# Cleanup
Remove-Item -Force $ZipPath -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force $ExtractTo -ErrorAction SilentlyContinue

if (Test-Path (Join-Path $Target "tcpdf.php")) {
    Write-Host "TCPDF $Version installed successfully into $Target" -ForegroundColor Green
} else {
    Write-Error "Installation failed: tcpdf.php not found in $Target"
    exit 1
}
