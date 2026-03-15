<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Extractor;

use DateTimeImmutable;

final class CzechRegexExtractor
{
    public function extract(string $text): RegexExtraction
    {
        $variableSymbol = $this->extractValue('/(?:variabiln[ií]\s+symbol|VS)\s*[:#]?\s*(\d{1,10})/iu', $text);
        $supplierIco = $this->extractValue('/\bI[CČ]O\s*[:#]?\s*(\d{8})\b/iu', $text);
        $supplierDic = $this->extractValue('/\bDI[CČ]\s*[:#]?\s*([A-Z]{2}\s?\d{8,10})\b/iu', $text);
        $issuedAt = $this->extractDate('/(?:datum\s+vystaven[ií]|vystaveno)\s*[:#]?\s*([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4})/iu', $text);
        $taxableSupplyDate = $this->extractDate('/(?:DUZP|datum\s+zdaniteln[eé]ho\s+pln[eě]n[ií])\s*[:#]?\s*([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4})/iu', $text);
        $dueDate = $this->extractDate('/(?:datum\s+splatnosti|splatnost)\s*[:#]?\s*([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4})/iu', $text);

        return new RegexExtraction(
            variableSymbol: $variableSymbol,
            supplierIco: $supplierIco,
            supplierDic: $supplierDic,
            issuedAt: $issuedAt,
            taxableSupplyDate: $taxableSupplyDate,
            dueDate: $dueDate,
        );
    }

    private function extractValue(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $match) !== 1) {
            return null;
        }

        return preg_replace('/\s+/', '', trim((string) ($match[1] ?? '')));
    }

    private function extractDate(string $pattern, string $text): ?DateTimeImmutable
    {
        $value = $this->extractValue($pattern, $text);
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('j.n.Y', $value);
        return $date ?: null;
    }
}
