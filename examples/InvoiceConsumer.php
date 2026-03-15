<?php

declare(strict_types=1);

namespace App\Messaging;

use InsightBase\InvoiceParserNette\Queue\InvoiceParseWorker;
use PhpAmqpLib\Message\AMQPMessage;

final class InvoiceConsumer
{
    public function __construct(
        private readonly InvoiceParseWorker $worker,
    ) {
    }

    public function consume(AMQPMessage $message): void
    {
        /** @var array{pdfPath?: string, pdfBase64?: string} $payload */
        $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $result = $this->worker->process($payload);

        // Persist parsed invoice to DB, send event, etc.
        // $result['invoice'] contains normalized invoice payload.
    }
}
