<?php

return [
    'qz' => [
        'signing_enabled' => (bool) env('QZ_SIGNING_ENABLED', false),
        'certificate_path' => env('QZ_CERTIFICATE_PATH', storage_path('app/private/qz/digital-certificate.txt')),
        'private_key_path' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/private/qz/private-key.pem')),
        'private_key_passphrase' => env('QZ_PRIVATE_KEY_PASSPHRASE'),
    ],
];
