<?php
return array(
    'type' => 'hosting',
    'access-hash' => true,
    // TLS certificate verification is enabled by default. WISECP's per-server SSL
    // verification setting overrides this value in define_server_info().
    'panelica-verify-tls' => true,
    'server-info-checker' => true,
    'server-info-port' => true,
    'server-info-not-secure-port' => 8443,
    'server-info-secure-port' => 8443,
    'fields' => array(
        'api_key' => array('type' => 'password', 'name' => 'API_KEY', 'crypt' => true),
    ),
    'configurable-option-params' => array(),
);
