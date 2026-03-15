<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Tests;

use DateTimeImmutable;
use InsightBase\InvoiceParserNette\Model\InvoiceData;
use InsightBase\InvoiceParserNette\Model\PartyData;
use InsightBase\InvoiceParserNette\Validation\InvoiceValidator;
use PHPUnit\Framework\TestCase;

final class InvoiceValidatorTest extends TestCase
{
    public function testDetectsInconsistentTotals(): void
    {
        $invoice = new InvoiceData(
            invoiceNumber: '2026-001',
            supplier: new PartyData(name: 'Dodavatel s.r.o.'),
            customer: new PartyData(name: 'Odberatel a.s.'),
            issuedAt: new DateTimeImmutable('2026-02-01'),
            dueDate: new DateTimeImmutable('2026-02-15'),
            totalWithoutVat: 1000.0,
            totalVat: 210.0,
            totalWithVat: 1500.0,
        );

        $issues = (new InvoiceValidator())->validate($invoice);
        $codes = array_map(static fn ($issue): string => $issue->code, $issues);

        self::assertContains('total_mismatch', $codes);
    }
}
