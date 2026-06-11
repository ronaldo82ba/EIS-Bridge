<?php

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\Billing\BillingController;
use App\Http\Controllers\Admin\Billing\LicensePlanController;
use App\Http\Controllers\Admin\Billing\MerchantLicenseController;
use App\Http\Controllers\Admin\Billing\VendorLicenseController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CertificateAlertController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\Logs\AuditLogController;
use App\Http\Controllers\Admin\Logs\LogExportController;
use App\Http\Controllers\Admin\Logs\SystemLogController;
use App\Http\Controllers\Admin\Logs\TransmissionLogController;
use App\Http\Controllers\Admin\Logs\WebhookLogController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MerchantReadinessController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\PttController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\TokenController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\VendorIpWhitelistController;
use App\Http\Controllers\Admin\WebhookController;
use App\Models\MerchantCertificate;
use Illuminate\Support\Facades\Route;

Route::bind('certificate', fn (string $value) => MerchantCertificate::findOrFail($value));

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/tokens/revoke-all', [TokenController::class, 'revokeAll']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/certificate-alerts', [CertificateAlertController::class, 'index']);

    Route::get('/vendors/{vendor}/analytics', [VendorController::class, 'analytics']);
    Route::get('/vendors/{vendor}/health', [VendorController::class, 'health']);
    Route::apiResource('vendors', VendorController::class);
    Route::post('/vendors/{vendor}/rotate-api-key', [VendorController::class, 'rotateApiKey']);
    Route::post('/vendors/{vendor}/regenerate-key', [VendorController::class, 'rotateApiKey']);
    Route::post('/vendors/{vendor}/suspend', [VendorController::class, 'suspend']);
    Route::get('/merchants/{merchant}/analytics', [MerchantController::class, 'analytics']);
    Route::get('/merchants/{merchant}/health', [MerchantController::class, 'health']);
    Route::apiResource('merchants', MerchantController::class);
    Route::get('/merchants/{merchant}/readiness', [MerchantReadinessController::class, 'show']);
    Route::get('/merchants/{merchant}/activity', [MerchantController::class, 'activity']);
    Route::post('/merchants/{merchant}/certificate', [CertificateController::class, 'storeForMerchant']);
    Route::apiResource('branches', BranchController::class);
    Route::post('/branches/{branch}/devices', [DeviceController::class, 'storeForBranch']);
    Route::apiResource('devices', DeviceController::class);
    Route::get('/invoices/analytics', [InvoiceController::class, 'analytics']);
    Route::get('/invoices/search', [InvoiceController::class, 'search']);
    Route::post('/invoices/bulk', [InvoiceController::class, 'bulk'])
        ->middleware('support.write:bulk_invoice');
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
    Route::post('/invoices/{invoice}/retry', [InvoiceController::class, 'retry']);
    Route::apiResource('certificates', CertificateController::class)
        ->only(['index', 'store', 'show', 'destroy']);
    Route::post('certificates/{certificate}/test-signing', [CertificateController::class, 'testSigning']);

    Route::get('webhooks', [WebhookController::class, 'index']);
    Route::get('webhooks/{vendor}', [WebhookController::class, 'show']);
    Route::patch('webhooks/{vendor}', [WebhookController::class, 'update']);
    Route::post('webhooks/{vendor}/test', [WebhookController::class, 'test']);
    Route::get('webhooks/{vendor}/deliveries', [WebhookController::class, 'deliveries']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);

    Route::get('/queues', [QueueController::class, 'index']);
    Route::get('/jobs/failed', [QueueController::class, 'failed']);
    Route::post('/jobs/{id}/retry', [QueueController::class, 'retry'])
        ->middleware('support.write:retry_job');
    Route::delete('/jobs/{id}', [QueueController::class, 'destroy'])
        ->middleware('role:super_admin');

    Route::middleware('role:super_admin,support')->group(function () {
        Route::prefix('monitoring')->group(function () {
            Route::get('/queues', [MonitoringController::class, 'queues']);
            Route::get('/workers', [MonitoringController::class, 'workers']);
            Route::get('/failed', [MonitoringController::class, 'failed']);
            Route::get('/health', [MonitoringController::class, 'health']);
        });

        Route::prefix('alerts')->group(function () {
            Route::get('/summary', [AlertController::class, 'summary']);
            Route::get('/', [AlertController::class, 'index']);
            Route::post('/{alert}/acknowledge', [AlertController::class, 'acknowledge'])
                ->middleware('support.write:acknowledge_alert');
            Route::post('/{alert}/resolve', [AlertController::class, 'resolve'])
                ->middleware('role:super_admin');
        });

        Route::prefix('logs')->group(function () {
            Route::get('/system', [SystemLogController::class, 'index']);
            Route::get('/transmission', [TransmissionLogController::class, 'index']);
            Route::get('/audit', [AuditLogController::class, 'index']);
            Route::get('/export', [LogExportController::class, 'export'])
                ->middleware('role:super_admin');
        });
    });

    Route::middleware('role:super_admin,support,vendor_admin')->group(function () {
        Route::get('/logs/webhooks', [WebhookLogController::class, 'index']);
    });

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:super_admin');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('role:super_admin');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])
        ->middleware('role:super_admin');

    Route::post('/ptts', [PttController::class, 'store']);
    Route::put('/merchants/{merchant}/ptt', [PttController::class, 'upsert']);

    Route::get('/vendors/{vendor}/ip-whitelist', [VendorIpWhitelistController::class, 'index']);
    Route::post('/vendors/{vendor}/ip-whitelist', [VendorIpWhitelistController::class, 'store']);
    Route::delete('/vendors/{vendor}/ip-whitelist/{whitelist}', [VendorIpWhitelistController::class, 'destroy']);

    Route::prefix('billing')->group(function () {
        Route::get('/summary', [BillingController::class, 'summary']);
        Route::get('/invoices', [BillingController::class, 'invoices']);
        Route::get('/invoices/{invoice}', [BillingController::class, 'show']);
        Route::post('/generate', [BillingController::class, 'generate']);
    });

    Route::get('/license-plans', [LicensePlanController::class, 'index']);
    Route::post('/license-plans', [LicensePlanController::class, 'store'])
        ->middleware('role:super_admin');

    Route::get('/vendors/{vendor}/licenses', [VendorLicenseController::class, 'index']);
    Route::post('/vendors/{vendor}/licenses', [VendorLicenseController::class, 'store']);
    Route::get('/merchants/{merchant}/licenses', [MerchantLicenseController::class, 'index']);
    Route::post('/merchants/{merchant}/licenses', [MerchantLicenseController::class, 'store']);
});
