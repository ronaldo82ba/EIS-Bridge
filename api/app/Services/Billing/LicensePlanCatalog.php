<?php

namespace App\Services\Billing;

use App\Models\LicensePlan;
use Illuminate\Support\Collection;

class LicensePlanCatalog
{
    public function allActive(): Collection
    {
        return LicensePlan::query()
            ->active()
            ->orderBy('slug')
            ->get();
    }

    public function byCategory(?string $category = null): Collection
    {
        $query = LicensePlan::query()->active()->orderBy('slug');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    public function findBySlug(string $slug): ?LicensePlan
    {
        return LicensePlan::query()
            ->where('slug', $slug)
            ->first();
    }

    public function requireBySlug(string $slug): LicensePlan
    {
        $plan = $this->findBySlug($slug);

        if (! $plan) {
            throw new \InvalidArgumentException("License plan [{$slug}] not found.");
        }

        return $plan;
    }
}
