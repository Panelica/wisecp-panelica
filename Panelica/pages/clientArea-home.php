<?php defined('CORE_FOLDER') OR exit('You can not get in here!');
$usage = isset($usage) && is_array($usage) ? $usage : array();
$disk = isset($usage['disk']) && is_array($usage['disk']) ? $usage['disk'] : array();
$traffic = isset($usage['bandwidth']) && is_array($usage['bandwidth']) ? $usage['bandwidth'] : array();
$h = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$diskUsed = isset($disk['used_mb']) ? (float) $disk['used_mb'] : null;
$diskTotal = isset($disk['quota_mb']) ? (float) $disk['quota_mb'] : null;
$trafficUsed = isset($traffic['bytes_used']) ? (float) $traffic['bytes_used'] / 1048576 : (isset($traffic['used_mb']) ? (float) $traffic['used_mb'] : null);
$trafficTotal = isset($traffic['bytes_total']) ? (float) $traffic['bytes_total'] / 1048576 : (isset($traffic['total_mb']) ? (float) $traffic['total_mb'] : null);
$resources = array(array('Disk kullanımı', $diskUsed, $diskTotal), array('Trafik kullanımı', $trafficUsed, $trafficTotal));
?>
<style>
.panelica-client{font-family:inherit;color:#17243b}.panelica-client *{box-sizing:border-box}.panelica-client h3{margin:0 0 6px;font-size:18px}.panelica-client .pc-note{margin:0 0 16px;color:#6b7890;font-size:13px}.panelica-client .pc-usage{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}.panelica-client .pc-card{display:flex;align-items:center;gap:15px;padding:16px;border:1px solid #e7ecf3;border-radius:14px;background:#fff}.panelica-client .pc-ring{display:grid;place-items:center;width:82px;height:82px;flex:0 0 82px;border-radius:50%;background:conic-gradient(#315cf5 var(--pct),#e9eef7 0)}.panelica-client .pc-ring:before{content:"";grid-area:1/1;width:64px;height:64px;border-radius:50%;background:white}.panelica-client .pc-ring b{z-index:1;grid-area:1/1;font-size:13px}.panelica-client .pc-info{min-width:0}.panelica-client .pc-info strong,.panelica-client .pc-info span{display:block}.panelica-client .pc-info span{margin-top:5px;color:#6b7890;font-size:13px}.panelica-client .pc-account{display:grid;grid-template-columns:1fr 2fr;border:1px solid #e7ecf3;border-radius:12px;overflow:hidden}.panelica-client .pc-account div{padding:12px;border-bottom:1px solid #e7ecf3;overflow-wrap:anywhere}.panelica-client .pc-account div:nth-child(odd){color:#6b7890;background:#f7f9fc}.panelica-client .pc-account div:nth-last-child(-n+2){border-bottom:0}.panelica-client .pc-password{font-family:ui-monospace,monospace;user-select:all}.panelica-client .pc-empty{padding:14px;border:1px solid #e7ecf3;border-radius:12px;color:#6b7890}.panelica-client .pc-warning{color:#9b1c1c}
@media(max-width:600px){.panelica-client .pc-usage{grid-template-columns:1fr}.panelica-client .pc-account{grid-template-columns:1fr}.panelica-client .pc-account div:nth-child(odd){padding-bottom:5px}.panelica-client .pc-account div:nth-child(even){border-bottom:1px solid #e7ecf3}}
</style>
<section class="panelica-client">
    <h3>Hosting paketine ait kullanım bilgileri</h3>
    <p class="pc-note">Kullanım ve paket bilgileri Panelica sunucusundan alınır.</p>
    <div class="pc-usage">
        <?php foreach ($resources as $item): if ($item[1] === null) { continue; } $pct = $item[2] !== null && $item[2] > 0 ? min(100, max(0, $item[1] / $item[2] * 100)) : 0; ?>
        <div class="pc-card"><div class="pc-ring" style="--pct:<?php echo $h(number_format($pct, 1, '.', '')); ?>%"><b><?php echo $h(number_format($pct, 0)); ?>%</b></div><div class="pc-info"><strong><?php echo $h($item[0]); ?></strong><span><?php echo $h(number_format($item[1], 1)); ?> MB kullanıldı<?php if ($item[2] !== null && $item[2] > 0): ?> / <?php echo $h(number_format($item[2], 1)); ?> MB limit<?php else: ?> / Limitsiz<?php endif; ?></span></div></div>
        <?php endforeach; ?>
    </div>
    <?php if ($diskUsed === null && $trafficUsed === null): ?><p class="pc-empty">Kullanım verileri şu anda Panelica sunucusundan alınamadı.</p><?php endif; ?>
    <h3>FTP / hosting paneli giriş bilgileri</h3>
    <p class="pc-note">Parola değiştirildiğinde değişiklik Panelica sunucusuna uygulanır.</p>
    <div class="pc-account">
        <div>Alan adı</div><div><?php echo $h(isset($domain) ? $domain : ''); ?></div>
        <div>Hosting planı</div><div><?php echo $h(isset($plan) ? $plan : ''); ?></div>
        <div>Kullanıcı adı</div><div><?php echo $h(isset($username) ? $username : ''); ?></div>
        <div>Hosting parolası</div><div class="pc-password"><?php echo $h(isset($password) && $password !== '' ? $password : 'Kayıtlı parola bulunamadı'); ?></div>
        <div>Panelica hesap no</div><div><?php echo $h(isset($account_id) ? $account_id : ''); ?></div>
    </div>
</section>

