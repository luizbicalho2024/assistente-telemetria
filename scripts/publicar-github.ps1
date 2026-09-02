[CmdletBinding()]
param(
    [string]$Repositorio = "https://github.com/luizbicalho2024/assistente-telemetria.git",
    [string]$Branch = ("change/" + (Get-Date -Format "yyyyMMdd-HHmmss")),
    [string]$Mensagem = "chore: atualizacao validada",
    [switch]$Mesclar
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host ("=" * 68) -ForegroundColor DarkCyan
    Write-Host $Message -ForegroundColor Cyan
    Write-Host ("=" * 68) -ForegroundColor DarkCyan
}

function Ensure-Command([string]$Name, [string]$WingetId) {
    if (Get-Command $Name -ErrorAction SilentlyContinue) { return }
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw "$Name não encontrado e winget indisponível."
    }
    winget install --id $WingetId --exact --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) { throw "Falha ao instalar $Name." }
    $env:Path = [Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [Environment]::GetEnvironmentVariable("Path","User")
}

function Assert-NoSecrets {
    $tracked = @(git ls-files)
    $forbiddenNames = @(".env", ".env.production", "auth.json", "id_rsa", "id_ed25519")
    foreach ($name in $forbiddenNames) {
        if ($tracked -contains $name) { throw "Arquivo sensível rastreado: $name" }
    }

    $patterns = @(
        'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY',
        'ghp_[A-Za-z0-9]{30,}',
        'github_pat_[A-Za-z0-9_]{30,}',
        'sk-[A-Za-z0-9_-]{20,}',
        'AKIA[0-9A-Z]{16}',
        ('Troque' + 'EstaSenha123!'),
        ('Troque' + 'MongoAgora123!'),
        ('Troque' + 'RootMongo123!')
    )

    foreach ($file in $tracked) {
        if (-not (Test-Path $file -PathType Leaf)) { continue }
        try {
            $text = [IO.File]::ReadAllText((Resolve-Path $file))
        } catch {
            continue
        }
        foreach ($pattern in $patterns) {
            if ($text -match $pattern) {
                throw "Possível segredo/credencial insegura detectado em $file."
            }
        }
    }

    if (Get-Command docker -ErrorAction SilentlyContinue) {
        docker run --rm -v "${PWD}:/repo" ghcr.io/gitleaks/gitleaks:v8.30.1 detect --source=/repo --redact --no-banner
        if ($LASTEXITCODE -ne 0) { throw "Gitleaks encontrou possível segredo." }
    }
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $ProjectRoot

Ensure-Command git "Git.Git"
Ensure-Command gh "GitHub.cli"

if ($Branch -eq "main") {
    throw "Publicação direta na main foi desabilitada. Use uma branch e Pull Request."
}

if (-not (Test-Path ".git")) { throw "Execute este script dentro do repositório clonado." }

$dirty = git status --porcelain
if ($dirty) {
    Write-Step "Validando alterações locais"
} else {
    Write-Host "Nenhuma alteração local para publicar." -ForegroundColor Yellow
    exit 0
}

gh auth status --hostname github.com *> $null
if ($LASTEXITCODE -ne 0) {
    gh auth login --hostname github.com --git-protocol https --web
}
gh auth setup-git

Assert-NoSecrets

git diff --check
if ($LASTEXITCODE -ne 0) { throw "git diff --check falhou." }

if (Get-Command docker -ErrorAction SilentlyContinue) {
    Write-Step "Executando build/testes de segurança"
    docker build --build-arg INSTALL_DEV=true -t assistente-telemetria-publish .
    if ($LASTEXITCODE -ne 0) { throw "Docker build falhou." }
    docker run --rm --entrypoint php assistente-telemetria-publish artisan test --testsuite=Unit
    if ($LASTEXITCODE -ne 0) { throw "Testes unitários falharam." }
}

git fetch origin main
git checkout -b $Branch

git add -A
git commit -m $Mensagem
git push -u origin $Branch

$repoFullName = $Repositorio -replace '^https://github\.com/','' -replace '\.git$',''
$prUrl = gh pr create --repo $repoFullName --base main --head $Branch --title $Mensagem --body "Alterações publicadas pelo fluxo seguro do projeto."
if ($LASTEXITCODE -ne 0) { throw "Falha ao criar Pull Request." }

Write-Host "PR criado: $prUrl" -ForegroundColor Green

if ($Mesclar) {
    gh pr merge --repo $repoFullName $Branch --squash --delete-branch
    if ($LASTEXITCODE -ne 0) { throw "Falha ao mesclar o Pull Request." }
}
