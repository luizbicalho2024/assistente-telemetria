<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SpreadsheetService
{
    public function read(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (!$path) {
            throw new \RuntimeException('Arquivo de planilha inválido.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    public function canonical(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace(["\n", "\r"], ' ', $value)) ?? '');
        $value = str_replace(['º', 'ª'], ['o', 'a'], $value);
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return mb_strtolower(trim($normalized !== false ? $normalized : $value));
    }

    public function normalizeDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \DateTimeImmutable::createFromMutable(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, trim((string) $value));
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function findHeaderRow(array $rows, array $requiredCanonical = ['cliente', 'terminal']): int
    {
        foreach (array_slice($rows, 0, 60, true) as $index => $row) {
            $keys = array_map(fn ($v) => $this->canonical((string) $v), $row);
            $ok = true;
            foreach ($requiredCanonical as $required) {
                if (!in_array($required, $keys, true)) {
                    $ok = false;
                    break;
                }
            }
            $hasEquipment = array_intersect($keys, ['equipamento', 'n equipamento', 'numero equipamento']);
            if ($ok && $hasEquipment) {
                return (int) $index;
            }
        }
        throw new \RuntimeException('Não foi possível encontrar o cabeçalho. A planilha deve conter Cliente, Terminal e Equipamento/Nº Equipamento.');
    }

    public function rowsWithHeaders(array $rows, int $headerRow): array
    {
        $headers = array_map(fn ($v) => trim((string) $v), $rows[$headerRow] ?? []);
        $result = [];

        foreach (array_slice($rows, $headerRow + 1) as $row) {
            $mapped = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $mapped[$header] = $row[$index] ?? null;
            }
            if ($mapped) {
                $result[] = $mapped;
            }
        }
        return $result;
    }
}
