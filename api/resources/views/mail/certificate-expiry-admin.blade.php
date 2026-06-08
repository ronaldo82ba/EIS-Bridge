<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate Expiry Alert</title>
</head>
<body>
    <h1>Certificate expiry alert</h1>

    @php
        $certificate = $alert->certificate;
        $merchant = $certificate?->merchant;
    @endphp

    <p><strong>Level:</strong> {{ $alert->level }}</p>
    <p><strong>Merchant:</strong> {{ $merchant?->name ?? 'Unknown' }}</p>
    <p><strong>Merchant code:</strong> {{ $merchant?->merchant_code ?? '—' }}</p>
    <p><strong>Certificate file:</strong> {{ $certificate?->filename ?? '—' }}</p>
    <p><strong>Expires at:</strong> {{ $certificate?->expires_at?->toDateString() ?? '—' }}</p>
    <p><strong>Alert created:</strong> {{ $alert->created_at?->toDateTimeString() ?? '—' }}</p>
</body>
</html>
