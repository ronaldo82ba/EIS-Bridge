<?php

use App\Services\Security\VendorApiKeyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->timestamp('api_key_rotated_at')->nullable()->after('api_key');
            $table->string('api_key_previous')->nullable()->after('api_key_rotated_at');
        });

        Schema::create('vendor_ip_whitelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        $this->hashExistingApiKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('vendor_ip_whitelists');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['api_key_rotated_at', 'api_key_previous']);
        });
    }

    private function hashExistingApiKeys(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        $service = app(VendorApiKeyService::class);

        foreach (DB::table('vendors')->orderBy('id')->get() as $vendor) {
            if ($this->looksHashed($vendor->api_key)) {
                continue;
            }

            DB::table('vendors')->where('id', $vendor->id)->update([
                'api_key' => $service->hashKey($vendor->api_key),
            ]);
        }
    }

    private function looksHashed(?string $value): bool
    {
        return is_string($value)
            && strlen($value) === 64
            && ctype_xdigit($value);
    }
};
