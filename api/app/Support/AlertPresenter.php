<?php

namespace App\Support;

use App\Models\Alert;

class AlertPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->displayCategory(),
            'status' => $alert->status,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'details' => $alert->displayDetails(),
            'created_at' => $alert->created_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'merchant' => $alert->merchant ? [
                'id' => $alert->merchant->id,
                'name' => $alert->merchant->name,
            ] : null,
            'vendor' => $alert->vendor ? [
                'id' => $alert->vendor->id,
                'name' => $alert->vendor->name,
            ] : null,
            'invoice' => $alert->invoice ? [
                'id' => $alert->invoice->id,
                'bridge_transaction_id' => $alert->invoice->bridge_transaction_id,
            ] : null,
            'certificate' => $alert->certificate ? [
                'id' => $alert->certificate->id,
            ] : null,
        ];
    }
}
