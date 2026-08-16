<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CodeBooks → EIS Bridge ingest (machine auth)
    |--------------------------------------------------------------------------
    |
    | Server-to-server token for CodeBooks. Prefer over admin Sanctum for
    | automated SI push. Compare is timing-safe (hash_equals).
    |
    | CODEBOOKS_INGEST_VENDOR_ID pins the tenant when set (recommended).
    | Request vendor_id must match when both are present.
    |
    */
    'ingest_token' => env('CODEBOOKS_INGEST_TOKEN', ''),
    'ingest_vendor_id' => env('CODEBOOKS_INGEST_VENDOR_ID') !== null && env('CODEBOOKS_INGEST_VENDOR_ID') !== ''
        ? (int) env('CODEBOOKS_INGEST_VENDOR_ID')
        : null,
];
