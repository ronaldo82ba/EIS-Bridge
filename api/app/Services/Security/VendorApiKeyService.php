<?php

namespace App\Services\Security;

use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorApiKeyService
{
    public function hashKey(string $plainKey): string
    {
        return hash_hmac('sha256', $plainKey, (string) config('app.key'));
    }

    public function generatePlainKey(): string
    {
        return 'vb_'.Str::random(40);
    }

    public function validate(string $plainKey): ?Vendor
    {
        $hash = $this->hashKey($plainKey);

        $vendor = Vendor::where('api_key', $hash)->first();

        if ($vendor) {
            return $vendor;
        }

        $graceHours = (int) config('security.api_key_grace_hours', 24);

        if ($graceHours <= 0) {
            return null;
        }

        return Vendor::query()
            ->whereNotNull('api_key_previous')
            ->where('api_key_previous', $hash)
            ->where('api_key_rotated_at', '>=', now()->subHours($graceHours))
            ->first();
    }

    /**
     * @return array{vendor: Vendor, plain_key: string}
     */
    public function rotate(Vendor $vendor): array
    {
        $plainKey = $this->generatePlainKey();

        $vendor->api_key_previous = $vendor->api_key;
        $vendor->api_key = $this->hashKey($plainKey);
        $vendor->api_key_rotated_at = now();
        $vendor->save();

        return [
            'vendor' => $vendor->fresh(),
            'plain_key' => $plainKey,
        ];
    }

    /**
     * @return array{vendor: Vendor, plain_key: string}
     */
    public function assignInitialKey(Vendor $vendor, ?string $plainKey = null): array
    {
        $plainKey ??= $this->generatePlainKey();

        $vendor->api_key = $this->hashKey($plainKey);
        $vendor->api_key_previous = null;
        $vendor->api_key_rotated_at = null;
        $vendor->save();

        return [
            'vendor' => $vendor->fresh(),
            'plain_key' => $plainKey,
        ];
    }
}
