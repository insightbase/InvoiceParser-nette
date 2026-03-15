<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Tests;

use InsightBase\InvoiceParserNette\Extractor\CzechRegexExtractor;
use PHPUnit\Framework\TestCase;

final class CzechRegexExtractorTest extends TestCase
{
    public function testExtractsBasicCzechFields(): void
    {
        $text = <<<TEXT
        Faktura č. 2026-001
        Variabilní symbol: 2026001
        IČO: 12345678
        DIČ: CZ12345678
        Datum vystavení: 3.2.2026
        DUZP: 5.2.2026
        Datum splatnosti: 17.2.2026
        TEXT;

        $extractor = new CzechRegexExtractor();
        $result = $extractor->extract($text);

        self::assertSame('2026001', $result->variableSymbol);
        self::assertSame('12345678', $result->supplierIco);
        self::assertSame('CZ12345678', $result->supplierDic);
        self::assertSame('2026-02-03', $result->issuedAt?->format('Y-m-d'));
        self::assertSame('2026-02-05', $result->taxableSupplyDate?->format('Y-m-d'));
        self::assertSame('2026-02-17', $result->dueDate?->format('Y-m-d'));
    }
}
