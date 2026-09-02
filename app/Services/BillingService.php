<?php

namespace App\Services;

use App\Models\BillingHistory;
use App\Models\BillingMonthClosure;
use App\Models\BillingMonthlyMetric;
use App\Models\BillingRun;
use App\Models\BillingRunItem;
use App\Models\BillingTerminalSnapshot;
use App\Models\ClientContract;
use App\Models\Tracker;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BillingService
{
    public function __construct(private readonly SpreadsheetService $sheets)
    {
    }

    public function importAndCalculate(UploadedFile $file, string $user): array
    {
        $rows = $this->sheets->read($file);
        $headerRow = $this->sheets->findHeaderRow($rows);
        $data = $this->sheets->rowsWithHeaders($rows, $headerRow);
        $reportDate = $this->extractReportDate($rows) ?? now()->toImmutable();
        $periodKey = $reportDate->format('Y-m');

        $closure = BillingMonthClosure::query()
            ->where('period_key', $periodKey)
            ->where('status', 'closed')
            ->first();

        if ($closure) {
            throw new \RuntimeException("A competência {$periodKey} está fechada e não pode ser reprocessada.");
        }

        $aliases = [
            'equipamento' => 'Nº Equipamento',
            'n equipamento' => 'Nº Equipamento',
            'numero equipamento' => 'Nº Equipamento',
            'suspenso dias mes' => 'Suspenso Dias Mes',
            'dias ativos mes' => 'Dias Ativos Mês',
            'data ativacao' => 'Data Ativação',
            'data desativacao' => 'Data Desativação',
            'condicao' => 'Condição',
            'cliente' => 'Cliente',
            'terminal' => 'Terminal',
            'placa' => 'Placa',
            'modelo' => 'Modelo',
        ];

        $normalizedRows = [];
        foreach ($data as $raw) {
            $row = [];
            foreach ($raw as $key => $value) {
                $canonical = $this->sheets->canonical((string) $key);
                $target = $aliases[$canonical] ?? trim((string) $key);
                $row[$target] = $value;
            }
            if (trim((string) ($row['Terminal'] ?? '')) === '') {
                continue;
            }
            $normalizedRows[] = $row;
        }

        $inventory = [];
        foreach (Tracker::query()->get() as $tracker) {
            $equipment = $this->equipment($tracker->{'Nº Equipamento'} ?? $tracker->equipamento ?? $tracker->serial ?? '');
            if ($equipment !== '') {
                $inventory[$equipment] = [
                    'Modelo' => (string) ($tracker->Modelo ?? $tracker->modelo ?? ''),
                    'Tipo' => $this->type($tracker->Tipo ?? $tracker->tipo ?? ''),
                ];
            }
        }

        $contracts = [];
        foreach (ClientContract::query()->get() as $contract) {
            $client = trim((string) ($contract->cliente ?? $contract->Cliente ?? ''));
            if ($client !== '') {
                $legacyPrices = is_array($contract->precos_por_tipo ?? null) ? $contract->precos_por_tipo : [];
                $modernPrices = is_array($contract->prices ?? null) ? $contract->prices : [];
                $contracts[$client] = $legacyPrices ?: ($modernPrices ?: [
                    'GPRS' => (float) ($contract->valor_gprs ?? 0),
                    'SATELITE' => (float) ($contract->valor_satelite ?? 0),
                ]);
            }
        }

        $averages = $this->averageContractPrices($contracts);
        $byClient = [];

        foreach ($normalizedRows as $row) {
            $client = trim((string) ($row['Cliente'] ?? ''));
            if ($client === '' || mb_strtolower($client) === 'cliente') {
                continue;
            }

            $equipment = $this->equipment($row['Nº Equipamento'] ?? '');
            $stock = $inventory[$equipment] ?? [];
            $model = trim((string) ($row['Modelo'] ?? $stock['Modelo'] ?? ''));
            $type = $this->type($stock['Tipo'] ?? $row['Tipo'] ?? '');

            $activation = $this->sheets->normalizeDate($row['Data Ativação'] ?? null);
            $deactivation = $this->sheets->normalizeDate($row['Data Desativação'] ?? null);
            $condition = $this->sheets->canonical((string) ($row['Condição'] ?? ''));
            $suspendedDays = max(0, (int) round((float) ($row['Suspenso Dias Mes'] ?? 0)));
            $daysInMonth = (int) $reportDate->format('t');
            $monthStart = $reportDate->modify('first day of this month')->setTime(0, 0);
            $monthEnd = $reportDate->modify('last day of this month')->setTime(23, 59, 59);

            $activeFrom = ($activation && $activation > $monthStart) ? $activation : $monthStart;
            $activeUntil = ($deactivation && $deactivation < $monthEnd) ? $deactivation : $monthEnd;
            $calendarActiveDays = $activeUntil >= $activeFrom
                ? ((int) $activeFrom->diff($activeUntil)->format('%a') + 1)
                : 0;

            $providedActiveDays = max(0, (int) round((float) ($row['Dias Ativos Mês'] ?? 0)));
            $activeDays = $providedActiveDays > 0 ? min($daysInMonth, $providedActiveDays) : min($daysInMonth, $calendarActiveDays);
            $billableDays = max(0, min($daysInMonth, $activeDays - $suspendedDays));

            $prices = $contracts[$client] ?? [];
            $contractPrice = $this->priceForType($prices, $type);
            $fallback = $averages[$type] ?? 0.0;
            $unit = $contractPrice > 0 ? $contractPrice : $fallback;

            $category = 'Ativo mês completo';
            $activatedThisMonth = $activation && $activation->format('Y-m') === $reportDate->format('Y-m');
            $deactivatedThisMonth = $deactivation && $deactivation->format('Y-m') === $reportDate->format('Y-m');

            if ($activatedThisMonth && $deactivatedThisMonth) {
                $category = 'Ativado e desativado no mês';
            } elseif ($activatedThisMonth) {
                $category = 'Ativado no mês';
            } elseif ($deactivatedThisMonth) {
                $category = 'Desativado';
            } elseif ($suspendedDays > 0 || str_contains($condition, 'suspens')) {
                $category = 'Suspenso';
            }

            $amount = round($unit * ($billableDays / max(1, $daysInMonth)), 2, PHP_ROUND_HALF_UP);
            if ($billableDays >= $daysInMonth) {
                $amount = round($unit, 2, PHP_ROUND_HALF_UP);
            }

            $byClient[$client][] = [
                'Cliente' => $client,
                'Terminal' => trim((string) ($row['Terminal'] ?? '')),
                'Nº Equipamento' => $equipment,
                'Placa' => trim((string) ($row['Placa'] ?? '')),
                'Frota' => trim((string) ($row['Frota'] ?? '')),
                'Modelo' => $model,
                'Tipo' => $type,
                'Condição' => (string) ($row['Condição'] ?? ''),
                'Categoria' => $category,
                'Data Ativação' => $activation,
                'Data Desativação' => $deactivation,
                'Dias Ativos Mês' => $activeDays,
                'Suspenso Dias Mes' => $suspendedDays,
                'Dias a Faturar' => $billableDays,
                'Valor Unitario' => round($unit, 2),
                'Valor a Faturar' => $amount,
                'Origem Preço' => $contractPrice > 0 ? 'Contrato do cliente' : ($fallback > 0 ? 'Média dos contratos cadastrados' : 'Sem preço de referência'),
            ];
        }

        $periodLabel = $this->periodLabel($reportDate);
        $summaries = [];

        foreach ($byClient as $client => $items) {
            $summary = [
                'cliente' => $client,
                'period_key' => $periodKey,
                'periodo_relatorio' => $periodLabel,
                'valor_total' => round(array_sum(array_column($items, 'Valor a Faturar')), 2),
                'terminais_cheio' => count(array_filter($items, fn ($i) => $i['Dias a Faturar'] >= (int) $reportDate->format('t'))),
                'terminais_proporcional' => count(array_filter($items, fn ($i) => $i['Dias a Faturar'] > 0 && $i['Dias a Faturar'] < (int) $reportDate->format('t'))),
                'terminais_suspensos' => count(array_filter($items, fn ($i) => $i['Suspenso Dias Mes'] > 0)),
                'terminais_gprs' => count(array_filter($items, fn ($i) => str_contains($i['Tipo'], 'GPRS') || str_contains($i['Tipo'], 'GSM'))),
                'terminais_satelitais' => count(array_filter($items, fn ($i) => str_contains($i['Tipo'], 'SATEL'))),
                'itens_detalhados' => $this->serializableItems($items),
                'data_geracao' => now(),
                'gerado_por' => $user,
                'schema_version' => 2,
            ];

            $hash = hash('sha256', json_encode($this->hashPayload($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $existing = BillingHistory::query()
                ->where('cliente', $client)
                ->where('period_key', $periodKey)
                ->first();
            $revision = (int) ($existing->revision ?? 0) + 1;

            $historyPayload = $summary;
            $historyPayload['snapshot_hash'] = $hash;
            $historyPayload['revision'] = $revision;

            // MongoDB limita documentos a 16 MB. Mantemos margem de segurança.
            $serializedItems = json_encode($historyPayload['itens_detalhados'], JSON_UNESCAPED_UNICODE);
            if (strlen((string) $serializedItems) > 8_000_000) {
                $historyPayload['itens_detalhados'] = [];
                $historyPayload['details_externalized'] = true;
            }

            if ($existing) {
                $existing->fill($historyPayload)->save();
            } else {
                BillingHistory::query()->create($historyPayload);
            }

            $runId = (string) Str::uuid();
            BillingRun::query()->create(array_merge(
                array_diff_key($summary, ['itens_detalhados' => true]),
                [
                    'run_id' => $runId,
                    'revision' => $revision,
                    'snapshot_hash' => $hash,
                    'item_count' => count($items),
                    'source' => 'billing',
                ]
            ));

            $metrics = [
                'period_key' => $periodKey,
                'periodo_relatorio' => $periodLabel,
                'cliente' => $client,
                'receita' => $summary['valor_total'],
                'veiculos_faturados' => count($items),
                'veiculos_ativos_fim_mes' => count(array_filter($items, fn ($i) => $i['Categoria'] !== 'Desativado' && $i['Categoria'] !== 'Ativado e desativado no mês')),
                'ativacoes' => count(array_filter($items, fn ($i) => str_contains($i['Categoria'], 'Ativado'))),
                'desativacoes' => count(array_filter($items, fn ($i) => str_contains(mb_strtolower($i['Categoria']), 'desativado'))),
                'suspensoes' => count(array_filter($items, fn ($i) => $i['Suspenso Dias Mes'] > 0)),
                'terminais_cheio' => $summary['terminais_cheio'],
                'terminais_proporcional' => $summary['terminais_proporcional'],
                'terminais_suspensos' => $summary['terminais_suspensos'],
                'terminais_gprs' => $summary['terminais_gprs'],
                'terminais_satelitais' => $summary['terminais_satelitais'],
                'data_quality' => 'detalhado',
                'source_run_id' => $runId,
                'schema_version' => 2,
                'updated_at' => now(),
            ];

            BillingMonthlyMetric::query()->where('period_key', $periodKey)->where('cliente', $client)->delete();
            BillingMonthlyMetric::query()->create($metrics);

            foreach ($items as $index => $item) {
                $normalized = $this->normalizedItem($item, $client, $periodKey, $runId);
                $normalized['item_index'] = $index;
                BillingRunItem::query()->create($normalized);

                $snapshotKey = sha1($periodKey.'|'.$client.'|'.$normalized['terminal'].'|'.$normalized['equipamento']);
                BillingTerminalSnapshot::query()->where('snapshot_key', $snapshotKey)->delete();
                BillingTerminalSnapshot::query()->create(array_merge($normalized, ['snapshot_key' => $snapshotKey]));
            }

            $summaries[] = $historyPayload;
        }

        return [
            'period_key' => $periodKey,
            'period_label' => $periodLabel,
            'clients' => count($summaries),
            'terminals' => array_sum(array_map(fn ($x) => count($x['itens_detalhados'] ?? []), $summaries)),
            'total' => round(array_sum(array_column($summaries, 'valor_total')), 2),
            'summaries' => $summaries,
        ];
    }

    public function closeMonth(string $periodKey, string $user): BillingMonthClosure
    {
        $existing = BillingMonthClosure::query()
            ->where('period_key', $periodKey)
            ->where('status', 'closed')
            ->first();

        if ($existing) {
            return $existing;
        }

        $metrics = BillingMonthlyMetric::query()->where('period_key', $periodKey)->get();
        if ($metrics->isEmpty()) {
            throw new \RuntimeException('Não é possível fechar uma competência sem métricas de faturamento.');
        }

        $payload = [
            'period_key' => $periodKey,
            'periodo_relatorio' => $metrics->first()?->periodo_relatorio ?? $periodKey,
            'status' => 'closed',
            'total_clientes' => $metrics->count(),
            'total_terminais' => (int) $metrics->sum('veiculos_faturados'),
            'faturamento_total' => round((float) $metrics->sum('receita'), 2),
            'closed_at' => now(),
            'closed_by' => $user,
            'schema_version' => 2,
        ];

        return BillingMonthClosure::query()->create($payload);
    }

    private function extractReportDate(array $rows): ?\DateTimeImmutable
    {
        foreach (array_slice($rows, 0, 40) as $row) {
            $text = implode(' ', array_filter(array_map(fn ($v) => trim((string) $v), $row)));
            if (preg_match('/Data\s*Final\s*[:\-]?\s*(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/iu', $text, $m)) {
                return $this->sheets->normalizeDate($m[1]);
            }
        }

        foreach (array_slice($rows, 0, 40) as $row) {
            $text = implode(' ', $row);
            preg_match_all('/\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}/', $text, $m);
            if (!empty($m[0])) {
                $date = $this->sheets->normalizeDate(end($m[0]));
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function equipment(mixed $value): string
    {
        $text = trim((string) $value);
        return preg_replace('/\.0$/', '', $text) ?? $text;
    }

    private function type(mixed $value): string
    {
        $text = strtoupper($this->sheets->canonical((string) $value));
        $text = str_replace(['SATELITAL', 'SATELLITE', 'SATELITE'], 'SATELITE', $text);
        return trim($text);
    }

    private function priceForType(array $prices, string $type): float
    {
        foreach ($prices as $key => $value) {
            if ($this->type($key) === $type) {
                return max(0.0, (float) $value);
            }
        }
        return 0.0;
    }

    private function averageContractPrices(array $contracts): array
    {
        $bucket = [];
        foreach ($contracts as $prices) {
            foreach ($prices as $key => $value) {
                $type = $this->type($key);
                $number = (float) $value;
                if ($type !== '' && $number > 0) {
                    $bucket[$type][] = $number;
                }
            }
        }
        return array_map(fn ($values) => round(array_sum($values) / count($values), 2), $bucket);
    }

    private function periodLabel(\DateTimeInterface $date): string
    {
        $months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
        return $months[(int) $date->format('n')].' de '.$date->format('Y');
    }

    private function serializableItems(array $items): array
    {
        return array_map(function (array $item) {
            foreach (['Data Ativação', 'Data Desativação'] as $field) {
                if ($item[$field] instanceof \DateTimeInterface) {
                    $item[$field] = $item[$field]->format('Y-m-d');
                }
            }
            return $item;
        }, $items);
    }

    private function hashPayload(array $summary): array
    {
        unset($summary['data_geracao'], $summary['gerado_por'], $summary['updated_at'], $summary['latest_run_id'], $summary['revision'], $summary['snapshot_hash']);
        return $summary;
    }

    private function normalizedItem(array $item, string $client, string $periodKey, string $runId): array
    {
        return [
            'cliente' => $client,
            'period_key' => $periodKey,
            'run_id' => $runId,
            'terminal' => (string) ($item['Terminal'] ?? ''),
            'equipamento' => (string) ($item['Nº Equipamento'] ?? ''),
            'placa' => (string) ($item['Placa'] ?? ''),
            'frota' => (string) ($item['Frota'] ?? ''),
            'modelo' => (string) ($item['Modelo'] ?? ''),
            'tipo' => (string) ($item['Tipo'] ?? ''),
            'condicao' => (string) ($item['Condição'] ?? ''),
            'categoria' => (string) ($item['Categoria'] ?? ''),
            'data_ativacao' => $item['Data Ativação'] ?? null,
            'data_desativacao' => $item['Data Desativação'] ?? null,
            'dias_ativos_mes' => (int) ($item['Dias Ativos Mês'] ?? 0),
            'suspenso_dias_mes' => (int) ($item['Suspenso Dias Mes'] ?? 0),
            'dias_a_faturar' => (int) ($item['Dias a Faturar'] ?? 0),
            'valor_unitario' => round((float) ($item['Valor Unitario'] ?? 0), 2),
            'valor_faturado' => round((float) ($item['Valor a Faturar'] ?? 0), 2),
            'updated_at' => now(),
        ];
    }
}
