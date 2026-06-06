# ============================================================
# Create Node.js Site Script
# ============================================================
# Penggunaan:
#   .\create-node-site.ps1 -Name "nextapp" -Framework "nextjs"
#   .\create-node-site.ps1 -Name "viteapp" -Framework "vite"
#   .\create-node-site.ps1 -Name "nuxtapp" -Framework "nuxt"
#   .\create-node-site.ps1 -Name "myapi" -Framework "express"
#
# PENTING: Jalankan sebagai Administrator (untuk edit hosts file)
# ============================================================

param(
    [Parameter(Mandatory=$true)]
    [string]$Name,

    [ValidateSet("nextjs", "vite", "nuxt", "express", "custom")]
    [string]$Framework = "custom",

    [int]$Port = 3000
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

Write-Host ""
Write-Host "Creating Node.js site: $domain (Framework: $Framework, Port: $Port)" -ForegroundColor Magenta

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
Write-Ok "Directory created: $siteDir"

# ============================================================
# 2. Add to Windows hosts file
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
}

# ============================================================
# 3. Framework-specific instructions
# ============================================================
$initCommand = switch ($Framework) {
    "nextjs"  { "npx -y create-next-app@latest ." }
    "vite"    { "npm create vite@latest . -- --template react" }
    "nuxt"    { "npx -y nuxi@latest init ." }
    "express" { "npm init -y && npm install express" }
    "custom"  { "npm init -y" }
}

$devCommand = switch ($Framework) {
    "nextjs"  { "npm run dev -- -H 0.0.0.0 -p $Port" }
    "vite"    { "npm run dev -- --host 0.0.0.0 --port $Port" }
    "nuxt"    { "npm run dev -- --host 0.0.0.0 --port $Port" }
    "express" { "node index.js" }
    "custom"  { "npm start" }
}

# ============================================================
# Done!
# ============================================================
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  [OK] Site $domain ready!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Dir: Files: $siteDir" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Next: Next Steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "  1. Enter the Node.js container:" -ForegroundColor White
Write-Host "     docker compose exec node sh" -ForegroundColor Gray
Write-Host ""
Write-Host "  2. Navigate to your project:" -ForegroundColor White
Write-Host "     cd /var/www/html/$domain" -ForegroundColor Gray
Write-Host ""
Write-Host "  3. Initialize the project:" -ForegroundColor White
Write-Host "     $initCommand" -ForegroundColor Gray
Write-Host ""
Write-Host "  4. Start the dev server:" -ForegroundColor White
Write-Host "     $devCommand" -ForegroundColor Gray
Write-Host ""
Write-Host "  5. Set up proxy in Nginx Proxy Manager:" -ForegroundColor White
Write-Host "     - Open http://localhost:81" -ForegroundColor Gray
Write-Host "     - Hosts ? Proxy Hosts ? Add Proxy Host" -ForegroundColor Gray
Write-Host "     - Domain: $domain" -ForegroundColor Gray
Write-Host "     - Forward: node:$Port" -ForegroundColor Gray
Write-Host "     - SSL: Upload custom certificate (*.test)" -ForegroundColor Gray
Write-Host ""
Write-Host "  6. Access your site:" -ForegroundColor White
Write-Host "     https://$domain" -ForegroundColor Gray
Write-Host ""
