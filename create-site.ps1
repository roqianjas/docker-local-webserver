# ============================================================
# Create PHP Site Script
# ============================================================
# Penggunaan:
#   .\create-site.ps1 -Name "myapp" -PhpVersion "8.4"
#   .\create-site.ps1 -Name "legacy" -PhpVersion "7.4"
#   .\create-site.ps1 -Name "laravel" -PhpVersion "8.3" -Type "laravel"
#
# PENTING: Jalankan sebagai Administrator (untuk edit hosts file)
# ============================================================

param(
    [Parameter(Mandatory=$true)]
    [string]$Name,

    [ValidateSet("7.4", "8.1", "8.2", "8.3", "8.4")]
    [string]$PhpVersion = "8.4",

    [ValidateSet("standard", "laravel")]
    [string]$Type = "standard"
)

$ErrorActionPreference = "Stop"

# ---- Helpers ----
function Write-Step($msg) { Write-Host "`n>> $msg" -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Write-Err($msg) { Write-Host "  [ERR] $msg" -ForegroundColor Red }

# ---- Variables ----
$domain = "$Name.test"
$sitesDir = Join-Path $PSScriptRoot "sites"
$siteDir = Join-Path $sitesDir $domain
$nginxDir = Join-Path $PSScriptRoot "config\nginx\sites"

# Configure Nginx
Write-Step "Configuring Nginx..."
$templateFile = Join-Path $nginxDir "_template.conf.example"
$confFile = Join-Path $nginxDir "$domain.conf"
$phpContainer = "php$($PhpVersion -replace '\.', '')"

# Map root path based on type
if ($Type -eq "laravel") {
    $rootPath = "/var/www/html/$domain/public"
} else {
    $rootPath = "/var/www/html/$domain"
}

Write-Host ""
Write-Host "Creating site: $domain (PHP $PhpVersion, Type: $Type)" -ForegroundColor Magenta

# ============================================================
# 1. Create site directory
# ============================================================
Write-Step "Creating site directory..."
if (Test-Path $siteDir) {
    Write-Err "Directory already exists: $siteDir"
    $continue = Read-Host "  Continue anyway? (y/N)"
    if ($continue -ne "y") { exit 1 }
} else {
    New-Item -Path $siteDir -ItemType Directory -Force | Out-Null
}

if ($Type -eq "laravel") {
    New-Item -Path (Join-Path $siteDir "public") -ItemType Directory -Force | Out-Null
}
Write-Ok "Directory created: $siteDir"

# ============================================================
# 2. Create default index.php
# ============================================================
Write-Step "Creating index.php..."
$indexPath = if ($Type -eq "laravel") { Join-Path $siteDir "public\index.php" } else { Join-Path $siteDir "index.php" }

$indexContent = @"
<?php
echo "<h1>?? Welcome to $domain</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . `$_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . `$_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<hr>";
echo "<p><a href='/info.php'>PHP Info</a></p>";
"@

Set-Content -Path $indexPath -Value $indexContent -Encoding UTF8
Write-Ok "index.php created"

# Create info.php
$infoPath = if ($Type -eq "laravel") { Join-Path $siteDir "public\info.php" } else { Join-Path $siteDir "info.php" }
Set-Content -Path $infoPath -Value "<?php phpinfo();" -Encoding UTF8

# ============================================================
# 3. Create Nginx config from template
# ============================================================
Write-Step "Creating Nginx configuration..."
if (-not (Test-Path $templateFile)) {
    Write-Err "Template not found: $templateFile"
    exit 1
}

$template = Get-Content $templateFile -Raw
$config = $template `
    -replace '\{\{DOMAIN\}\}', $domain `
    -replace '\{\{PHP_VERSION\}\}', $phpContainer `
    -replace '\{\{ROOT_PATH\}\}', $rootPath

Set-Content -Path $confFile -Value $config -Encoding UTF8
Write-Ok "Nginx config created: $confFile"

# ============================================================
# 4. Add to Windows hosts file
# ============================================================
Write-Step "Adding to Windows hosts file..."
$hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"

try {
    $hostsContent = Get-Content $hostsFile -Raw
    if ($hostsContent -notmatch [regex]::Escape($domain)) {
        Add-Content -Path $hostsFile -Value "`n127.0.0.1`t$domain"
        Write-Ok "$domain added to hosts file"
    } else {
        Write-Ok "$domain already in hosts file"
    }
} catch {
    Write-Err "Failed to edit hosts file. Run as Administrator!"
    Write-Host "  Manual: Add '127.0.0.1 $domain' to $hostsFile" -ForegroundColor Yellow
}

# ============================================================
# 5. Reload Nginx
# ============================================================
Write-Step "Reloading Nginx..."
docker compose -f (Join-Path $PSScriptRoot "docker-compose.yml") exec nginx nginx -s reload 2>$null
if ($LASTEXITCODE -eq 0) {
    Write-Ok "Nginx reloaded"
} else {
    Write-Err "Nginx reload failed. Is the container running?"
    Write-Host "  Run: docker compose restart nginx" -ForegroundColor Yellow
}

# ============================================================
# Done!
# ============================================================
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  [OK] Site $domain ready!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Web: URL          : http://$domain" -ForegroundColor Cyan
Write-Host "  Dir: Files         : $siteDir" -ForegroundColor Cyan
Write-Host "  PHP: PHP Version   : $PhpVersion ($phpContainer)" -ForegroundColor Cyan
Write-Host "  Config: Nginx Config  : $confFile" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Tip: HTTPS: Set up SSL via Nginx Proxy Manager:" -ForegroundColor Yellow
Write-Host "     1. Open http://localhost:81" -ForegroundColor White
Write-Host "     2. Hosts ? Proxy Hosts ? Add Proxy Host" -ForegroundColor White
Write-Host "     3. Domain: $domain ? Forward: nginx:8000" -ForegroundColor White
Write-Host "     4. SSL tab ? Upload custom certificate" -ForegroundColor White
Write-Host ""
