<?php

namespace App\Services\Mapping;

class CustomerMapper
{
    public function map(?array $customer): ?array
    {
        if (empty($customer)) {
            return null;
        }

        $mapped = [];

        if (! empty($customer['name'])) {
            $mapped['buyer_name'] = trim((string) $customer['name']);
        }

        if (! empty($customer['tin'])) {
            $mapped['buyer_tin'] = $this->normalizeTin((string) $customer['tin']);
        }

        if (! empty($customer['address'])) {
            $mapped['buyer_address'] = trim((string) $customer['address']);
        }

        if (! empty($customer['email'])) {
            $mapped['buyer_email'] = strtolower(trim((string) $customer['email']));
        }

        if (! empty($customer['mobile'])) {
            $mapped['buyer_mobile'] = preg_replace('/\s+/', '', (string) $customer['mobile']);
        }

        return $mapped ?: null;
    }

    private function normalizeTin(string $tin): string
    {
        return preg_replace('/\s+/', '', $tin);
    }
}
