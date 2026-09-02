# Assistente Telemetria

Projeto Laravel unificado que substitui os antigos repositórios **Simulador-Telemetria** e **financeiro-verdio**.

A aplicação consolida em um único login e um único MongoDB as funções comerciais, financeiras, operacionais e administrativas. O menu e as rotas são liberados por **perfil/permissão**, sem necessidade de manter duas aplicações.

## Stack

- PHP 8.4 no container de aplicação;
- Laravel 13;
- integração oficial `mongodb/laravel-mongodb`;
- MongoDB 8;
- Apache no mesmo container PHP;
- Blade + CSS/JavaScript estáticos;
- PhpSpreadsheet para XLS/XLSX/CSV;
- Docker Compose com **exatamente dois serviços**:
  - `assistente`;
  - `mongo`.

## Módulos unificados

### Comercial

- visão geral;
- Simulador PJ;
- Simulador PF;
- Simulador de licitações;
- dashboard de propostas;
- churn e base ativa;
- aprovações comerciais;
- pesquisa de mercado.

### Operacional

- consultas gerais;
- análise de jornada;
- dados de clientes;
- gestão de estoque;
- comandos de rastreadores;
- análise de terminais.

### Financeiro

- relatório SUGESP;
- faturamento Verdio;
- faturamento de parceiros;
- resumo mensal;
- contratos de clientes;
- histórico de faturamento;
- comissão de vendedores.

### Administração

- usuários;
- perfis e permissões;
- identidade visual;
- preços/custos;
- auditoria e logs.

## Perfis iniciais

O bootstrap cria cinco perfis:

- `admin`: acesso total;
- `head_comercial`: módulos comerciais e operacionais;
- `user`: Comercial;
- `financeiro`: módulos financeiros e dados necessários;
- `operacional`: módulos operacionais.

O Administrador pode criar outros perfis em **Administração > Perfis e permissões** e marcar página por página o que cada perfil pode acessar. A proteção existe tanto no menu quanto no middleware das rotas.

## Compatibilidade de usuários legados

A autenticação aceita durante a migração:

- hash bcrypt usado pelo Simulador;
- hash `pbkdf2_sha256` usado pelo Financeiro;
- hash nativo atual do Laravel.

Após um login legado válido, a senha é automaticamente regravada com o `Hash` atual do Laravel e os campos antigos são removidos.

## Regras preservadas do Simulador PJ

A camada `PricingService` mantém:

- custo recorrente mensal;
- custo único por veículo;
- mensalização equivalente de custos únicos;
- instalação cobrada ou isenta;
- custo da instalação sempre considerado;
- custo fixo por proposta;
- margem bruta sobre o preço de venda;
- fórmula de preço por margem;
- piso comercial mínimo de **30%**;
- payback da instalação isenta;
- totalização de propostas com mix de produtos;
- cálculo de ponto de equilíbrio por quantidade.

Os valores padrão de planos, PF, licitações e taxas de parcelamento foram portados do projeto anterior.

## Faturamento

O fluxo de faturamento recebe XLS/XLSX/CSV e:

1. localiza automaticamente o cabeçalho;
2. normaliza aliases das colunas;
3. usa o relatório para identificar a competência;
4. cruza `Nº Equipamento` com o estoque;
5. identifica tipo/modelo;
6. usa preço do contrato do cliente;
7. quando não há preço no contrato, utiliza média disponível;
8. calcula dias faturáveis e proporcionalidade;
9. grava o snapshot vigente em `billing_history`;
10. cria revisão imutável em `billing_runs`;
11. grava itens em `billing_run_items`;
12. atualiza snapshots por terminal em `billing_terminal_snapshots`;
13. gera métricas em `billing_monthly_metrics`;
14. permite fechar a competência em `billing_month_closures`.

Para evitar documentos próximos do limite de 16 MB do MongoDB, o histórico vigente só embute detalhes enquanto o JSON está abaixo da margem configurada no serviço. A cópia integral permanece nas coleções de execução/snapshot.

## Comandos Suntech

A página de comandos mantém os comandos existentes para:

- ST300;
- ST390;
- ST4315U.

O envio via Twilio é opcional. Sem `TWILIO_*`, os comandos continuam sendo gerados normalmente.

## Subir localmente com Docker

No PowerShell:

```powershell
Copy-Item .env.example .env
notepad .env
.\scripts\iniciar-local.ps1
```

Ou diretamente:

```powershell
docker compose up -d --build
docker compose ps
```

A aplicação ficará em:

```text
http://localhost:8080
```

### Antes de produção

Troque no `.env`:

```dotenv
APP_DEBUG=false
APP_URL=https://seu-dominio
MONGO_ROOT_PASSWORD=UMA_SENHA_FORTE
MONGO_APP_PASSWORD=OUTRA_SENHA_FORTE
ADMIN_EMAIL=seu-email
ADMIN_PASSWORD=UMA_SENHA_FORTE
```

O MongoDB **não possui porta publicada** no host no `docker-compose.yml`; somente o container da aplicação se conecta a ele pela rede Docker.

## Primeiro administrador

No startup o container executa:

```bash
php artisan app:bootstrap
```

O comando:

- cria índices;
- cria perfis padrão;
- grava identidade visual padrão;
- grava preços padrão;
- cria o administrador definido em `ADMIN_*`, quando necessário.

Se a coleção `users` estiver vazia e nenhum administrador for criado por ambiente, a tela de login também oferece o formulário de primeiro acesso.

## Importar os bancos atuais

A migração de dados foi separada do deploy para não exigir acesso aos bancos legados durante o build.

Preencha no `.env`:

```dotenv
LEGACY_SIMULADOR_MONGODB_URI=mongodb+srv://...
LEGACY_SIMULADOR_MONGODB_DATABASE=simulador_db

LEGACY_FINANCEIRO_MONGODB_URI=mongodb+srv://...
LEGACY_FINANCEIRO_MONGODB_DATABASE=financeiro_verdio
```

Primeiro valide sem gravar:

```bash
docker compose exec assistente php artisan legacy:import --dry-run
```

Depois execute:

```bash
docker compose exec assistente php artisan legacy:import
```

A rotina:

- unifica os formatos de usuários;
- mantém hashes legados para upgrade no primeiro login;
- importa propostas, preços, identidade visual e logs do Simulador;
- importa faturamentos, revisões, snapshots, métricas, fechamentos, estoque, contratos e parceiros do Financeiro;
- converte a antiga coleção física `billing_runs__items` para `billing_run_items`;
- executa novamente `app:bootstrap` ao final.

Faça backup dos bancos antigos antes da importação definitiva.

## Publicar no GitHub

O script pronto está em:

```text
scripts/publicar-github.ps1
```

Execute a partir do PowerShell:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\publicar-github.ps1
```

Ele:

- garante Git e GitHub CLI;
- autentica pelo fluxo oficial do GitHub;
- impede publicação acidental do `.env`;
- inicializa Git quando necessário;
- configura `origin`;
- cria o commit;
- sincroniza a `main` remota sem `--force`;
- publica em `https://github.com/luizbicalho2024/assistente-telemetria.git`.

## Deploy Linux posteriormente

O repositório já está preparado para o deploy posterior via SSH. Na VPS o fluxo básico será:

```bash
git clone https://github.com/luizbicalho2024/assistente-telemetria.git
cd assistente-telemetria
cp .env.example .env
nano .env
docker compose up -d --build
docker compose ps
```

Para produção, a etapa posterior deve acrescentar proxy reverso/HTTPS no host (por exemplo Nginx/Caddy já existente na VPS), backup do volume Mongo e política de atualização.

## Estrutura principal

```text
app/
  Console/Commands/
    BootstrapApplication.php
    ImportLegacyData.php
  Http/
    Controllers/
    Middleware/EnsurePermission.php
  Models/
  Services/
    AccessService.php
    ActivityLogger.php
    BillingService.php
    LegacyPasswordService.php
    PricingService.php
    SpreadsheetService.php
    TrackerCommandService.php

config/
  modules.php

resources/views/
  admin/
  auth/
  finance/
  inventory/
  modules/
  proposals/
  simulators/
  trackers/

docker/
  entrypoint.sh
  mongo-init.js

Dockerfile
docker-compose.yml
scripts/publicar-github.ps1
scripts/iniciar-local.ps1
```

## Segurança

- nunca versionar `.env`;
- usar senhas diferentes para root Mongo, usuário da aplicação e administrador;
- não publicar a porta 27017;
- usar HTTPS em produção;
- revisar permissões dos perfis antes de liberar usuários;
- restringir credenciais Twilio;
- manter backups antes de cargas/importações históricas.
