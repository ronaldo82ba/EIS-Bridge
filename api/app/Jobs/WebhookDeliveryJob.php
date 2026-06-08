<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Alerts\AlertEmitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class WebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $vendorId,
        public ?int $invoiceId,
        public string $event,
        public array $payload = [],
    ) {
        $this->onQueue('webhooks');
    }

    public function backoff(): array
    {
        return array_map(
            fn (int $attempt) => 30 * $attempt,
            range(1, $this->tries - 1)
        );
    }

    public function handle(): void
    {
        $vendor = Vendor::find($this->vendorId);

        if (! $vendor || empty($vendor->webhook_url)) {
            return;
        }

        $payload = $this->resolvePayload($vendor);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, (string) $vendor->webhook_secret);

        $response = Http::timeout(15)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-EISBridge-Signature' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->post($vendor->webhook_url);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'invoice_id' => $this->invoiceId,
            'event' => $this->event,
            'request_url' => $vendor->webhook_url,
            'attempt' => $this->attempts(),
            'status_code' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 4000),
            'success' => $response->successful(),
            'delivered_at' => now(),
        ]);

        if (! $response->successful()) {
            $invoice = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

            if ($response->status() >= 400) {
                AlertEmitter::webhookFailure(
                    $vendor,
                    $invoice,
                    $response->status(),
                    $this->event,
                    ['response_body' => mb_substr($response->body(), 0, 500)]
                );
            }

            $this->fail(new \RuntimeException("Webhook delivery failed with status {$response->status()}."));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(Vendor $vendor): array
    {
        if ($this->payload !== []) {
            return $this->payload;
        }

        if ($this->event === 'webhook.test') {
            return [
                'event' => $this->event,
                'data' => [
                    'message' => 'Test webhook from EIS Bridge.',
                    'vendor_id' => $vendor->id,
                ],
            ];
        }

        $invoice = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

        return [
            'event' => $this->event,
            'data' => [
                'bridge_transaction_id' => $invoice?->bridge_transaction_id,
                'transaction_id' => $invoice?->transaction_id,
                'eis_status' => $invoice?->eis_status,
                'eis_reference_no' => $invoice?->eis_reference_no,
            ],
        ];
    }
}
