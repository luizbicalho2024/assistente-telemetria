<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Tracker;
use App\Services\ActivityLogger;
use App\Services\SpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tracker::query()->orderBy('Modelo', 'asc');
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($q) use ($term) {
                $q->where('Nº Equipamento', 'like', "%{$term}%")
                    ->orWhere('Modelo', 'like', "%{$term}%")
                    ->orWhere('Tipo', 'like', "%{$term}%");
            });
        }
        return view('inventory.index', ['items' => $query->limit(1500)->get()]);
    }

    public function import(Request $request, SpreadsheetService $sheets, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(['file' => ['required','file','mimes:xlsx,xls,csv','max:30720']]);
        $rows = $sheets->read($request->file('file'));
        if (!$rows) {
            return back()->withErrors(['file' => 'Planilha vazia.']);
        }

        $headerIndex = 0;
        foreach (array_slice($rows, 0, 60, true) as $index => $candidate) {
            $keys = array_map(fn ($v) => $sheets->canonical((string) $v), $candidate);
            if (array_intersect($keys, ['equipamento', 'n equipamento', 'numero equipamento'])) {
                $headerIndex = (int) $index;
                break;
            }
        }
        $headers = array_map(fn ($v) => trim((string) $v), $rows[$headerIndex]);
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $data = [];
            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $data[$header] = $row[$i] ?? null;
                }
            }

            $equipment = trim((string) ($data['Nº Equipamento'] ?? $data['Equipamento'] ?? $data['equipamento'] ?? ''));
            if ($equipment === '') {
                continue;
            }

            $existing = Tracker::query()->where('Nº Equipamento', $equipment)->first();
            $payload = $data + ['Nº Equipamento' => preg_replace('/\.0$/', '', $equipment)];
            if ($existing) {
                $existing->fill($payload)->save();
            } else {
                Tracker::query()->create($payload);
            }
        }

        $logger->log('Estoque importado', ['arquivo' => $request->file('file')->getClientOriginalName()]);
        return back()->with('success', 'Estoque importado/atualizado.');
    }
}
