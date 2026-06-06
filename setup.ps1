# ============================================================
# Docker Local Webserver ? Setup Script (Run ONCE)
# ============================================================
# Cara menjalankan:
#   1. Klik kanan file ini ? "Run with PowerShell"
#   2. ATAU buka PowerShell sebagai Administrator, lalu:
#      cd c:\docker
#      .\setup.ps1
#
# PENTING: Jalankan sebagai Administrator (untuk edit hosts file)
# ============================================================

param(
    [switch]$SkipMkcert,
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"
$Host.UI.RawUI.WindowTitle = "Docker Webserver Setup"

# ---- Colors ----
function Write-Step($msg) { Write-Host "`n>> $msg" -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "  [WARN]  $msg" -ForegroundColor Yellow }
function Write-Err($msg) { Write-Host "  [ERR] $msg" -ForegroundColor Red }

Write-Host ""
Write-Host "============================================" -ForegroundColor Magenta
Write-Host "  Docker Local Webserver ? Setup" -ForegroundColor Magenta
Write-Host "  17 containers, 6 GUI panels" -ForegroundColor Magenta
Write-Host "============================================" -ForegroundColor Magenta

# ============================================================
# 1. Check Administrator
# ============================================================
Write-Step "Checking administrator privileges..."
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Warn "Not running as Administrator. Restarting elevated..."
    Start-Process powershell.exe -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`"" -Verb RunAs
    exit
}
Write-Ok "Running as Administrator"

# ============================================================
# 2. Check Docker Desktop
# ============================================================
Write-Step "Checking Docker Desktop..."
try {
    $dockerVersion = docker version --format '{{.Server.Version}}' 2>$null
    if ($LASTEXITCODE -ne 0) { throw "Docker not responding" }
    Write-Ok "Docker Desktop is running (v$dockerVersion)"
} catch {
    Write-Err "Docker Desktop is not running!"
    Write-Host "  Please start Docker Desktop and run this script again." -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# ============================================================
# 3. Check Port Availability
# ============================================================
Write-Step "Checking port availability..."
$portsToCheck = @(
    @{Port=80;  Name="HTTP"},
    @{Port=443; Name="HTTPS"},
    @{Port=81;  Name="NPM Admin"},
    @{Port=3306; Name="MariaDB"},
    @{Port=6379; Name="Redis"},
    @{Port=8080; Name="phpMyAdmin"},
    @{Port=8025; Name="Mailpit"}
)

$portConflict = $false
foreach ($p in $portsToCheck) {
    $conn = Get-NetTCPConnection -LocalPort $p.Port -State Listen -ErrorAction SilentlyContinue
    if ($conn) {
        $process = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
        Write-Err "Port $($p.Port) ($($p.Name)) is in use by $($process.ProcessName) (PID: $($conn.OwningProcess))"
        $portConflict = $true
    } else {
        Write-Ok "Port $($p.Port) ($($p.Name)) is available"
    }
}

if ($portConflict) {
    Write-Host ""
    Write-Warn "Some ports are in use. Please close the conflicting applications and try again."
    Write-Host "  Common culprits: Laragon, Herd, XAMPP, IIS, Apache, Skype" -ForegroundColor Yellow
    $continue = Read-Host "  Continue anyway? (y/N)"
    if ($continue -ne "y") { exit 1 }
}

# ============================================================
# 4. Create Directory Structure
# ============================================================
Write-Step "Creating directory structure..."
$dirs = @(
    "data\mariadb",
    "data\redis",
    "data\redisinsight",
    "data\npm\data",
    "data\npm\letsencrypt",
    "data\dockge",
    "data\mailpit",
    "logs\nginx",
    "logs\php",
    "logs\cron",
    "logs\worker",
    "config\ssl",
    "sites\default"
)

foreach ($dir in $dirs) {
    $fullPath = Join-Path $PSScriptRoot $dir
    if (-not (Test-Path $fullPath)) {
        New-Item -Path $fullPath -ItemType Directory -Force | Out-Null
    }
}
Write-Ok "All directories created"

# ============================================================
# 5. Install mkcert & Generate SSL Certificates
# ============================================================
if (-not $SkipMkcert) {
    Write-Step "Setting up mkcert for trusted HTTPS..."

    $mkcertPath = Join-Path $PSScriptRoot "mkcert.exe"
    $sslDir = Join-Path $PSScriptRoot "config\ssl"

    # Download mkcert if not exists
    if (-not (Test-Path $mkcertPath)) {
        Write-Host "  Downloading mkcert..." -ForegroundColor Gray
        $mkcertUrl = "https://dl.filippo.io/mkcert/latest?for=windows/amd64"
        try {
            Invoke-WebRequest -Uri $mkcertUrl -OutFile $mkcertPath -UseBasicParsing
            Write-Ok "mkcert downloaded"
        } catch {
            Write-Err "Failed to download mkcert: $_"
            Write-Warn "You can manually download from: https://github.com/FiloSottile/mkcert/releases"
            Write-Warn "Skipping SSL setup..."
            $SkipMkcert = $true
        }
    } else {
        Write-Ok "mkcert already exists"
    }

    if (-not $SkipMkcert) {
        # Install local CA
        Write-Host "  Installing local CA (browser will trust certificates)..." -ForegroundColor Gray
        $oldErrorAction = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        try { & $mkcertPath -install 2>&1 | Out-Null } catch {}
        Write-Ok "Local CA installed to Windows Certificate Store"

        # Generate wildcard certificate for *.test (fallback)
        $certFile = Join-Path $sslDir "wildcard.test.pem"
        $keyFile = Join-Path $sslDir "wildcard.test-key.pem"

        if (-not (Test-Path $certFile)) {
            Write-Host "  Generating wildcard certificate for *.test..." -ForegroundColor Gray
            Push-Location $sslDir
            try { & $mkcertPath "*.test" "localhost" "127.0.0.1" "::1" 2>&1 | Out-Null } catch {}
            $ErrorActionPreference = $oldErrorAction
            # mkcert outputs files with specific names, rename them
            $generatedCert = Get-ChildItem -Path $sslDir -Filter "_wildcard*-key.pem" | Select-Object -First 1
            if ($generatedCert) {
                $certBaseName = $generatedCert.Name -replace "-key\.pem$", ".pem"
                Rename-Item -Path (Join-Path $sslDir $certBaseName) -NewName "wildcard.test.pem" -ErrorAction SilentlyContinue
                Rename-Item -Path $generatedCert.FullName -NewName "wildcard.test-key.pem" -ErrorAction SilentlyContinue
            }
            Pop-Location
            Write-Ok "Wildcard SSL certificate generated for *.test"
        } else {
            Write-Ok "SSL certificate already exists"
        }

        # Copy Root CA for PHP container (per-domain cert generation)
        $caDir = Join-Path $sslDir "ca"
        if (-not (Test-Path $caDir)) { New-Item -Path $caDir -ItemType Directory -Force | Out-Null }
        $caRoot = & $mkcertPath -CAROOT
        Copy-Item -Path (Join-Path $caRoot "rootCA.pem") -Destination $caDir -Force -ErrorAction SilentlyContinue
        Copy-Item -Path (Join-Path $caRoot "rootCA-key.pem") -Destination $caDir -Force -ErrorAction SilentlyContinue
        Write-Ok "Root CA copied for PHP container"

        # Download mkcert Linux binary for PHP container
        $mkcertLinux = Join-Path $sslDir "mkcert"
        if (-not (Test-Path $mkcertLinux)) {
            Write-Host "  Downloading mkcert (Linux) for PHP container..." -ForegroundColor Gray
            try {
                Invoke-WebRequest -Uri "https://dl.filippo.io/mkcert/latest?for=linux/amd64" -OutFile $mkcertLinux -UseBasicParsing
                Write-Ok "mkcert Linux binary downloaded"
            } catch {
                Write-Warn "Failed to download mkcert Linux binary. Dashboard auto-SSL may not work."
            }
        } else {
            Write-Ok "mkcert Linux binary already exists"
        }

        $ErrorActionPreference = $oldErrorAction
    }
} else {
    Write-Warn "Skipping mkcert setup (--SkipMkcert flag)"
}

# ============================================================
# 6. Add default.test to Windows hosts file
# ============================================================
Write-Step "Configuring Windows hosts file..."
$hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
$hostsContent = Get-Content $hostsFile -Raw

$domains = @("default.test")
foreach ($domain in $domains) {
    if ($hostsContent -notmatch [regex]::Escape($domain)) {
        Add-Content -Path $hostsFile -Value "`n127.0.0.1`t$domain"
        Write-Ok "Added $domain to hosts file"
    } else {
        Write-Ok "$domain already in hosts file"
    }
}

# ============================================================
# 7. Build & Start Docker Containers
# ============================================================
if (-not $SkipBuild) {
    Write-Step "Building and starting Docker containers (this may take 5-15 minutes on first run)..."
    Write-Host "  Building custom PHP & Node.js images..." -ForegroundColor Gray

    Push-Location $PSScriptRoot
    docker compose up -d --build
    Pop-Location

    if ($LASTEXITCODE -eq 0) {
        Write-Ok "All 17 containers are starting!"
    } else {
        Write-Err "Docker Compose failed. Check the output above for errors."
        Read-Host "Press Enter to exit"
        exit 1
    }
} else {
    Write-Warn "Skipping Docker build (--SkipBuild flag)"
}

# ============================================================
# 8. Wait for containers to be healthy
# ============================================================
Write-Step "Waiting for containers to be ready..."
Start-Sleep -Seconds 10

# Check container status
$containers = docker compose ps --format "table {{.Name}}\t{{.Status}}" 2>$null
Write-Host $containers -ForegroundColor Gray

# ============================================================
# 9. Summary
# ============================================================
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  [OK] Setup Complete!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  System: GUI Panels:" -ForegroundColor Cyan
Write-Host "  |-- Nginx Proxy Manager : http://localhost:81" -ForegroundColor White
Write-Host "  |   \-- Login: admin@example.com / changeme" -ForegroundColor Gray
Write-Host "  |-- Dockge             : http://localhost:5001" -ForegroundColor White
Write-Host "  |-- Dozzle (Logs)      : http://localhost:9999" -ForegroundColor White
Write-Host "  |-- phpMyAdmin         : http://localhost:8080" -ForegroundColor White
Write-Host "  |   \-- Login: root / secret" -ForegroundColor Gray
Write-Host "  |-- RedisInsight       : http://localhost:8001" -ForegroundColor White
Write-Host "  \-- Mailpit            : http://localhost:8025" -ForegroundColor White
Write-Host ""
Write-Host "  Web: Default Dashboard   : http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Dir: Project files       : c:\docker\sites\" -ForegroundColor Cyan
Write-Host "  Docs: Documentation       : c:\docker\docs\" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Next: Create a new site:" -ForegroundColor Yellow
Write-Host "     .\create-site.ps1 -Name `"myapp`" -PhpVersion `"8.4`"" -ForegroundColor White
Write-Host "     .\create-node-site.ps1 -Name `"nextapp`" -Framework `"nextjs`"" -ForegroundColor White
Write-Host ""
Write-Host "============================================" -ForegroundColor Green

Read-Host "Press Enter to close"
