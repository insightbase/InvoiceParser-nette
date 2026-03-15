<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Validation;

use InsightBase\InvoiceParserNette\Model\InvoiceData;

final class InvoiceValidator
{
    /**
     * @return list<InvoiceValidationIssue>
     */
    public function validate(InvoiceData $invoice): array
    {
        $issues = [];

        if ($invoice->supplier?->name === null) {
            $issues[] = new InvoiceValidationIssue('missing_supplier_name', 'Supplier name is missing.');
        }
        if ($invoice->customer?->name === null) {
            $issues[] = new InvoiceValidationIssue('missing_customer_name', 'Customer name is missing.');
        }
        if ($invoice->invoiceNumber === null) {
            $issues[] = new InvoiceValidationIssue('missing_invoice_number', 'Invoice number is missing.');
        }
        if ($invoice->dueDate !== null && $invoice->issuedAt !== null && $invoice->dueDate < $invoice->issuedAt) {
            $issues[] = new InvoiceValidationIssue('due_before_issue', 'Due date is before issue date.');
        }
        if ($invoice->totalWithVat !== null && $invoice->totalWithVat < 0) {
            $issues[] = new InvoiceValidationIssue('negative_total', 'Invoice total is negative.');
        }

        if (
            $invoice->totalWithVat !== null
            && $invoice->totalWithoutVat !== null
            && $invoice->totalVat !== null
        ) {
            $expected = $invoice->totalWithoutVat + $invoice->totalVat;
            if (abs($expected - $invoice->totalWithVat) > 1.0) {
                $issues[] = new InvoiceValidationIssue(
                    'total_mismatch',
                    'Total amount does not match subtotal + VAT.',
                );
            }
        }

        return $issues;
    }
}
