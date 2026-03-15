<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

use DateTimeImmutable;
use DateTimeInterface;
use InsightBase\InvoiceParserNette\Extractor\RegexExtraction;
use InsightBase\InvoiceParserNette\Model\InvoiceData;
use InsightBase\InvoiceParserNette\Model\InvoiceItemData;
use InsightBase\InvoiceParserNette\Model\PartyData;

final class InvoiceMapper
{
    /**
     * @param array<string, mixed> $diFields
     * @param array<string, mixed> $llmData
     */
    public function map(array $diFields, RegexExtraction $regexExtraction, array $llmData = []): InvoiceData
    {
        $supplier = new PartyData(
            name: $this->pickString($llmData, ['supplier', 'name']) ?? $this->asString($diFields['VendorName'] ?? null),
            ico: $this->pickString($llmData, ['supplier', 'ico']) ?? $regexExtraction->supplierIco,
            dic: $this->pickString($llmData, ['supplier', 'dic']) ?? $regexExtraction->supplierDic,
            address: $this->pickString($llmData, ['supplier', 'address']) ?? $this->asString($diFields['VendorAddress'] ?? null),
        );

        $customer = new PartyData(
            name: $this->pickString($llmData, ['customer', 'name']) ?? $this->asString($diFields['CustomerName'] ?? null),
            ico: $this->pickString($llmData, ['customer', 'ico']),
            dic: $this->pickString($llmData, ['customer', 'dic']),
            address: $this->pickString($llmData, ['customer', 'address']) ?? $this->asString($diFields['CustomerAddress'] ?? null),
        );

        $items = $this->mapItems(
            $this->pickArray($llmData, ['items']) ?? $this->asArray($diFields['Items'] ?? null),
        );

        return new InvoiceData(
            invoiceNumber: $this->pickString($llmData, ['invoiceNumber']) ?? $this->asString($diFields['InvoiceId'] ?? null),
            variableSymbol: $this->pickString($llmData, ['variableSymbol']) ?? $regexExtraction->variableSymbol,
            currency: $this->pickString($llmData, ['currency']) ?? $this->asString($diFields['CurrencyCode'] ?? null),
            supplier: $supplier,
            customer: $customer,
            issuedAt: $this->pickDate($llmData, ['issuedAt']) ?? $this->parseDate($this->asString($diFields['InvoiceDate'] ?? null)) ?? $regexExtraction->issuedAt,
            taxableSupplyDate: $this->pickDate($llmData, ['taxableSupplyDate']) ?? $this->parseDate($this->asString($diFields['ServiceDate'] ?? null)) ?? $regexExtraction->taxableSupplyDate,
            dueDate: $this->pickDate($llmData, ['dueDate']) ?? $this->parseDate($this->asString($diFields['DueDate'] ?? null)) ?? $regexExtraction->dueDate,
            totalWithoutVat: $this->pickFloat($llmData, ['totalWithoutVat']) ?? $this->asFloat($diFields['SubTotal'] ?? null),
            totalVat: $this->pickFloat($llmData, ['totalVat']) ?? $this->asFloat($diFields['TotalTax'] ?? null),
            totalWithVat: $this->pickFloat($llmData, ['totalWithVat']) ?? $this->asFloat($diFields['InvoiceTotal'] ?? null),
            items: $items,
            metadata: [
                'source' => [
                    'diFields' => $diFields,
                    'llmData' => $llmData,
                ],
            ],
        );
    }

    /**
     * @param list<mixed> $items
     * @return list<InvoiceItemData>
     */
    private function mapItems(array $items): array
    {
        $mapped = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $mapped[] = new InvoiceItemData(
                description: $this->asString($item['description'] ?? $item['Description'] ?? null),
                quantity: $this->asFloat($item['quantity'] ?? $item['Quantity'] ?? null),
                unitPrice: $this->asFloat($item['unitPrice'] ?? $item['UnitPrice'] ?? null),
                total: $this->asFloat($item['total'] ?? $item['Amount'] ?? null),
                vatRate: $this->asFloat($item['vatRate'] ?? $item['TaxRate'] ?? null),
            );
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function pickString(array $data, array $path): ?string
    {
        $value = $this->pick($data, $path);
        return $this->asString($value);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function pickFloat(array $data, array $path): ?float
    {
        $value = $this->pick($data, $path);
        return $this->asFloat($value);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     * @return list<mixed>|null
     */
    private function pickArray(array $data, array $path): ?array
    {
        $value = $this->pick($data, $path);
        if (!is_array($value)) {
            return null;
        }

        return array_values($value);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function pickDate(array $data, array $path): ?DateTimeInterface
    {
        $value = $this->pickString($data, $path);
        return $this->parseDate($value);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function pick(array $data, array $path): mixed
    {
        $cursor = $data;
        foreach ($path as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }
            $cursor = $cursor[$key];
        }

        return $cursor;
    }

    private function asString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return list<mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'Y/m/d', 'j.n.Y', 'j.n.y', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : (new DateTimeImmutable())->setTimestamp($timestamp);
    }
}
