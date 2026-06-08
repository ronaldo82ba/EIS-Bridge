<?php

namespace App\Services\Eis;

use App\Models\Invoice;
use App\Models\TransmissionLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class EisClient
{
    public function __construct(
        private EisResponseParser $responseParser,
    ) {}

    /**
     * POST signed payload to BIR EIS and log the attempt to transmission_logs.
     *
     * @param  array<string, mixed>  $signedPayload
     * @return array{success: bool, eis_status: string, eis_reference_no: ?string, error?: string, raw?: array}
     */
    public function send(array $signedPayload, Invoice $invoice): array
    {
        if (empty($signedPayload)) {
            throw new RuntimeException('Signed payload is empty.');
        }

        if (config('eis.sandbox_mode')) {
            return $this->sendSandbox($invoice, $signedPayload);
        }

        return $this->sendProduction($invoice, $signedPayload);
    }

    /**
     * @param  array<string, mixed>  $signedPayload
     */
    private function sendProduction(Invoice $invoice, array $signedPayload): array
    {
        $endpoint = config('eis.endpoint');

        if (empty($endpoint)) {
            throw new RuntimeException('EIS endpoint is not configured.');
        }

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'transmitting',
            'timestamp' => now(),
            'metadata' => [
                'direction' => 'outbound',
                'endpoint' => $endpoint,
                'payload' => $signedPayload,
                'mtls' => config('eis.mtls.enabled', false),
            ],
        ]);

        try {
            $response = $this->httpClient()
                ->timeout(config('eis.timeout', 30))
                ->acceptJson()
                ->post($endpoint, $signedPayload);
        } catch (ConnectionException $e) {
            $this->logTransmissionFailure($invoice, $endpoint, $e->getMessage(), $signedPayload);

            throw new RuntimeException('EIS connection failed: '.$e->getMessage(), 0, $e);
        }

        $parsed = $this->responseParser->parse(
            $response->json() ?? ['message' => $response->body()],
            $response->status()
        );

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => $parsed['success'] ? 'sent_to_eis' : 'eis_rejected',
            'timestamp' => now(),
            'metadata' => [
                'direction' => 'outbound',
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $parsed['raw'] ?? null,
                'eis_status' => $parsed['eis_status'],
                'eis_reference_no' => $parsed['eis_reference_no'],
                'error' => $parsed['error'] ?? null,
            ],
        ]);

        if (! $parsed['success']) {
            Log::warning('EIS rejected invoice transmission', [
                'invoice_id' => $invoice->id,
                'status_code' => $response->status(),
                'eis_status' => $parsed['eis_status'],
            ]);
        }

        return $parsed;
    }

    private function httpClient()
    {
        $client = Http::withHeaders([
            'User-Agent' => 'EIS-Bridge/'.config('app.name', 'EIS Bridge'),
        ]);

        if (! config('eis.mtls.enabled')) {
            return $client;
        }

        $certPath = config('eis.mtls.client_cert_path');
        $keyPath = config('eis.mtls.client_key_path');

        if (empty($certPath) || empty($keyPath)) {
            throw new RuntimeException('EIS mTLS is enabled but cert/key paths are not configured.');
        }

        if (! is_file($certPath) || ! is_file($keyPath)) {
            throw new RuntimeException('EIS mTLS certificate or key file not found.');
        }

        return $client->withOptions([
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signedPayload
     */
    private function logTransmissionFailure(Invoice $invoice, string $endpoint, string $message, array $signedPayload): void
    {
        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'transmission_failed',
            'timestamp' => now(),
            'metadata' => [
                'direction' => 'outbound',
                'endpoint' => $endpoint,
                'payload' => $signedPayload,
                'status' => 'connection_failed',
                'response' => ['message' => $message],
            ],
        ]);

        Log::error('EIS transmission connection failure', [
            'invoice_id' => $invoice->id,
            'endpoint' => $endpoint,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signedPayload
     */
    private function sendSandbox(Invoice $invoice, array $signedPayload): array
    {
        $reference = 'EIS-INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'sent_to_eis',
            'timestamp' => now(),
            'metadata' => [
                'direction' => 'outbound',
                'endpoint' => config('eis.endpoint'),
                'payload' => $signedPayload,
                'status' => 200,
                'response' => [
                    'status' => 'acknowledged',
                    'reference_no' => $reference,
                    'sandbox' => true,
                ],
                'sandbox' => true,
                'eis_reference_no' => $reference,
            ],
        ]);

        return [
            'success' => true,
            'eis_status' => 'acknowledged',
            'eis_reference_no' => $reference,
            'raw' => [
                'status' => 'acknowledged',
                'reference_no' => $reference,
                'sandbox' => true,
            ],
        ];
    }
}
