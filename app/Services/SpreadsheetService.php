<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class SpreadsheetService
{
    private const MAX_FILE_BYTES = 15 * 1024 * 1024;
    private const MAX_ZIP_UNCOMPRESSED_BYTES = 120 * 1024 * 1024;
    private const MAX_ZIP_ENTRIES = 5000;
    private const MAX_TOTAL_CELLS = 2_000_000;
    private const MAX_ROWS_PER_SHEET = 60_000;
    private const MAX_COLUMNS_PER_SHEET = 120;

    public function read(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (!$path || !is_file($path)) {
            throw new \RuntimeException('Arquivo de planilha inválido.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('A planilha excede o limite seguro de 15 MB.');
        }

        $this->assertZipIsSafe($path);

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalCells = 0;

        foreach ($worksheetInfo as $info) {
            $rows = (int) ($info['totalRows'] ?? 0);
            $columns = (int) ($info['totalColumns'] ?? 0);

            if ($rows > self::MAX_ROWS_PER_SHEET || $columns > self::MAX_COLUMNS_PER_SHEET) {
                throw new \RuntimeException('A planilha excede o limite seguro de linhas ou colunas.');
            }

            $totalCells += $rows * $columns;
            if ($totalCells > self::MAX_TOTAL_CELLS) {
                throw new \RuntimeException('A planilha excede o limite seguro de células.');
            }
        }

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            return $sheet->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
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
        $headers = $this->sanitizeHeaders($rows[$headerRow] ?? []);
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

    public function sanitizeHeaders(array $headers, array $reservedCanonical = []): array
    {
        if (count($headers) > self::MAX_COLUMNS_PER_SHEET) {
            throw new \RuntimeException('Quantidade de colunas acima do limite permitido.');
        }

        $seen = [];
        $result = [];

        foreach ($headers as $header) {
            $header = trim((string) $header);

            if ($header === '') {
                $result[] = '';
                continue;
            }

            if (mb_strlen($header) > 120
                || str_starts_with($header, '$')
                || str_contains($header, '.')
                || preg_match('/[\x00-\x1F\x7F]/u', $header)) {
                throw new \RuntimeException('Cabeçalho de planilha inválido ou potencialmente perigoso.');
            }

            $canonical = $this->canonical($header);
            if (in_array($canonical, $reservedCanonical, true)) {
                throw new \RuntimeException("A coluna {$header} é reservada e não pode ser importada.");
            }

            if (isset($seen[$canonical])) {
                throw new \RuntimeException("Cabeçalho duplicado encontrado: {$header}.");
            }

            $seen[$canonical] = true;
            $result[] = $header;
        }

        return $result;
    }

    private function assertZipIsSafe(string $path): void
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);

        if ($opened !== true) {
            return;
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new \RuntimeException('A planilha compactada possui entradas demais.');
            }

            $totalUncompressed = 0;
            $totalCompressed = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) {
                    continue;
                }

                $size = max(0, (int) ($stat['size'] ?? 0));
                $compressed = max(0, (int) ($stat['comp_size'] ?? 0));
                $totalUncompressed += $size;
                $totalCompressed += $compressed;

                if ($totalUncompressed > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                    throw new \RuntimeException('A planilha compactada excede o limite seguro após descompressão.');
                }
            }

            if ($totalCompressed > 0 && ($totalUncompressed / $totalCompressed) > 100) {
                throw new \RuntimeException('Taxa de compressão anormal detectada na planilha.');
            }
        } finally {
            $zip->close();
        }
    }
}
