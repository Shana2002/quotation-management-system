<#
    install_fonts.ps1
    ------------------------------------------------------------------
    Vendors the Carlito font family into libs/tcpdf/fonts/ WITHOUT
    requiring Composer.

    The quotation letter is set in Calibri. TCPDF ships only Helvetica,
    Times, Courier and DejaVu, and Calibri itself cannot be redistributed.
    Carlito is an open-licensed (OFL) metric-compatible clone of Calibri:
    identical advance widths, so line breaks and wrapping in the letter
    match the original document.

    Downloads the four faces from the google/fonts repository, then asks
    TCPDF to build its own font metric files from them.

    libs/ is git-ignored, so — exactly like TCPDF itself — this must be
    run once per checkout.

    Usage (from project root or anywhere):
        powershell -ExecutionPolicy Bypass -File scripts\install_fonts.ps1
#>

$ErrorActionPreference = "Stop"

# Resolve project root (parent of this scripts/ folder)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root      = Split-Path -Parent $ScriptDir
$FontsDir  = Join-Path $Root "libs\tcpdf\fonts"
$PhpExe    = "C:\xampp\php\php.exe"
$Base      = "https://github.com/google/fonts/raw/main/ofl/carlito"
$Faces     = @("Regular", "Bold", "Italic", "BoldItalic")

Write-Host "QMS :: Carlito font installer" -ForegroundColor Cyan
Write-Host "Target: $FontsDir"

if (-not (Test-Path (Join-Path (Split-Path -Parent $FontsDir) "tcpdf.php"))) {
    Write-Error "TCPDF not found. Run scripts\install_tcpdf.ps1 first."
    exit 1
}
if (-not (Test-Path $FontsDir)) {
    New-Item -ItemType Directory -Force -Path $FontsDir | Out-Null
}

# Already generated? Nothing to do.
if (Test-Path (Join-Path $FontsDir "carlito.php")) {
    Write-Host "Carlito already installed (carlito.php found). Nothing to do." -ForegroundColor Green
    exit 0
}

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# The filenames matter: TCPDF derives the font name and its bold/italic
# style from them (see TCPDF_FONTS::addTTFfont), so "Carlito-Bold.ttf"
# becomes "carlitob", "Carlito-BoldItalic.ttf" becomes "carlitobi".
foreach ($face in $Faces) {
    $url = "$Base/Carlito-$face.ttf"
    $out = Join-Path $FontsDir "Carlito-$face.ttf"
    Write-Host "Downloading Carlito-$face.ttf ..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing
}

Write-Host "Generating TCPDF font metrics ..." -ForegroundColor Yellow
& $PhpExe (Join-Path $ScriptDir "build_fonts.php")
if ($LASTEXITCODE -ne 0) {
    Write-Error "Font generation failed."
    exit 1
}

if (Test-Path (Join-Path $FontsDir "carlito.php")) {
    Write-Host "Carlito installed successfully into $FontsDir" -ForegroundColor Green
} else {
    Write-Error "Installation failed: carlito.php not found in $FontsDir"
    exit 1
}
