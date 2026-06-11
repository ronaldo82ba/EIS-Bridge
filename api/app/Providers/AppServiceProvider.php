<?php

namespace App\Providers;

use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\BillingPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CertificatePolicy;
use App\Policies\DevicePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MerchantPolicy;
use App\Policies\VendorPolicy;
use App\Support\UrlSecurity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce only on /up (DiagnosingHealth). Do not throw during HTTP kernel boot —
        // a mis-cached APP_ENV would otherwise blank-500 every route before JSON handlers run.
        Event::listen(DiagnosingHealth::class, function () {
            $this->guardProductionSandboxConfig();
        });

        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Merchant::class, MerchantPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(MerchantCertificate::class, CertificatePolicy::class);

        $billing = app(BillingPolicy::class);

        Gate::define('billing.viewPlans', fn (User $user) => $billing->viewPlans($user));
        Gate::define('billing.managePlans', fn (User $user) => $billing->managePlans($user));
        Gate::define('billing.viewVendorLicenses', fn (User $user, Vendor $vendor) => $billing->viewVendorLicenses($user, $vendor));
        Gate::define('billing.manageVendorLicenses', fn (User $user, Vendor $vendor) => $billing->manageVendorLicenses($user, $vendor));
        Gate::define('billing.viewMerchantLicenses', fn (User $user, Merchant $merchant) => $billing->viewMerchantLicenses($user, $merchant));
        Gate::define('billing.manageMerchantLicenses', fn (User $user, Merchant $merchant) => $billing->manageMerchantLicenses($user, $merchant));
        Gate::define('billing.viewInvoices', fn (User $user) => $billing->viewInvoices($user));
        Gate::define('billing.viewInvoice', fn (User $user, BillingInvoice $invoice) => $billing->viewInvoice($user, $invoice));
        Gate::define('billing.generateInvoices', fn (User $user) => $billing->generateInvoices($user));
        Gate::define('billing.viewSummary', fn (User $user) => $billing->viewSummary($user));

        RateLimiter::for('vendor-api', function (Request $request) {
            $vendor = $request->attributes->get('vendor');

            return Limit::perMinute((int) config('security.vendor_api_rate_limit', 120))
                ->by('vendor:'.($vendor?->id ?: $request->ip()))
                ->response(fn () => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Rate limit exceeded.',
                ], 429));
        });

        RateLimiter::for('vendor-transactions', function (Request $request) {
            $vendor = $request->attributes->get('vendor');

            return Limit::perMinute((int) config('security.vendor_transaction_rate_limit', 60))
                ->by('vendor-tx:'.($vendor?->id ?: $request->ip()))
                ->response(fn () => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Transaction rate limit exceeded.',
                ], 429));
        });

        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute((int) config('security.admin_api_rate_limit', 60))
                ->by('admin:'.($request->user()?->id ?: $request->ip()))
                ->response(fn () => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Rate limit exceeded.',
                ], 429));
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('security.login_rate_limit', 5))
                ->by('login:'.$request->ip())
                ->response(fn () => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Too many login attempts. Please try again later.',
                ], 429));
        });
    }

    private function guardProductionSandboxConfig(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if (config('eis.sandbox_mode')) {
            $liveEnv = $this->readAppEnvFromEnvironmentFile();
            if ($liveEnv === 'staging') {
                return;
            }

            throw new RuntimeException(
                'EIS sandbox mode (EIS_SANDBOX_MODE=true) cannot be enabled when APP_ENV=production.'
            );
        }

        $endpoint = trim((string) config('eis.endpoint', ''));
        if ($endpoint === '') {
            throw new RuntimeException(
                'EIS endpoint must be configured when APP_ENV=production and EIS_SANDBOX_MODE=false.'
            );
        }

        if (! UrlSecurity::isAllowedPublicHttpsUrl($endpoint)) {
            throw new RuntimeException(
                'EIS endpoint must be an HTTPS URL with a public host when APP_ENV=production and EIS_SANDBOX_MODE=false.'
            );
        }
    }

    private function readAppEnvFromEnvironmentFile(): ?string
    {
        $path = $this->app->environmentFilePath();

        if (! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (preg_match('/^\s*APP_ENV\s*=\s*(["\']?)([\w-]+)\1\s*$/m', $contents, $matches) === 1) {
            return $matches[2];
        }

        if (preg_match('/^\s*APP_ENV\s*=\s*["\']?([\w-]+)/m', $contents, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
