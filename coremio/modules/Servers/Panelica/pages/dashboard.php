<?php
declare(strict_types=1);

/** @var array<string,mixed> $data */
$data = is_array($data ?? null) ? $data : [];
$disk = is_array($data['disk'] ?? null) ? $data['disk'] : [];
$stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];
$bandwidth = is_array($data['bandwidth'] ?? null) ? $data['bandwidth'] : [];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$panelUrl = (string) ($data['panel_url'] ?? '');
$webmailUrl = (string) ($data['webmail_url'] ?? '');
?>
<section class="panelica-service-details">
    <h3>Hosting hizmeti</h3>
    <table width="100%" border="0" cellpadding="5">
        <tr><td>Alan adı</td><td><?= $escape($data['domain'] ?? '—') ?></td></tr>
        <tr><td>Kullanıcı adı</td><td><?= $escape($data['username'] ?? '—') ?></td></tr>
        <tr><td>Plan</td><td><?= $escape($data['plan'] ?? '—') ?></td></tr>
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

