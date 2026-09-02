<?php

namespace App\Console\Commands;

use App\Models\PricingConfig;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use MongoDB\Client;
use Throwable;

class BootstrapApplication extends Command
{
    protected $signature = 'app:bootstrap {--force-admin : Atualiza a senha do administrador informado no .env}';
    protected $description = 'Cria índices, perfis, configuração padrão e primeiro administrador.';

    public function handle(PricingService $pricing): int
    {
        $this->components->info('Preparando Assistente Telemetria...');

        $this->ensureIndexes();
        $this->seedRoles();
        $this->seedSettings($pricing);
        $this->seedAdmin();

        $this->components->info('Bootstrap concluído.');
        return self::SUCCESS;
    }

    private function ensureIndexes(): void
    {
        try {
            $client = new Client((string) env('MONGODB_URI', 'mongodb://mongo:27017'));
            $db = $client->selectDatabase((string) env('MONGODB_DATABASE', 'assistente_telemetria'));

            $specs = [
                'users' => [
                    [['username' => 1], ['name' => 'uq_users_username', 'unique' => true, 'sparse' => true]],
                    [['email' => 1], ['name' => 'uq_users_email', 'unique' => true, 'sparse' => true]],
                ],
                'roles' => [
                    [['slug' => 1], ['name' => 'uq_roles_slug', 'unique' => true]],
                ],
                'activity_logs' => [
                    [['timestamp' => -1], ['name' => 'ix_logs_timestamp']],
                    [['user' => 1, 'timestamp' => -1], ['name' => 'ix_logs_user_timestamp']],
                ],
                'proposals' => [
                    [['proposal_code' => 1], ['name' => 'uq_proposals_code', 'unique' => true, 'sparse' => true]],
                    [['status' => 1, 'data_geracao' => -1], ['name' => 'ix_proposals_status_date']],
                    [['submitted_by_username' => 1, 'data_geracao' => -1], ['name' => 'ix_proposals_submitter']],
                ],
                'billing_history' => [
                    [['cliente' => 1, 'period_key' => 1], ['name' => 'uq_billing_client_period', 'unique' => true, 'sparse' => true]],
                    [['data_geracao' => -1], ['name' => 'ix_billing_date']],
                ],
                'billing_runs' => [
                    [['run_id' => 1], ['name' => 'uq_billing_run_id', 'unique' => true, 'sparse' => true]],
                    [['period_key' => 1, 'cliente' => 1], ['name' => 'ix_billing_runs_period_client']],
                ],
                'billing_run_items' => [
                    [['run_id' => 1, 'item_index' => 1], ['name' => 'ix_billing_items_run']],
                ],
                'billing_terminal_snapshots' => [
                    [['snapshot_key' => 1], ['name' => 'uq_billing_snapshot', 'unique' => true, 'sparse' => true]],
                    [['period_key' => 1, 'cliente' => 1], ['name' => 'ix_snapshots_period_client']],
                ],
                'billing_monthly_metrics' => [
                    [['period_key' => 1, 'cliente' => 1], ['name' => 'uq_metrics_period_client', 'unique' => true]],
                ],
                'billing_month_closures' => [
                    [['period_key' => 1], ['name' => 'uq_closure_period', 'unique' => true]],
                ],
                'trackers' => [
                    [['Nº Equipamento' => 1], ['name' => 'ix_trackers_equipment', 'sparse' => true]],
                    [['Modelo' => 1], ['name' => 'ix_trackers_model']],
                ],
                'client_contracts' => [
                    [['cliente' => 1], ['name' => 'uq_contracts_client', 'unique' => true]],
                ],
            ];

            foreach ($specs as $collection => $indexes) {
                foreach ($indexes as [$keys, $options]) {
                    try {
                        $db->selectCollection($collection)->createIndex($keys, $options);
                    } catch (Throwable $e) {
                        $this->warn("Índice {$collection}/{$options['name']}: {$e->getMessage()}");
                    }
                }
            }
        } catch (Throwable $e) {
            $this->error('MongoDB indisponível: '.$e->getMessage());
            throw $e;
        }
    }

    private function seedRoles(): void
    {
        $labels = [
            'admin' => 'Administrador',
            'head_comercial' => 'Head Comercial',
            'user' => 'Comercial',
            'financeiro' => 'Financeiro',
            'operacional' => 'Operacional',
        ];

        foreach (config('modules.defaults', []) as $slug => $permissions) {
            $role = Role::query()->where('slug', $slug)->first();
            $payload = [
                'slug' => $slug,
                'name' => $labels[$slug] ?? ucfirst(str_replace('_', ' ', $slug)),
                'permissions' => $permissions,
                'system' => true,
            ];
            $role ? $role->fill($payload)->save() : Role::query()->create($payload);
        }
    }

    private function seedSettings(PricingService $pricing): void
    {
        if (!SystemSetting::query()->where('_id', 'global_branding')->exists()) {
            SystemSetting::query()->create(SystemSetting::defaultBranding());
        }

        if (!PricingConfig::query()->where('_id', 'global_prices')->exists()) {
            PricingConfig::query()->create($pricing->defaults());
        }
    }

    private function seedAdmin(): void
    {
        $email = mb_strtolower(trim((string) env('ADMIN_EMAIL', 'admin@localhost')));
        $username = mb_strtolower(trim((string) env('ADMIN_USERNAME', 'admin')));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->warn('ADMIN_PASSWORD vazio. O administrador poderá ser criado pelo formulário do primeiro acesso.');
            return;
        }

        $existing = User::query()->where('username', $username)->orWhere('email', $email)->first();
        if ($existing) {
            if ($this->option('force-admin')) {
                $existing->fill([
                    'role' => 'admin',
                    'active' => true,
                    'disabled' => false,
                    'password' => Hash::make($password),
                    'hashed_password' => null,
                    'password_hash' => null,
                ])->save();
                $this->info("Administrador {$username} atualizado.");
            }
            return;
        }

        User::query()->create([
            'name' => (string) env('ADMIN_NAME', 'Administrador'),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'active' => true,
            'disabled' => false,
        ]);
        $this->info("Administrador {$username} criado.");
    }
}
