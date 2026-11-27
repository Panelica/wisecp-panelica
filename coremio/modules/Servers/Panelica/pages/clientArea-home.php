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
 * MARKUP (changed 23 Nov 2026): the host drops this into its own card -
 * `<div class="card p-2" id="block_module_details_con">` in the stock themes -
 * and those themes are Bootstrap 5: every other panel on the page is a
 * `list-group` of `list-group-item d-flex justify-content-between` rows, label
 * on the left and value on the right. This page used to emit
 * `<table width="100%" border="0" cellpadding="5">`, which inherits none of
 * that and is why an operator running 3.1.9.8.2 reported the page as cluttered.
 * It now emits the theme's own shape. Where the stylesheet is missing the rows
 * still read as label and value, so an older theme loses the alignment and
 * nothing else.
 *
 * @var string $domain
 * @var string $username
 * @var string $status
 * @var string $plan
 * @var string $panel
 * @var string $nameservers
 * @var string $ftp_user
 * @var string $ftp_host
 * @var int $ftp_port
 * @var array<string,array{used:float,limit:float}> $usage
 * @var string $error
 * @var object $module
 */

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$lang = is_object($module ?? null) && is_array($module->lang ?? null) ? $module->lang : [];
$t = static fn (string $key, string $fallback): string => trim((string) ($lang[$key] ?? '')) !== '' ? (string) $lang[$key] : $fallback;

$shown = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

$amount = static function (array $gauge, string $unit) use ($e, $shown): string {
    $used = (float) ($gauge['used'] ?? 0);
    $limit = (float) ($gauge['limit'] ?? 0);

    return $e($shown($used) . ' ' . $unit . ' / ' . ($limit > 0 ? $shown($limit) . ' ' . $unit : '∞'));
};

// How full the gauge is, or null where there is no ceiling to be full of.
$ratio = static function (array $gauge): ?int {
    $limit = (float) ($gauge['limit'] ?? 0);

    if ($limit <= 0) {
        return null;
    }

    return (int) max(0, min(100, round(((float) ($gauge['used'] ?? 0) / $limit) * 100)));
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

// The FTP login, when the account has one. The host draws its own FTP card from
// what create() returned, and an imported service never had a create() - so
// without these rows an imported service showed the customer no FTP details at
// all (BUG-01, 23 Nov 2026). No password row: the panel keeps a hash, so there
// is no password to show, and printing the panel password here is the fault
// this module was reported for (BUG-02). Empty rows drop out below.
if (trim((string) ($ftp_user ?? '')) !== '') {
    $rows[$t('ftp_username', 'FTP username')] = (string) $ftp_user;
    $rows[$t('ftp_host', 'FTP host')] = (string) ($ftp_host ?? '');
    $rows[$t('ftp_port', 'FTP port')] = (string) ($ftp_port ?? 21);
}

$gauges = [];
foreach (['disk' => 'Disk', 'bandwidth' => 'Traffic'] as $key => $fallback) {
    if (isset($usage[$key])) {
        $gauges[$t($key, $fallback)] = ['text' => $amount($usage[$key], 'MB'), 'ratio' => $ratio($usage[$key])];
    }
}
?>
<?php if (trim((string) ($error ?? '')) !== ''): ?>
    <div class="alert alert-danger red-info" role="alert"><div class="padding10"><?= $e($error) ?></div></div>
<?php endif; ?>
<section class="panelica-service">
    <div class="card-header py-3">
        <h4 class="mb-0"><strong><?= $e($t('service_details', 'Hosting account')) ?></strong></h4>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($rows as $label => $value): ?>
            <?php if (trim((string) $value) === '') { continue; } ?>
            <li class="list-group-item d-flex justify-content-between">
                <strong><?= $e($label) ?></strong>
                <span><?= $e($value) ?></span>
            </li>
        <?php endforeach; ?>
        <?php foreach ($gauges as $label => $gauge): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <strong><?= $e($label) ?></strong>
                    <span><?= $gauge['text'] ?></span>
                </div>
                <?php if ($gauge['ratio'] !== null): ?>
                    <div class="progress mt-2" style="height:6px">
                        <div class="progress-bar" role="progressbar" style="width:<?= $gauge['ratio'] ?>%"
                             aria-valuenow="<?= $gauge['ratio'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
  <!--  <?php if (trim((string) ($panel ?? '')) !== ''): ?>
        <div class="card-footer text-center">
            <a class="btn btn-primary" href="<?= $e($panel) ?>" target="_blank" rel="noopener noreferrer"><?= $e($t('open_panel', 'Open the panel')) ?></a>
        </div> -->
    <?php endif; ?>
</section>
