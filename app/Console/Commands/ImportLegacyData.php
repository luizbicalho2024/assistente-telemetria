<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception as MongoException;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {--simulador-uri= : URI MongoDB do Simulador}
        {--financeiro-uri= : URI MongoDB do Financeiro}
        {--dry-run : Apenas lista o que seria importado}';

    protected $description = 'Importa os bancos MongoDB dos projetos Streamlit para o banco unificado.';

    public function handle(): int
    {
        $targetUri = (string) env('MONGODB_URI', 'mongodb://mongo:27017');
        $targetDbName = (string) env('MONGODB_DATABASE', 'assistente_telemetria');
        $target = (new Client($targetUri))->selectDatabase($targetDbName);

        $simUri = trim((string) ($this->option('simulador-uri') ?: env('LEGACY_SIMULADOR_MONGODB_URI', '')));
        $finUri = trim((string) ($this->option('financeiro-uri') ?: env('LEGACY_FINANCEIRO_MONGODB_URI', '')));

        if ($simUri === '' && $finUri === '') {
            $this->error('Informe pelo menos uma URI legada via opção ou .env.');
            return self::FAILURE;
        }

        if ($simUri !== '') {
            $this->components->info('Importando Simulador...');
            $source = (new Client($simUri))->selectDatabase((string) env('LEGACY_SIMULADOR_MONGODB_DATABASE', 'simulador_db'));
            $this->importSimulator($source, $target);
        }

        if ($finUri !== '') {
            $this->components->info('Importando Financeiro...');
            $source = (new Client($finUri))->selectDatabase((string) env('LEGACY_FINANCEIRO_MONGODB_DATABASE', 'financeiro_verdio'));
            $this->importFinance($source, $target);
        }

        if (!$this->option('dry-run')) {
            $this->call('app:bootstrap');
        }

        $this->components->info('Importação concluída.');
        return self::SUCCESS;
    }

    private function importSimulator(Database $source, Database $target): void
    {
        $this->importUsers($source, $target, 'simulador');

        $collections = [
            'activity_logs','proposals','pricing_config','system_settings','fipe_vehicles',
            'billing_history','market_research','customers','trackers',
        ];

        foreach ($collections as $name) {
            $this->copyCollection($source, $target, $name, $name);
        }
    }

    private function importFinance(Database $source, Database $target): void
    {
        $this->importUsers($source, $target, 'financeiro');

        $mapping = [
            'billing_history' => 'billing_history',
            'billing_runs' => 'billing_runs',
            'billing_runs__items' => 'billing_run_items',
            'billing_terminal_snapshots' => 'billing_terminal_snapshots',
            'billing_monthly_metrics' => 'billing_monthly_metrics',
            'billing_month_closures' => 'billing_month_closures',
            'trackers' => 'trackers',
            'client_contracts' => 'client_contracts',
            'terminais_parceiros' => 'terminais_parceiros',
            'system_logs' => 'finance_system_logs',
            'billing_settings' => 'billing_settings',
            'commissions' => 'commissions',
        ];

        foreach ($mapping as $sourceName => $targetName) {
            $this->copyCollection($source, $target, $sourceName, $targetName);
        }
    }

    private function importUsers(Database $source, Database $target, string $origin): void
    {
        $sourceUsers = $source->selectCollection('users');
        $targetUsers = $target->selectCollection('users');
        $count = 0;

        try {
            foreach ($sourceUsers->find() as $document) {
                $data = (array) $document;
                $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
                $username = mb_strtolower(trim((string) ($data['username'] ?? '')));

                if ($username === '' && $email !== '') {
                    $username = preg_replace('/[^a-z0-9._-]+/i', '_', strstr($email, '@', true) ?: $email) ?: 'usuario';
                }
                if ($username === '') {
                    $username = 'legacy_'.substr(sha1((string) ($data['_id'] ?? Str::uuid())), 0, 10);
                }

                $roleRaw = mb_strtolower(trim((string) ($data['role'] ?? 'user')));
                $role = match ($roleRaw) {
                    'admin', 'administrador' => 'admin',
                    'head_comercial', 'head comercial' => 'head_comercial',
                    'financeiro' => 'financeiro',
                    'operacional', 'operacao', 'operação' => 'operacional',
                    default => 'user',
                };

                $filter = $email !== '' ? ['email' => $email] : ['username' => $username];
                $usernameOwner = $targetUsers->findOne(['username' => $username], ['projection' => ['email' => 1]]);
                if ($usernameOwner && mb_strtolower((string) ($usernameOwner['email'] ?? '')) !== $email) {
                    $username .= '_'.substr(sha1($email ?: (string) ($data['_id'] ?? Str::uuid())), 0, 6);
                }

                $payload = [
                    'username' => $username,
                    'name' => (string) ($data['name'] ?? $data['nome'] ?? $email ?: $username),
                    'role' => $role,
                    'active' => ($data['active'] ?? true) !== false && ($data['disabled'] ?? false) !== true,
                    'disabled' => ($data['active'] ?? true) === false || ($data['disabled'] ?? false) === true,
                    'legacy_origin' => $origin,
                    'updated_at' => new \MongoDB\BSON\UTCDateTime(),
                ];
                if ($email !== '') {
                    $payload['email'] = $email;
                }

                if (!empty($data['password'])) {
                    $payload['password'] = $data['password'];
                }
                if (!empty($data['hashed_password'])) {
                    $payload['hashed_password'] = $data['hashed_password'];
                }
                if (!empty($data['password_hash'])) {
                    $payload['password_hash'] = $data['password_hash'];
                }

                if ($this->option('dry-run')) {
                    $count++;
                    continue;
                }

                $targetUsers->updateOne($filter, [
                    '$set' => $payload,
                    '$setOnInsert' => ['created_at' => new \MongoDB\BSON\UTCDateTime()],
                ], ['upsert' => true]);
                $count++;
            }
        } catch (Throwable $e) {
            $this->warn("Usuários {$origin}: {$e->getMessage()}");
        }

        $this->line("  usuários {$origin}: {$count}");
    }

    private function copyCollection(Database $source, Database $target, string $sourceName, string $targetName): void
    {
        $count = 0;
        try {
            foreach ($source->selectCollection($sourceName)->find() as $document) {
                $data = (array) $document;
                $id = $data['_id'] ?? new \MongoDB\BSON\ObjectId();

                // Converte metadados da antiga emulação de subcoleções.
                if ($sourceName === 'billing_runs__items') {
                    $data['run_id'] = $data['run_id'] ?? $data['__mongo_parent_id'] ?? null;
                    unset($data['__mongo_parent_id'], $data['__mongo_parent_collection'], $data['__mongo_document_id']);
                }

                if (!$this->option('dry-run')) {
                    $target->selectCollection($targetName)->replaceOne(['_id' => $id], $data, ['upsert' => true]);
                }
                $count++;
            }
        } catch (Throwable $e) {
            $this->warn("  {$sourceName}: {$e->getMessage()}");
            return;
        }

        $this->line("  {$sourceName} -> {$targetName}: {$count}");
    }
}
