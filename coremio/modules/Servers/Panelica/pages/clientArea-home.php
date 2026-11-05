<?php
declare(strict_types=1);

$hosting = is_array($hosting ?? null) ? $hosting : [];
$disk = is_array($hosting['disk'] ?? null) ? $hosting['disk'] : [];
$stats = is_array($hosting['stats'] ?? null) ? $hosting['stats'] : [];
$bandwidth = is_array($hosting['bandwidth'] ?? null) ? $hosting['bandwidth'] : [];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$panelUrl = (string) ($hosting['panel_url'] ?? '');
$webmailUrl = (string) ($hosting['webmail_url'] ?? '');
?>
<section class="panelica-service-details">
    <h3>Hosting hizmeti</h3>
    <table width="100%" border="0" cellpadding="5">
        <tr><td>Alan adı</td><td><?= $escape($hosting['domain'] ?? '—') ?></td></tr>
        <tr><td>Kullanıcı adı</td><td><?= $escape($hosting['username'] ?? '—') ?></td></tr>
        <tr><td>Plan</td><td><?= $escape($hosting['plan'] ?? '—') ?></td></tr>
        <tr><td>Disk kullanımı</td><td><?= $escape($disk['used_mb'] ?? $disk['used'] ?? '—') ?> / <?= $escape($disk['quota_mb'] ?? $disk['quota'] ?? '—') ?> MB</td></tr>
        <tr><td>Trafik kullanımı</td><td><?= $escape($bandwidth['bytes_used'] ?? $bandwidth['used_bytes'] ?? '—') ?> / <?= $escape($bandwidth['bytes_total'] ?? $bandwidth['total_bytes'] ?? '—') ?> bayt</td></tr>
        <tr><td>Web siteleri</td><td><?= $escape($stats['domain_count'] ?? $stats['domains'] ?? '—') ?></td></tr>
        <tr><td>E-posta hesapları</td><td><?= $escape($stats['email_count'] ?? $stats['emails'] ?? '—') ?></td></tr>
        <tr><td>Veritabanları</td><td><?= $escape($stats['database_count'] ?? $stats['databases'] ?? '—') ?></td></tr>
    </table>
    <nav aria-label="Hosting bağlantıları">
        <?php if ($panelUrl !== ''): ?><a href="<?= $escape($panelUrl) ?>" target="_blank" rel="noopener noreferrer">Panelica Paneli</a><?php endif; ?>
        <?php if ($panelUrl !== '' && $webmailUrl !== ''): ?> | <?php endif; ?>
        <?php if ($webmailUrl !== ''): ?><a href="<?= $escape($webmailUrl) ?>" target="_blank" rel="noopener noreferrer">Webmail</a><?php endif; ?>
    </nav>
</section>

