<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('queues', fn () => auth()->check());
Broadcast::channel('alerts', fn () => auth()->check());
Broadcast::channel('analytics', fn () => auth()->check());
Broadcast::channel('invoices.{invoiceId}', fn ($user) => auth()->check());
Broadcast::channel('merchants.{merchantId}.activity', fn ($user) => auth()->check());
