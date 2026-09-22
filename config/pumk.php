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
];
