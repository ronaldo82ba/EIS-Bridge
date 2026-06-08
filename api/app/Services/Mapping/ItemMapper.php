<?php

namespace App\Services\Mapping;

class ItemMapper
{
    private const DEFAULT_VAT_RATE = 12.0;

    public function map(array $items): array
    {
        $mapped = [];

        foreach ($items as $index => $item) {
            $lineNo = (int) ($item['line_no'] ?? ($index + 1));
            $qty = $this->decimal($item['qty']);
            $unitPrice = $this->decimal($item['unit_price']);
            $discount = $this->decimal($item['discount'] ?? 0);
            $gross = $this->decimal(($qty * $unitPrice) - $discount);

            $vatExempt = (bool) ($item['vat_exempt'] ?? false);
            $zeroRated = (bool) ($item['zero_rated'] ?? false);
            $vatRate = $this->decimal($item['vat_rate'] ?? self::DEFAULT_VAT_RATE);

            $vatableSales = 0.0;
            $vatAmount = 0.0;
            $vatExemptSales = 0.0;
            $zeroRatedSales = 0.0;

            if ($vatExempt) {
                $vatExemptSales = $gross;
            } elseif ($zeroRated) {
                $zeroRatedSales = $gross;
            } else {
                $vatableSales = $this->decimal($gross / (1 + ($vatRate / 100)));
                $vatAmount = $this->decimal($gross - $vatableSales);
            }

            $mapped[] = [
                'line_no' => $lineNo,
                'sku' => (string) $item['sku'],
                'barcode' => isset($item['barcode']) ? (string) $item['barcode'] : null,
                'description' => (string) $item['description'],
                'quantity' => $qty,
                'unit' => (string) ($item['unit'] ?? 'PCS'),
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'gross_amount' => $gross,
                'vat_rate' => $vatRate,
                'vatable_sales' => $vatableSales,
                'vat_amount' => $vatAmount,
                'vat_exempt_sales' => $vatExemptSales,
                'zero_rated_sales' => $zeroRatedSales,
                'vat_exempt' => $vatExempt,
                'zero_rated' => $zeroRated,
            ];
        }

        return $mapped;
    }

    private function decimal(float|int|string $value): float
    {
        return round((float) $value, 2);
    }
}
