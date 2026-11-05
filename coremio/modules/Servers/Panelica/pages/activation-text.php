<?php

declare(strict_types=1);

/*
 * The plain-text half of the welcome mail WISECP sends when the account is
 * opened. The core includes it with the service's options; every stock module
 * carries one, and a module without it sends the customer an empty mail.
 *
 * @var object $module
 * @var array<string,mixed> $options
 * @var array<string,mixed> $server
 */

$options = isset($options) && is_array($options) ? $options : [];
$server = isset($server) && is_array($server) ? $server : [];
$ftp = is_array($options['ftp_info'] ?? null) ? $options['ftp_info'] : [];
$lang = is_array($module->lang ?? null) ? $module->lang : [];
$t = static fn (string $key, string $fallback): string => trim((string) ($lang[$key] ?? '')) !== '' ? (string) $lang[$key] : $fallback;

$host = trim((string) ($server['hostname'] ?? $server['name'] ?? $server['ip'] ?? ''));
$port = (int) ($server['port'] ?? 8443);
$panel = $host === '' ? '' : 'https://' . $host . ':' . ($port > 0 ? $port : 8443);
$domain = trim((string) ($options['domain'] ?? ''));

$lines = [
    $t('mail_intro', 'Your hosting account is ready. These are its details.'),
    '',
    $t('panel', 'Control panel') . ': ' . $panel,
    $t('username', 'Username') . ': ' . (string) ($ftp['username'] ?? $options['username'] ?? ''),
    $t('password', 'Password') . ': ' . (string) ($ftp['password'] ?? ''),
];

if ($domain !== '') {
    $lines[] = $t('domain', 'Domain') . ': ' . $domain;
    $lines[] = $t('open_webmail', 'Webmail') . ': https://webmail.' . $domain;
}

$ns = array_values(array_filter([
    trim((string) ($server['ns1'] ?? '')),
    trim((string) ($server['ns2'] ?? '')),
    trim((string) ($server['ns3'] ?? '')),
    trim((string) ($server['ns4'] ?? '')),
]));

if ($ns !== []) {
    $lines[] = $t('nameservers', 'Nameservers') . ': ' . implode(', ', $ns);
}

if (($ftp['host'] ?? '') !== '') {
    $lines[] = '';
    $lines[] = 'FTP: ' . (string) $ftp['host'] . ':' . (string) ($ftp['port'] ?? 21);
}

echo implode("\n", $lines) . "\n";
