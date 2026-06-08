<?php

/**
 * Deviations from user skeleton:
 * - Model is MerchantCertificate (skeleton uses Certificate); route binding in admin.php.
 * - Upload delegated to CertificateStorageService (private disk, structure validation, expiry extraction).
 * - Accepts skeleton field `certificate` or React UI field `file` for the multipart upload.
 * - index added for certificate list page; storeForMerchant alias route retained.
 * - Audit logging on upload/delete; destroy returns 204.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Services\Audit\AuditLogger;
use App\Support\AdminScope;
use App\Services\Certificate\CertificateStorageService;
use App\Services\Signing\CertificateLoader;
use App\Services\Signing\JsonSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CertificateController extends AdminController
{
    public function __construct(
        private readonly CertificateStorageService $certificateStorage,
        private readonly CertificateLoader $certificateLoader,
        private readonly JsonSigner $jsonSigner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MerchantCertificate::class);

        $query = AdminScope::scopeMerchantCertificates(
            MerchantCertificate::query()->with('merchant:id,name,merchant_code,vendor_id'),
            $this->adminUser()
        )->orderByDesc('expires_at');

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->integer('merchant_id'));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function show(MerchantCertificate $certificate): JsonResponse
    {
        $this->authorize('view', $certificate);

        $certificate->load([
            'merchant:id,name,merchant_code,vendor_id',
            'certificateAlerts' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return response()->json(['data' => $this->formatCertificate($certificate)]);
    }

    public function testSigning(MerchantCertificate $certificate): JsonResponse
    {
        $this->authorize('view', $certificate);

        $certificate->loadMissing('merchant');

        $samplePayload = [
            'document_type' => 'OR',
            'transaction_id' => 'TEST-'.now()->format('YmdHis'),
            'transaction_datetime' => now()->toIso8601String(),
            'currency' => 'PHP',
            'merchant' => [
                'code' => $certificate->merchant?->merchant_code ?? 'TEST',
                'name' => $certificate->merchant?->name ?? 'Test Merchant',
                'tin' => $certificate->merchant?->tin ?? '',
            ],
            'branch' => ['code' => 'TEST'],
            'device' => ['pos_device_id' => 'TEST'],
            'line_items' => [[
                'line_no' => 1,
                'sku' => 'TEST',
                'description' => 'Test item',
                'quantity' => 1,
                'unit_price' => 100,
                'gross_amount' => 100,
            ]],
            'totals' => [
                'gross_amount' => 100,
                'discount_amount' => 0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0,
                'zero_rated_sales' => 0,
                'service_charge' => 0,
                'net_amount' => 100,
            ],
            'payment' => ['method' => 'CASH', 'amount' => 100],
            'eis_fields' => [
                'submission_version' => '1.0',
                'source' => 'EIS_BRIDGE',
            ],
        ];

        try {
            $cert = $this->certificateLoader->load($certificate);
            $signed = $this->jsonSigner->sign($samplePayload, $cert['path'], $cert['password']);

            return response()->json([
                'success' => true,
                'algorithm' => $signed['algorithm'],
                'signature_hash' => $signed['signature_hash'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function storeForMerchant(Request $request, Merchant $merchant): JsonResponse
    {
        $this->authorize('view', $merchant);

        $request->merge(['merchant_id' => $merchant->id]);

        return $this->store($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MerchantCertificate::class);

        $data = $request->validate([
            'merchant_id'  => ['required', 'exists:merchants,id'],
            'certificate'  => ['nullable', 'file', 'mimes:pfx,p12,pem'],
            'file'         => ['nullable', 'file', 'mimes:pfx,p12,pem'],
            'password'     => ['required', 'string'],
            'expires_at'   => ['nullable', 'date'],
        ]);

        $file = $this->resolveUploadFile($request);
        $merchant = Merchant::findOrFail($data['merchant_id']);
        $this->authorize('view', $merchant);

        $certificate = $this->certificateStorage->store(
            $file,
            $merchant,
            $this->adminUser(),
            $data['password'],
        );

        if (! empty($data['expires_at'])) {
            $certificate->update(['expires_at' => $data['expires_at']]);
        }

        AuditLogger::log($this->adminUser(), 'uploaded_certificate', 'merchant', $merchant->id, [
            'certificate_id' => $certificate->id,
            'filename'       => $certificate->filename,
        ]);

        return response()->json(['data' => $certificate], 201);
    }

    public function destroy(MerchantCertificate $certificate): JsonResponse
    {
        $this->authorize('delete', $certificate);

        $merchantId = $certificate->merchant_id;
        $certificateId = $certificate->id;

        $this->certificateStorage->delete($certificate);
        $certificate->delete();

        AuditLogger::log($this->adminUser(), 'deleted_certificate', 'merchant', $merchantId, [
            'certificate_id' => $certificateId,
        ]);

        return response()->json(null, 204);
    }

    /** Accept skeleton `certificate` or React UI `file` upload field. */
    private function resolveUploadFile(Request $request): UploadedFile
    {
        $file = $request->file('certificate') ?? $request->file('file');

        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => ['A certificate file is required.'],
            ]);
        }

        return $file;
    }

    /** @return array<string, mixed> */
    private function formatCertificate(MerchantCertificate $certificate): array
    {
        $extension = pathinfo($certificate->filename, PATHINFO_EXTENSION) ?: 'pfx';

        return [
            'id' => $certificate->id,
            'filename' => $certificate->filename,
            'file_path' => $certificate->filename,
            'storage_path_display' => "certificates/{$certificate->merchant_id}/***.{$extension}",
            'expires_at' => $certificate->expires_at,
            'created_at' => $certificate->created_at,
            'parsed_at' => $certificate->parsed_at,
            'password_status' => 'encrypted',
            'merchant' => $certificate->merchant,
            'alerts' => $certificate->certificateAlerts->map(fn ($alert) => [
                'id' => $alert->id,
                'level' => $alert->level,
                'notified_admin' => $alert->notified_admin,
                'notified_vendor' => $alert->notified_vendor,
                'created_at' => $alert->created_at,
            ])->values(),
        ];
    }
}
