<?php

namespace App\Services\Mapping;

class TotalsMapper
{
    public function map(array $posTotals, array $lineItems): array
    {
        $computed = $this->computeFromLines($lineItems);

        return [
            'gross_amount' => $this->decimal($posTotals['gross'] ?? $computed['gross_amount']),
            'discount_amount' => $this->decimal($posTotals['discount'] ?? 0),
            'vatable_sales' => $this->decimal($posTotals['vatable_sales'] ?? $computed['vatable_sales']),
            'vat_amount' => $this->decimal($posTotals['vat_amount'] ?? $computed['vat_amount']),
            'vat_exempt_sales' => $this->decimal($posTotals['vat_exempt_sales'] ?? $computed['vat_exempt_sales']),
            'zero_rated_sales' => $this->decimal($posTotals['zero_rated_sales'] ?? $computed['zero_rated_sales']),
            'service_charge' => $this->decimal($posTotals['service_charge'] ?? 0),
            'net_amount' => $this->decimal($posTotals['net'] ?? $computed['net_amount']),
        ];
    }

    private function computeFromLines(array $lineItems): array
    {
        $totals = [
            'gross_amount' => 0.0,
            'vatable_sales' => 0.0,
            'vat_amount' => 0.0,
            'vat_exempt_sales' => 0.0,
            'zero_rated_sales' => 0.0,
            'net_amount' => 0.0,
        ];

        foreach ($lineItems as $item) {
            $totals['gross_amount'] += $item['gross_amount'];
            $totals['vatable_sales'] += $item['vatable_sales'];
            $totals['vat_amount'] += $item['vat_amount'];
            $totals['vat_exempt_sales'] += $item['vat_exempt_sales'];
            $totals['zero_rated_sales'] += $item['zero_rated_sales'];
        }

        $totals['net_amount'] = $totals['gross_amount'];

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->decimal($value);
        }

        return $totals;
    }

    private function decimal(float|int|string $value): float
    {
        return round((float) $value, 2);
    }
}
