$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

$frontendDir = Join-Path $repoRoot "frontend"
$distDir = Join-Path $frontendDir "dist"

$phpIndex = Join-Path $repoRoot "php/public/index.php"
$phpHtaccess = Join-Path $repoRoot "php/public/.htaccess"
$phpPrompts = Join-Path $repoRoot "php/src/prompts.php"

$outDir = Join-Path $repoRoot "hosting"
$envPath = Join-Path $outDir ".env"
$preservedEnv = $null
if (Test-Path $envPath) {
  $preservedEnv = [System.IO.File]::ReadAllText($envPath)
}

Write-Host "== Build frontend =="
Push-Location $frontendDir
try {
  $viteBin = Join-Path $frontendDir "node_modules/.bin/vite"
  if (-not (Test-Path $viteBin)) {
    npm install --no-package-lock
  }
  npm run build
} finally {
  Pop-Location
}

if (-not (Test-Path $distDir)) {
  throw "Frontend build output not found: $distDir"
}

Write-Host "== Prepare output folder =="
if (Test-Path $outDir) {
  Remove-Item -Recurse -Force $outDir
}
New-Item -ItemType Directory -Path $outDir | Out-Null
New-Item -ItemType Directory -Path (Join-Path $outDir "src") | Out-Null

Write-Host "== Copy PHP runtime files =="
Copy-Item -Force $phpIndex (Join-Path $outDir "index.php")
Copy-Item -Force $phpHtaccess (Join-Path $outDir ".htaccess")
Copy-Item -Force $phpPrompts (Join-Path $outDir "src/prompts.php")

Write-Host "== Copy frontend build =="
Copy-Item -Recurse -Force (Join-Path $distDir "*") $outDir

if ($preservedEnv -ne $null) {
  [System.IO.File]::WriteAllText($envPath, $preservedEnv)
}

Write-Host ""
Write-Host "Done."
Write-Host "Upload the contents of: $outDir"
Write-Host "Create: $outDir/.env  (OPENAI_API_KEY=...)"
