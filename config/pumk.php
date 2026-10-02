<?php

return [
    'contract_document_max_kb' => (int) env('PUMK_CONTRACT_DOCUMENT_MAX_KB', 153600),
    'payment_proof_max_kb' => (int) env('PUMK_PAYMENT_PROOF_MAX_KB', 10240),
    'admin' => [
        'username' => env('PUMK_ADMIN_USERNAME', 'labubu123'),
        'email' => env('PUMK_ADMIN_EMAIL', 'pumk.admin@tjslinka.local'),
        'password' => env('PUMK_ADMIN_PASSWORD'),
        'legacy_username' => env('PUMK_LEGACY_ADMIN_USERNAME', 'PUMKADMIN'),
        'legacy_email' => env('PUMK_LEGACY_ADMIN_EMAIL', 'pumk.admin@tjslinka.local'),
    ],
    // Batas dipilih pemilik aplikasi pada 29 September 2026; nominal saat penutupan tetap diaudit.
    'settlement_tolerance' => env('PUMK_SETTLEMENT_TOLERANCE', '100000.00'),
    // Pengecualian rekap harus merujuk source_key yang sudah diverifikasi,
    // bukan pencocokan nama mitra yang dapat mengenai record lain.
    'recap_excluded_source_keys' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PUMK_RECAP_EXCLUDED_SOURCE_KEYS', '')),
    ))),
];
