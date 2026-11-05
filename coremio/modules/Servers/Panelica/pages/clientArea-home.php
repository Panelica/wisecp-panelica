<?php

declare(strict_types=1);

/*
 * What the customer sees on the service page in WISECP 3.
 *
 * WISECP 5 builds this page itself from dashboard_data(); WISECP 3 renders this
 * template instead, with the values clientArea() passes. get_page() also hands
 * the page $module, which is where the strings come from, so the page is in the
 * operator's language rather than in one language hard-coded here.
 *
 * @var string $domain
 * @var string $username
 * @var string $status
 * @var string $plan
 * @var string $panel
 * @var string $nameservers
 * @var array<string,array{used:float,limit:float}> $usage
 * @var string $error
 * @var object $module
 */

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$lang = is_object($module ?? null) && is_array($module->lang ?? null) ? $module->lang : [];
$t = static fn (string $key, string $fallback): string => trim((string) ($lang[$key] ?? '')) !== '' ? (string) $lang[$key] : $fallback;

$amount = static function (array $gauge, string $unit) use ($e): string {
    $used = (float) ($gauge['used'] ?? 0);
    $limit = (float) ($gauge['limit'] ?? 0);
    $shown = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    return $e($shown($used) . ' ' . $unit . ' / ' . ($limit > 0 ? $shown($limit) . ' ' . $unit : '∞'));
};

// The same keys the newer host's theme looks up for its own rows, so one set
// of strings serves both generations.
$rows = [
    $t('domain', 'Domain') => $domain,
    $t('username', 'Username') => $username,
    $t('plan', 'Plan') => $plan,
    $t('status', 'Status') => $t('status_' . $status, $status),
    $t('nameservers', 'Nameservers') => $nameservers,
];

if (isset($usage['disk'])) {
    $rows[$t('disk', 'Disk')] = $amount($usage['disk'], 'MB');
}
if (isset($usage['bandwidth'])) {
    $rows[$t('bandwidth', 'Traffic')] = $amount($usage['bandwidth'], 'MB');
}
?>
<?php if (trim((string) ($error ?? '')) !== ''): ?>
    <div class="red-info"><div class="padding10"><?= $e($error) ?></div></div>
<?php endif; ?>
<section class="panelica-service">
    <table width="100%" border="0" cellpadding="5">
        <?php foreach ($rows as $label => $value): ?>
            <?php if (trim((string) $value) === '') { continue; } ?>
            <tr>
                <td width="35%"><?= $e($label) ?></td>
                <td><?= $e($value) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php if (trim((string) ($panel ?? '')) !== ''): ?>
        <p><a href="<?= $e($panel) ?>" target="_blank" rel="noopener noreferrer"><?= $e($t('open_panel', 'Open the panel')) ?></a></p>
    <?php endif; ?>
</section>
