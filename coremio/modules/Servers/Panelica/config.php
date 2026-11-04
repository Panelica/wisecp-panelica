<?php
declare(strict_types=1);

return [
    'name' => 'Panelica',
    'type' => 'hosting',
    // WiseCP 3.1.x sunucu ekleme formunun tanıdığı eski anahtarlar.
    'access-hash' => true,
    'server-info-checker' => true,
    'server-info-port' => true,
    'server-info-secure-port' => 8443,
    'server-info-not-secure-port' => 8443,
    'service-relationship' => 'domain',
    'supported' => [
        'disk-bandwidth-usage',
    ],
];

