[CmdletBinding()]
param(
    [string]$Repositorio = "https://github.com/luizbicalho2024/assistente-telemetria.git",
    [string]$Branch = "main",
    [string]$Mensagem = "feat: migracao unificada para Laravel e MongoDB Docker"
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

function Refresh-Path {
    $machine = [Environment]::GetEnvironmentVariable("Path", "Machine")
    $user = [Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machine;$user"
}

function Ensure-Command {
    param(
        [Parameter(Mandatory=$true)][string]$Name,
        [Parameter(Mandatory=$true)][string]$WingetId
    )

    if (Get-Command $Name -ErrorAction SilentlyContinue) {
        return
    }

    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw "$Name não foi encontrado e o winget também não está disponível. Instale $Name e execute novamente."
    }

    Write-Host "Instalando $Name via winget..."
    winget install --id $WingetId --exact --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao instalar $Name."
    }

    Refresh-Path
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name foi instalado, mas ainda não está no PATH. Feche e abra o PowerShell e execute novamente."
    }
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $ProjectRoot

Write-Step "ASSISTENTE TELEMETRIA - PUBLICAÇÃO NO GITHUB"
Write-Host "Projeto:     $ProjectRoot"
Write-Host "Repositório: $Repositorio"
Write-Host "Branch:      $Branch"

Write-Step "1/8 - Verificando Git e GitHub CLI"
Ensure-Command -Name "git" -WingetId "Git.Git"
Ensure-Command -Name "gh" -WingetId "GitHub.cli"

git --version
gh --version | Select-Object -First 1

Write-Step "2/8 - Autenticando no GitHub"
$ghStatus = & gh auth status --hostname github.com 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "O GitHub CLI abrirá o fluxo oficial de autenticação no navegador." -ForegroundColor Yellow
    gh auth login --hostname github.com --git-protocol https --web
    if ($LASTEXITCODE -ne 0) {
        throw "Não foi possível autenticar no GitHub."
    }
}
gh auth setup-git
if ($LASTEXITCODE -ne 0) {
    throw "Não foi possível configurar o Git para usar a autenticação do GitHub CLI."
}
gh auth status --hostname github.com

Write-Step "3/8 - Validando arquivos sensíveis"
if (Test-Path ".env") {
    $trackedEnv = git ls-files --error-unmatch .env 2>$null
    if ($LASTEXITCODE -eq 0 -and $trackedEnv) {
        throw "O arquivo .env está rastreado pelo Git. Remova-o do índice antes de publicar."
    }
}

if (-not (Test-Path ".env.example")) {
    throw ".env.example não encontrado."
}
if (-not (Test-Path "composer.json")) {
    throw "composer.json não encontrado."
}
if (-not (Test-Path "docker-compose.yml")) {
    throw "docker-compose.yml não encontrado."
}

Write-Step "4/8 - Inicializando repositório local"
if (-not (Test-Path ".git")) {
    git init
    if ($LASTEXITCODE -ne 0) { throw "Falha em git init." }
}

git config user.name 2>$null | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (git config user.name)) {
    $login = gh api user --jq .login
    git config user.name $login
}

git config user.email 2>$null | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (git config user.email)) {
    $userId = gh api user --jq .id
    $login = gh api user --jq .login
    git config user.email "$userId+$login@users.noreply.github.com"
}

git branch -M $Branch

$originExists = git remote 2>$null | Where-Object { $_ -eq "origin" }
if ($originExists) {
    git remote set-url origin $Repositorio
} else {
    git remote add origin $Repositorio
}

Write-Step "5/8 - Conferindo repositório remoto"
git ls-remote origin | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw "Não foi possível acessar $Repositorio com a autenticação atual."
}

Write-Step "6/8 - Criando commit"
git add -A
if ($LASTEXITCODE -ne 0) { throw "Falha em git add." }

$staged = git diff --cached --name-only
if ($staged) {
    Write-Host "Arquivos incluídos no commit:"
    $staged | ForEach-Object { Write-Host "  $_" }
    git commit -m $Mensagem
    if ($LASTEXITCODE -ne 0) { throw "Falha ao criar commit." }
} else {
    Write-Host "Nenhuma alteração nova para commit." -ForegroundColor Yellow
}

Write-Step "7/8 - Sincronizando com a main remota sem force"
$remoteMain = git ls-remote --heads origin $Branch
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao consultar a branch remota."
}

if ($remoteMain) {
    git fetch origin $Branch
    if ($LASTEXITCODE -ne 0) { throw "Falha no git fetch." }

    $mergeBase = git merge-base HEAD "origin/$Branch" 2>$null
    if (-not $mergeBase) {
        Write-Host "A branch remota possui histórico independente. Tentando rebase preservando o conteúdo remoto..." -ForegroundColor Yellow
        git pull --rebase --allow-unrelated-histories origin $Branch
    } else {
        git pull --rebase origin $Branch
    }

    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "O rebase encontrou conflito. Resolva os arquivos indicados, execute:" -ForegroundColor Red
        Write-Host "  git add -A"
        Write-Host "  git rebase --continue"
        Write-Host "e depois rode novamente este script."
        exit 1
    }
}

Write-Step "8/8 - Enviando para o GitHub"
git push -u origin $Branch
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao enviar a branch $Branch."
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "PUBLICAÇÃO CONCLUÍDA" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host "Repositório: $Repositorio"
Write-Host "Branch:      $Branch"
Write-Host ""
Write-Host "Próximo passo local, se quiser validar com Docker:" -ForegroundColor Cyan
Write-Host "  Copy-Item .env.example .env"
Write-Host "  notepad .env"
Write-Host "  docker compose up -d --build"
Write-Host "  docker compose ps"
Write-Host ""
