<?php

declare(strict_types=1);

/*
 * What WISECP shows on the server form, and what this module supports.
 *
 * The two generations spell every one of these keys differently, and a host
 * silently ignores a key it does not know. So both spellings are here, saying
 * the same thing twice:
 *
 *   WISECP 3                         WISECP 5
 *   access-hash                      use-access-hash / require-access-hash
 *   server-info-checker              use-test-connection
 *   server-info-port                 use-port
 *   server-info-secure-port          secure-port
 *   server-info-not-secure-port      not-secure-port
 *
 * This file is read, not executed, so carrying both costs nothing and is what
 * lets one module serve both - the same reason the verbs come in two sets.
 *
 * The first version carried only the WISECP 3 spelling, which the newer core
 * does not read: the form then offered no access-hash field and no connection
 * test, so a server could be saved with credentials the module never received.
 */

return [
    'name' => 'Panelica',

    // Domain based hosting accounts, not virtual machines.
    'type' => 'hosting',

    // The API key goes in the password field and the API secret in the access
    // hash: servers.password is a varchar, and the secret is long.
    'access-hash' => true,               // WISECP 3
    'use-access-hash' => true,           // WISECP 5
    'require-access-hash' => true,

    // The Test Connection button on the server form.
    'server-info-checker' => true,       // WISECP 3
    'use-test-connection' => true,       // WISECP 5

    // The External API answers on the panel's own port, behind /api/external.
    'server-info-port' => true,          // WISECP 3
    'server-info-secure-port' => 8443,
    'server-info-not-secure-port' => 8443,
    'use-port' => true,                  // WISECP 5
    'secure-port' => 8443,
    'not-secure-port' => 8443,
    'use-ssl' => true,

    // Only what is actually implemented: a flag here turns on a screen, and a
    // screen whose method is missing is an error in front of the customer.
    'supported' => [
        'disk-bandwidth-usage',
        'change-password',
        'upgrade',
    ],
];
