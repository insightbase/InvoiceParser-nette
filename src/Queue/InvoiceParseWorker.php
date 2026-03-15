<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Queue;

use InsightBase\InvoiceParserNette\Parser\InvoiceParser;
use RuntimeException;

final class InvoiceParseWorker
{
    public function __construct(
        private readonly InvoiceParser $invoiceParser,
    ) {
    }

    /**
     * @param array{pdfPath?: string, pdfBase64?: string} $message
     * @return array<string, mixed>
     */
    public function process(array $message): array
    {
        $pdfContent = $this->resolvePdfContent($message);
        $result = $this->invoiceParser->parsePdf($pdfContent);

        return [
            'invoice' => $result->invoice->toArray(),
            'warnings' => array_map(
                static fn ($warning): array => ['code' => $warning->code, 'message' => $warning->message],
                $result->warnings,
            ),
        ];
    }

    /**
     * @param array{pdfPath?: string, pdfBase64?: string} $message
     */
    private function resolvePdfContent(array $message): string
    {
        if (isset($message['pdfBase64'])) {
            $decoded = base64_decode($message['pdfBase64'], true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if (isset($message['pdfPath']) && is_file($message['pdfPath'])) {
            $content = file_get_contents($message['pdfPath']);
            if ($content !== false) {
                return $content;
            }
        }

        throw new RuntimeException('Message must contain valid pdfBase64 or pdfPath.');
    }
}
