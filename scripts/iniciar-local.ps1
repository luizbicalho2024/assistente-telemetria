[CmdletBinding()]
param(
    [switch]$Rebuild
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $ProjectRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop não foi encontrado. Instale/inicie o Docker Desktop e execute novamente."
}

docker info | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "O Docker está instalado, mas o daemon não está disponível. Inicie o Docker Desktop."
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host ".env criado a partir de .env.example." -ForegroundColor Yellow
    Write-Host "Antes de produção, troque ADMIN_PASSWORD, MONGO_ROOT_PASSWORD e MONGO_APP_PASSWORD." -ForegroundColor Yellow
}

if ($Rebuild) {
    docker compose down
    docker compose build --no-cache
}

docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao iniciar os containers."
}

docker compose ps
Write-Host ""
Write-Host "Aplicação: http://localhost:8080" -ForegroundColor Green
Write-Host "Logs:      docker compose logs -f assistente" -ForegroundColor Cyan
