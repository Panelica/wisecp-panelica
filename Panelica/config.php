<?php
return array(
    'type' => 'hosting',
    'access-hash' => true,
    // The panel API certificate does not match the configured IP address.
    // HTTPS stays enabled, but certificate and hostname verification are disabled.
    'panelica-verify-tls' => false,
    'server-info-checker' => true,
    'server-info-port' => true,
    'server-info-not-secure-port' => 8443,
    'server-info-secure-port' => 8443,
    'configurable-option-params' => array(),
);
