<?php

namespace App\Enums;

enum SupportWriteAction: string
{
    case AcknowledgeAlert = 'acknowledge_alert';
    case RetryJob = 'retry_job';
    case ResendWebhook = 'resend_webhook';
    case BulkInvoice = 'bulk_invoice';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
