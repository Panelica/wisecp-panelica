<?php

declare(strict_types=1);

/*
 * What WISECP shows on the server form, and what this module supports.
 *
 * The keys are the current ones (WISECP 5, dev.wisecp.com/en/module-configuration).
 * The first version of this file used the WISECP 3 spelling - 'access-hash',
 * 'server-info-checker', 'server-info-port', 'service-relationship' - which the
 * current core does not read, so the form offered no access-hash field and no
 * connection test. That is why a server could be saved with credentials the
 * module never received.
 */

return [
    'name' => 'Panelica',

    // Domain based hosting accounts, not virtual machines.
    'type' => 'hosting',

    // The API key goes in the password field and the API secret in the access
    // hash: servers.password is a varchar, and the secret is long.
    'use-access-hash' => true,
    'require-access-hash' => true,

    'use-test-connection' => true,
    'use-port' => true,
    'secure-port' => 8443,
    'not-secure-port' => 8443,

    // The External API answers on the panel's own port behind /api/external.
    'use-ssl' => true,

    'supported' => [
        'disk-bandwidth-usage',
        'change-password',
        'upgrade',
    ],
];
