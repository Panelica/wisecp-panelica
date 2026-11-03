<?php
return array(
    'type' => 'hosting',
    'access-hash' => false,
    // The target Panelica endpoint uses a self-signed certificate, matching the supplied SDK.
    // Set true after installing a publicly trusted certificate on the Panelica server.
    'panelica-verify-tls' => true,
    'server-info-checker' => true,
    'server-info-port' => true,
    'server-info-not-secure-port' => 8443,
    'server-info-secure-port' => 8443,
    'fields' => array(
        'api_key' => array('type' => 'password', 'name' => 'API_KEY', 'crypt' => true),
        'api_secret' => array('type' => 'password', 'name' => 'API_SECRET', 'crypt' => true),
    ),
    'configurable-option-params' => array(),
);
