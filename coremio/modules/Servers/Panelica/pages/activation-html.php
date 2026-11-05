<?php

declare(strict_types=1);

/*
 * The HTML half of the welcome mail. Same values as activation-text.php, in the
 * plain table markup WISECP's own mail templates use.
 *
 * @var object $module
 * @var array<string,mixed> $options
 * @var array<string,mixed> $server
 */

$options = isset($options) && is_array($options) ? $options : [];
$server = isset($server) && is_array($server) ? $server : [];
$ftp = is_array($options['ftp_info'] ?? null) ? $options['ftp_info'] : [];
$lang = is_array($module->lang ?? null) ? $module->lang : [];
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn (string $key, string $fallback): string => trim((string) ($lang[$key] ?? '')) !== '' ? (string) $lang[$key] : $fallback;

$host = trim((string) ($server['hostname'] ?? $server['name'] ?? $server['ip'] ?? ''));
$port = (int) ($server['port'] ?? 8443);
$panel = $host === '' ? '' : 'https://' . $host . ':' . ($port > 0 ? $port : 8443);
$domain = trim((string) ($options['domain'] ?? ''));

$ns = array_values(array_filter([
    trim((string) ($server['ns1'] ?? '')),
    trim((string) ($server['ns2'] ?? '')),
    trim((string) ($server['ns3'] ?? '')),
    trim((string) ($server['ns4'] ?? '')),
]));

$rows = [
    $t('panel', 'Control panel') => $panel,
    $t('username', 'Username') => (string) ($ftp['username'] ?? $options['username'] ?? ''),
    $t('password', 'Password') => (string) ($ftp['password'] ?? ''),
    $t('domain', 'Domain') => $domain,
    $t('nameservers', 'Nameservers') => implode(', ', $ns),
];

if (($ftp['host'] ?? '') !== '') {
    $rows['FTP'] = (string) $ftp['host'] . ':' . (string) ($ftp['port'] ?? 21);
}
?>
<p><?= $e($t('mail_intro', 'Your hosting account is ready. These are its details.')) ?></p>
<table width="100%" border="0" cellpadding="5">
    <?php foreach ($rows as $label => $value): ?>
        <?php if (trim((string) $value) === '') { continue; } ?>
        <tr>
            <td width="35%"><?= $e($label) ?></td>
            <td><?= $e($value) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php if ($panel !== ''): ?>
    <p><a href="<?= $e($panel) ?>" target="_blank" rel="noopener noreferrer"><?= $e($t('open_panel', 'Open the panel')) ?></a></p>
<?php endif; ?>
