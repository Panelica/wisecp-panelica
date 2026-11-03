<?php
defined('CORE_FOLDER') OR exit('You can not get in here!');

$h = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$toArray = static function ($value) {
    if (is_object($value)) { $value = (array) $value; }
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (!is_array($decoded) && class_exists('Utility') && method_exists('Utility', 'jdecode')) {
            $decoded = Utility::jdecode($value, true);
        }
        $value = is_array($decoded) ? $decoded : array();
    }
    return is_array($value) ? $value : array();
};

// WISECP versions pass service/order data under different variable names.
// Collect only display fields; credentials are deliberately never rendered.
$sources = array();
foreach (array('options', 'service', 'order', 'creation_info', 'module_data') as $sourceName) {
    if (isset($$sourceName)) { $sources[] = $toArray($$sourceName); }
}
$values = array();
foreach ($sources as $source) {
    foreach (array('options', 'config', 'creation_info', 'module_data', 'login') as $nestedKey) {
        if (isset($source[$nestedKey])) {
            $nested = $toArray($source[$nestedKey]);
            if ($nested) { $values = array_merge($values, $nested); }
        }
    }
    $values = array_merge($values, $source);
}

$value = static function ($keys) use ($values) {
    foreach ($keys as $key) {
        if (isset($values[$key]) && is_scalar($values[$key]) && trim((string) $values[$key]) !== '') {
            return trim((string) $values[$key]);
        }
    }
    return '';
};
$domain = $value(array('domain', 'domain_name'));
$plan = $value(array('plan', 'plan_name', 'panelica_plan'));
$accountId = $value(array('panelica_account_id', 'account_id'));
$username = $value(array('user', 'username'));
$ip = $value(array('ip', 'server_ip', 'dedicated_ip'));
$live = array();
if (isset($module) && is_object($module) && method_exists($module, 'hostingOrderDisplayData')) {
    try { $live = $module->hostingOrderDisplayData(); } catch (Exception $e) { $live = array(); }
}
if (isset($live['account_id']) && $live['account_id'] !== '') { $accountId = (string) $live['account_id']; }
if (isset($live['username']) && $live['username'] !== '') { $username = (string) $live['username']; }
if ($plan === '' && isset($live['plan'])) { $plan = (string) $live['plan']; }
$liveUsage = isset($live['usage']) && is_array($live['usage']) ? $live['usage'] : array();
$diskData = isset($liveUsage['disk']) && is_array($liveUsage['disk']) ? $liveUsage['disk'] : array();
$trafficData = isset($liveUsage['bandwidth']) && is_array($liveUsage['bandwidth']) ? $liveUsage['bandwidth'] : array();
$diskUsed = isset($diskData['used_mb']) ? (float) $diskData['used_mb'] : null;
$diskTotal = isset($diskData['quota_mb']) ? (float) $diskData['quota_mb'] : null;
$trafficUsed = isset($trafficData['bytes_used']) ? (float) $trafficData['bytes_used'] / 1048576 : (isset($trafficData['used_mb']) ? (float) $trafficData['used_mb'] : null);
$trafficTotal = isset($trafficData['bytes_total']) ? (float) $trafficData['bytes_total'] / 1048576 : (isset($trafficData['total_mb']) ? (float) $trafficData['total_mb'] : null);
$panelHost = '';
$port = 8443;
foreach ($sources as $source) {
    $server = isset($source['server']) ? $toArray($source['server']) : array();
    if (!$server) { continue; }
    if ($panelHost === '') { $panelHost = isset($server['hostname']) && $server['hostname'] !== '' ? $server['hostname'] : (isset($server['ip']) ? $server['ip'] : ''); }
    if (isset($server['port']) && is_numeric($server['port'])) { $port = (int) $server['port']; }
}
if ($ip === '' && $panelHost !== '') { $ip = $panelHost; }
$panelHost = preg_replace('#^https?://#i', '', trim($panelHost));
$panelHost = trim(explode('/', $panelHost, 2)[0]);
?>
<table width="100%" border="0" cellpadding="3" cellspacing="0">
    <tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Otomasyon modülü</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">Panelica</td></tr>
    <?php if ($domain !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Alan adı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($domain); ?></td></tr><?php endif; ?>
    <?php if ($plan !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Panelica planı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($plan); ?></td></tr><?php endif; ?>
    <?php if ($username !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Hosting kullanıcı adı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($username); ?></td></tr><?php endif; ?>
    <?php if ($accountId !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Panelica hesap numarası</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($accountId); ?></td></tr><?php endif; ?>
    <?php if ($username !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Hosting kullanıcı adı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($username); ?></td></tr><?php endif; ?>
    <?php if ($diskUsed !== null || $trafficUsed !== null): ?><tr><td colspan="2" style="padding:10px 8px;border-bottom:1px solid #eee;"><strong>Hosting paketine ait kullanım bilgileri</strong></td></tr><?php endif; ?>
    <?php foreach (array(array('Disk kullanımı', $diskUsed, $diskTotal), array('Trafik kullanımı', $trafficUsed, $trafficTotal)) as $metric): if ($metric[1] === null) { continue; } $percent = $metric[2] !== null && $metric[2] > 0 ? min(100, max(0, $metric[1] / $metric[2] * 100)) : 0; ?>
    <tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong><?php echo $h($metric[0]); ?></strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><span style="display:inline-block;width:64px;height:64px;line-height:64px;text-align:center;border-radius:50%;background:conic-gradient(#315cf5 <?php echo $h(number_format($percent, 1, '.', '')); ?>%,#e9eef7 0);font-weight:bold;"><?php echo $h(number_format($percent, 0)); ?>%</span> &nbsp; <?php echo $h(number_format($metric[1], 1)); ?> MB kullanıldı<?php if ($metric[2] !== null && $metric[2] > 0): ?> / <?php echo $h(number_format($metric[2], 1)); ?> MB limit<?php else: ?> / Limitsiz<?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if ($ip !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Sunucu IP/adresi</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($ip); ?></td></tr><?php endif; ?>
    <?php if ($panelHost !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Panelica paneli</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><a href="<?php echo $h('https://' . $panelHost . ($port > 0 ? ':' . $port : '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo $h($panelHost . ($port > 0 ? ':' . $port : '')); ?></a></td></tr><?php endif; ?>
    <tr><td colspan="2" style="padding:8px;color:#666;">Hesap parolası ve API kimlik bilgileri güvenlik nedeniyle bu sipariş görünümünde gösterilmez. Panelica paneline müşteri panelindeki güvenli giriş bağlantısından erişin.</td></tr>
</table>

