<?php

namespace Tests\Unit;

use App\Services\SpreadsheetService;
use PHPUnit\Framework\TestCase;

class SpreadsheetServiceSecurityTest extends TestCase
{
    public function test_rejects_mongo_operator_header(): void
    {
        $service = new SpreadsheetService();

        $this->expectException(\RuntimeException::class);
        $service->sanitizeHeaders(['Nº Equipamento', '$where']);
    }

    public function test_rejects_dot_notation_header(): void
    {
        $service = new SpreadsheetService();

        $this->expectException(\RuntimeException::class);
        $service->sanitizeHeaders(['Nº Equipamento', 'owner.password']);
    }

    public function test_rejects_reserved_header(): void
    {
        $service = new SpreadsheetService();

        $this->expectException(\RuntimeException::class);
        $service->sanitizeHeaders(['Nº Equipamento', '_id'], ['_id']);
    }
}
