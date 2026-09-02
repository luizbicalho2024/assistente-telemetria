[CmdletBinding()]
param(
    [switch]$ResetData,
    [switch]$NoCache
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "============================================================" -ForegroundColor DarkCyan
    Write-Host $Message -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor DarkCyan
}

function Write-Utf8NoBom {
    param([string]$Path, [string]$Content)
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Set-DotEnvValue {
    param(
        [string]$Path,
        [string]$Key,
        [string]$Value
    )

    $content = [System.IO.File]::ReadAllText($Path)
    $pattern = "(?m)^" + [regex]::Escape($Key) + "=.*$"
    $line = "$Key=$Value"

    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $line })
    } else {
        if ($content.Length -gt 0 -and -not $content.EndsWith("`n")) {
            $content += "`r`n"
        }
        $content += "$line`r`n"
    }

    Write-Utf8NoBom -Path $Path -Content $content
}

function Get-DotEnvValue {
    param([string]$Path, [string]$Key)
    if (-not (Test-Path $Path)) { return $null }
    $line = Get-Content $Path | Where-Object { $_ -match ('^' + [regex]::Escape($Key) + '=') } | Select-Object -First 1
    if (-not $line) { return $null }
    return (($line -split '=', 2)[1]).Trim().Trim('"').Trim("'")
}

function New-HexSecret {
    param([int]$Bytes = 24)
    $buffer = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return -join ($buffer | ForEach-Object { $_.ToString('x2') })
}

function New-AppKey {
    $buffer = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return "base64:" + [Convert]::ToBase64String($buffer)
}

function Test-PortListening {
    param([int]$Port)
    try {
        $conn = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
        return [bool]$conn
    } catch {
        $match = netstat -ano | Select-String -Pattern (":$Port\s+.*LISTENING")
        return [bool]$match
    }
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $ProjectRoot
$EnvFile = Join-Path $ProjectRoot ".env"

Write-Step "ASSISTENTE TELEMETRIA - TESTE LOCAL WINDOWS + DOCKER"
Write-Host "Projeto: $ProjectRoot"

Write-Step "1/8 - Verificando Docker Desktop"
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker não foi encontrado no PATH. Abra/reinstale o Docker Desktop e tente novamente."
}

docker version --format '{{.Client.Version}}' | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw "O cliente Docker não respondeu corretamente."
}

docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Docker Desktop está instalado, mas o engine não está iniciado. Abra o Docker Desktop e execute novamente."
}

docker compose version | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw "Docker Compose v2 não está disponível. Atualize o Docker Desktop."
}

Write-Step "2/8 - Preparando configuração local"
if ($ResetData) {
    Write-Host "Reset solicitado: removendo containers e volume local do Assistente Telemetria..." -ForegroundColor Yellow
    docker compose down -v --remove-orphans 2>$null | Out-Host
    if (Test-Path $EnvFile) {
        Remove-Item $EnvFile -Force
    }
}

$createdEnv = $false
if (-not (Test-Path $EnvFile)) {
    Copy-Item ".env.example" ".env"
    $createdEnv = $true

    $port = 8080
    while ($port -le 8099 -and (Test-PortListening -Port $port)) {
        $port++
    }
    if ($port -gt 8099) {
        throw "Não encontrei uma porta livre entre 8080 e 8099."
    }

    $mongoRootPassword = New-HexSecret -Bytes 24
    $mongoAppPassword = New-HexSecret -Bytes 24
    $adminPassword = "Local2026-" + (New-HexSecret -Bytes 8) + "-Aa1"

    Set-DotEnvValue -Path $EnvFile -Key "APP_ENV" -Value "local"
    Set-DotEnvValue -Path $EnvFile -Key "APP_DEBUG" -Value "true"
    Set-DotEnvValue -Path $EnvFile -Key "APP_PORT" -Value "$port"
    Set-DotEnvValue -Path $EnvFile -Key "APP_URL" -Value "http://localhost:$port"
    Set-DotEnvValue -Path $EnvFile -Key "APP_TIMEZONE" -Value "America/Porto_Velho"
    Set-DotEnvValue -Path $EnvFile -Key "APP_KEY" -Value (New-AppKey)
    Set-DotEnvValue -Path $EnvFile -Key "MONGODB_DATABASE" -Value "assistente_telemetria"
    Set-DotEnvValue -Path $EnvFile -Key "MONGO_ROOT_USERNAME" -Value "root"
    Set-DotEnvValue -Path $EnvFile -Key "MONGO_ROOT_PASSWORD" -Value $mongoRootPassword
    Set-DotEnvValue -Path $EnvFile -Key "MONGO_APP_USER" -Value "assistente"
    Set-DotEnvValue -Path $EnvFile -Key "MONGO_APP_PASSWORD" -Value $mongoAppPassword
    Set-DotEnvValue -Path $EnvFile -Key "ADMIN_NAME" -Value "Administrador Local"
    Set-DotEnvValue -Path $EnvFile -Key "ADMIN_USERNAME" -Value "admin"
    Set-DotEnvValue -Path $EnvFile -Key "ADMIN_EMAIL" -Value "admin@localhost"
    Set-DotEnvValue -Path $EnvFile -Key "ADMIN_PASSWORD" -Value $adminPassword

    Write-Host ".env local criado com chaves próprias desta instalação." -ForegroundColor Green
} else {
    Write-Host ".env já existe; mantendo as credenciais e dados atuais." -ForegroundColor Green
}

$AppPort = [int](Get-DotEnvValue -Path $EnvFile -Key "APP_PORT")
if (-not $AppPort) { $AppPort = 8080 }
$AppUrl = Get-DotEnvValue -Path $EnvFile -Key "APP_URL"
if (-not $AppUrl) { $AppUrl = "http://localhost:$AppPort" }
$AdminUsername = Get-DotEnvValue -Path $EnvFile -Key "ADMIN_USERNAME"
$AdminPassword = Get-DotEnvValue -Path $EnvFile -Key "ADMIN_PASSWORD"

Write-Step "3/8 - Validando Docker Compose"
docker compose config --quiet
if ($LASTEXITCODE -ne 0) {
    throw "docker-compose.yml ou .env possuem configuração inválida."
}
Write-Host "Compose válido." -ForegroundColor Green

Write-Step "4/8 - Removendo execução anterior sem apagar o MongoDB"
docker compose down --remove-orphans 2>$null | Out-Host

# Containers com nomes fixos podem ter sido criados por outra tentativa/pasta.
foreach ($containerName in @("assistente-telemetria-app", "assistente-telemetria-mongo")) {
    $exists = docker ps -a --filter "name=^/${containerName}$" --format '{{.Names}}'
    if ($exists -eq $containerName) {
        Write-Host "Removendo container residual: $containerName" -ForegroundColor Yellow
        docker rm -f $containerName | Out-Host
    }
}

Write-Step "5/8 - Construindo e iniciando MongoDB + Laravel"
if ($NoCache) {
    docker compose build --no-cache
    if ($LASTEXITCODE -ne 0) { throw "Falha durante docker compose build --no-cache." }
    docker compose up -d
} else {
    docker compose up -d --build
}
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao construir ou iniciar os containers."
}

Write-Step "6/8 - Aguardando healthchecks"
$mongoHealthy = $false
$appHealthy = $false

for ($attempt = 1; $attempt -le 90; $attempt++) {
    $mongoStatus = docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' assistente-telemetria-mongo 2>$null
    $appStatus = docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' assistente-telemetria-app 2>$null

    if ($mongoStatus -eq "healthy") { $mongoHealthy = $true }
    if ($appStatus -eq "healthy") { $appHealthy = $true }

    Write-Host ("Mongo: {0,-10} | Laravel: {1,-10}" -f $mongoStatus, $appStatus)

    if ($mongoHealthy -and $appHealthy) { break }
    Start-Sleep -Seconds 2
}

if (-not ($mongoHealthy -and $appHealthy)) {
    Write-Host ""
    Write-Host "Os containers não ficaram saudáveis. Últimos logs:" -ForegroundColor Red
    docker compose ps | Out-Host
    docker compose logs --tail=250 assistente mongo | Out-Host
    throw "Healthcheck falhou. Os logs acima indicam a causa."
}

Write-Step "7/8 - Testando aplicação HTTP"
try {
    $health = Invoke-WebRequest -Uri "$AppUrl/up" -UseBasicParsing -TimeoutSec 20
    if ($health.StatusCode -ne 200) {
        throw "GET /up retornou HTTP $($health.StatusCode)."
    }
    Write-Host "GET $AppUrl/up -> HTTP 200" -ForegroundColor Green

    $login = Invoke-WebRequest -Uri "$AppUrl/login" -UseBasicParsing -TimeoutSec 20
    if ($login.StatusCode -ne 200) {
        throw "GET /login retornou HTTP $($login.StatusCode)."
    }
    Write-Host "GET $AppUrl/login -> HTTP 200" -ForegroundColor Green
} catch {
    docker compose logs --tail=250 assistente | Out-Host
    throw
}

Write-Step "8/8 - Resultado"
docker compose ps | Out-Host

Write-Host ""
Write-Host "TESTE LOCAL CONCLUÍDO COM SUCESSO" -ForegroundColor Green
Write-Host ""
Write-Host "Aplicação: $AppUrl" -ForegroundColor Cyan
Write-Host "Login:     $AdminUsername" -ForegroundColor Cyan
Write-Host "Senha:     $AdminPassword" -ForegroundColor Yellow
Write-Host ""
Write-Host "Comandos úteis:" -ForegroundColor Cyan
Write-Host "  docker compose ps"
Write-Host "  docker compose logs -f assistente"
Write-Host "  docker compose logs -f mongo"
Write-Host "  docker compose restart assistente"
Write-Host "  docker compose down"
Write-Host ""
Write-Host "Para publicar exatamente esta versão testada no GitHub:" -ForegroundColor Cyan
Write-Host "  Set-Location `"$ProjectRoot`""
Write-Host "  .\scripts\publicar-github.ps1"
Write-Host ""
if ($createdEnv) {
    Write-Host "O arquivo .env NÃO será enviado ao GitHub (.gitignore já o bloqueia)." -ForegroundColor DarkGray
}
