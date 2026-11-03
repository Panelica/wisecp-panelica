<?php
defined('CORE_FOLDER') OR exit('You can not get in here!');

$options = isset($options) && is_array($options) ? $options : array();
$server = isset($server) && is_array($server) ? $server : array();
$translations = isset($translations) && is_array($translations) ? $translations : array();
$config = isset($options['config']) && is_array($options['config']) ? $options['config'] : array();
$creation = isset($options['creation_info']) && is_array($options['creation_info']) ? $options['creation_info'] : array();
$moduleData = isset($options['module_data']) && is_array($options['module_data']) ? $options['module_data'] : array();
$login = isset($options['login']) && is_array($options['login']) ? $options['login'] : array();
$values = array_merge($options, $moduleData, $creation, $login, $config);

$t = static function ($key, $fallback) use ($translations) {
    return isset($translations[$key]) && is_scalar($translations[$key]) && $translations[$key] !== ''
        ? (string) $translations[$key]
        : $fallback;
};
$h = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$value = static function ($source, $keys) {
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_scalar($source[$key]) && trim((string) $source[$key]) !== '') {
            return trim((string) $source[$key]);
        }
    }
    return '';
};

$domain = $value($values, array('domain', 'domain_name'));
$plan = $value($values, array('plan', 'plan_name', 'plan_id', 'panelica_plan'));
$accountId = $value($values, array('panelica_account_id'));
$username = $value($values, array('user', 'username'));
if ($username === '') { $username = $value($login, array('username')); }

$hostCandidate = $value($server, array('hostname', 'name', 'ip'));
$hostCandidate = preg_replace('#^https?://#i', '', $hostCandidate);
$hostCandidate = trim(explode('/', $hostCandidate, 2)[0]);
$panelHost = parse_url('https://' . $hostCandidate, PHP_URL_HOST);
$hostIsValid = is_string($panelHost) && $panelHost !== '' && (
    filter_var($panelHost, FILTER_VALIDATE_IP) !== false ||
    filter_var($panelHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
);
$port = isset($server['port']) && is_numeric($server['port']) ? (int) $server['port'] : 8443;
$panelUrl = $hostIsValid ? 'https://' . $panelHost . ($port > 0 ? ':' . $port : '') : '';

$statusLabel = $accountId !== ''
    ? $t('activation_status_ready', 'Hesap hazır')
    : $t('activation_status_pending', 'Kurulum bekleniyor');
$statusClass = $accountId !== '' ? 'is-ready' : 'is-pending';

$limitLabels = array(
    'disk_limit' => array('activation_disk', 'Disk alanı', 'size'),
    'bandwidth_limit' => array('activation_bandwidth', 'Trafik', 'size'),
    'email_limit' => array('activation_email', 'E-posta hesabı', 'count'),
    'database_limit' => array('activation_database', 'Veritabanı', 'count'),
    'addons_limit' => array('activation_addons', 'Ek hizmet', 'count'),
    'subdomain_limit' => array('activation_subdomain', 'Alt alan adı', 'count'),
    'ftp_limit' => array('activation_ftp', 'FTP hesabı', 'count'),
    'park_limit' => array('activation_parked_domains', 'Park edilmiş alan adı', 'count'),
    'max_email_per_hour' => array('activation_hourly_email', 'Saatlik e-posta gönderimi', 'count'),
    'cpu_limit' => array('activation_cpu', 'CPU limiti', 'text'),
);
$limits = array();
foreach ($limitLabels as $key => $definition) {
    if (!isset($values[$key]) || !is_scalar($values[$key]) || trim((string) $values[$key]) === '') { continue; }
    $raw = trim((string) $values[$key]);
    if (in_array(strtolower($raw), array('unlimited', 'limitless', 'none'), true)) {
        $display = $t('activation_unlimited', 'Limitsiz');
    } elseif ($definition[2] === 'size' && is_numeric($raw)) {
        $display = $raw . ' MB';
        if (class_exists('FileManager') && method_exists('FileManager', 'converByte') && method_exists('FileManager', 'formatByte')) {
            $display = FileManager::formatByte(FileManager::converByte($raw . 'MB'));
        }
    } elseif ($definition[2] === 'count') {
        $display = $raw . ' ' . $t('activation_unit_count', 'adet');
    } else {
        $display = $raw;
    }
    $limits[] = array(
        'label' => $t($definition[0], $definition[1]),
        'value' => $display,
    );
}

$dns = isset($values['dns']) && is_array($values['dns']) ? $values['dns'] : array();
if (!$dns) {
    foreach (array('ns1', 'ns2', 'ns3', 'ns4') as $nsKey) {
        if (isset($server[$nsKey]) && is_scalar($server[$nsKey])) { $dns[$nsKey] = $server[$nsKey]; }
    }
}
$nameServers = array();
foreach (array('ns1', 'ns2', 'ns3', 'ns4') as $nsKey) {
    if (isset($dns[$nsKey]) && is_scalar($dns[$nsKey]) && trim((string) $dns[$nsKey]) !== '') {
        $nameServers[] = trim((string) $dns[$nsKey]);
    }
}
?>
<style>
    .panelica-service, .panelica-service * { box-sizing: border-box; }
    .panelica-service {
        --pc-ink: #17243b;
        --pc-muted: #6b7890;
        --pc-line: #e7ecf3;
        --pc-blue: #315cf5;
        width: 100%;
        color: var(--pc-ink);
        font-family: inherit;
    }
    .panelica-service__hero {
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 28px 30px;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(120deg, #172c57 0%, #315cf5 100%);
        box-shadow: 0 12px 32px rgba(34, 69, 151, .16);
    }
    .panelica-service__hero:after {
        content: "";
        position: absolute;
        width: 230px;
        height: 230px;
        right: -70px;
        top: -120px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 50%;
        box-shadow: 0 0 0 28px rgba(255,255,255,.04), 0 0 0 58px rgba(255,255,255,.035);
        pointer-events: none;
    }
    .panelica-service__eyebrow { margin: 0 0 7px; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 700; letter-spacing: .11em; text-transform: uppercase; }
    .panelica-service__title { margin: 0; color: #fff; font-size: clamp(22px, 3vw, 30px); font-weight: 750; line-height: 1.2; overflow-wrap: anywhere; }
    .panelica-service__subtitle { margin: 8px 0 0; color: rgba(255,255,255,.82); font-size: 14px; }
    .panelica-service__status { z-index: 1; display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto; padding: 9px 13px; border: 1px solid rgba(255,255,255,.24); border-radius: 999px; background: rgba(255,255,255,.12); font-size: 13px; font-weight: 700; }
    .panelica-service__status:before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #a7f3c3; box-shadow: 0 0 0 4px rgba(167,243,195,.16); }
    .panelica-service__status.is-pending:before { background: #ffd477; box-shadow: 0 0 0 4px rgba(255,212,119,.18); }
    .panelica-service__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
    .panelica-service__card { min-width: 0; padding: 18px 20px; border: 1px solid var(--pc-line); border-radius: 14px; background: #fff; box-shadow: 0 4px 14px rgba(28,43,73,.035); }
    .panelica-service__label { display: block; margin-bottom: 8px; color: var(--pc-muted); font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .panelica-service__value { display: block; color: var(--pc-ink); font-size: 15px; font-weight: 650; overflow-wrap: anywhere; }
    .panelica-service__section { margin-top: 22px; padding: 22px; border: 1px solid var(--pc-line); border-radius: 16px; background: #fff; }
    .panelica-service__section-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 17px; }
    .panelica-service__section-title { margin: 0; color: var(--pc-ink); font-size: 17px; font-weight: 750; }
    .panelica-service__section-note { margin: 5px 0 0; color: var(--pc-muted); font-size: 13px; }
    .panelica-service__limits { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .panelica-service__limit { min-width: 0; padding: 14px; border-radius: 12px; background: #f6f8fc; }
    .panelica-service__limit-label { display: block; margin-bottom: 7px; color: var(--pc-muted); font-size: 12px; }
    .panelica-service__limit-value { display: block; color: var(--pc-ink); font-size: 14px; font-weight: 700; overflow-wrap: anywhere; }
    .panelica-service__dns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .panelica-service__dns-item { padding: 12px 14px; border: 1px solid var(--pc-line); border-radius: 10px; color: var(--pc-ink); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; overflow-wrap: anywhere; }
    .panelica-service__actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 18px; padding: 16px 18px; border-radius: 13px; background: #f3f6ff; }
    .panelica-service__hint { margin: 0; color: var(--pc-muted); font-size: 13px; line-height: 1.55; }
    .panelica-service__button { display: inline-flex; justify-content: center; align-items: center; gap: 9px; flex: 0 0 auto; padding: 11px 16px; border-radius: 10px; color: #fff !important; background: var(--pc-blue); font-size: 13px; font-weight: 700; text-decoration: none !important; transition: transform .15s ease, background .15s ease; }
    .panelica-service__button:hover { color: #fff !important; background: #2449d2; transform: translateY(-1px); }
    .panelica-service__empty { margin: 0; color: var(--pc-muted); font-size: 13px; }
    @media (max-width: 900px) {
        .panelica-service__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .panelica-service__limits { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 600px) {
        .panelica-service__hero { align-items: flex-start; flex-direction: column; padding: 22px; }
        .panelica-service__grid, .panelica-service__limits, .panelica-service__dns { grid-template-columns: 1fr 1fr; }
        .panelica-service__section { padding: 17px; }
        .panelica-service__actions { align-items: stretch; flex-direction: column; }
        .panelica-service__button { width: 100%; }
    }
    @media (max-width: 380px) {
        .panelica-service__grid, .panelica-service__limits, .panelica-service__dns { grid-template-columns: 1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
        .panelica-service__button { transition: none; }
    }
</style>

<section class="panelica-service" aria-label="<?php echo $h($t('activation_title', 'Panelica hosting hizmetiniz')); ?>">
    <header class="panelica-service__hero">
        <div>
            <p class="panelica-service__eyebrow">Panelica Hosting</p>
            <h2 class="panelica-service__title"><?php echo $h($domain !== '' ? $domain : $t('activation_title', 'Hosting hizmetiniz')); ?></h2>
            <p class="panelica-service__subtitle"><?php echo $h($t('activation_summary', 'Hosting hizmetinizin bilgileri ve paket özellikleri')); ?></p>
        </div>
        <span class="panelica-service__status <?php echo $h($statusClass); ?>"><?php echo $h($statusLabel); ?></span>
    </header>

    <div class="panelica-service__grid">
        <div class="panelica-service__card">
            <span class="panelica-service__label"><?php echo $h($t('activation_plan', 'Hosting planı')); ?></span>
            <span class="panelica-service__value"><?php echo $h($plan !== '' ? $plan : '—'); ?></span>
        </div>
        <div class="panelica-service__card">
            <span class="panelica-service__label"><?php echo $h($t('activation_username', 'Kullanıcı adı')); ?></span>
            <span class="panelica-service__value"><?php echo $h($username !== '' ? $username : '—'); ?></span>
        </div>
        <div class="panelica-service__card">
            <span class="panelica-service__label"><?php echo $h($t('activation_account_id', 'Panelica hesap numarası')); ?></span>
            <span class="panelica-service__value"><?php echo $h($accountId !== '' ? $accountId : '—'); ?></span>
        </div>
    </div>

    <?php if ($limits): ?>
    <section class="panelica-service__section">
        <div class="panelica-service__section-head">
            <div>
                <h3 class="panelica-service__section-title"><?php echo $h($t('activation_resources', 'Hosting özellikleri')); ?></h3>
                <p class="panelica-service__section-note"><?php echo $h($t('activation_resources_note', 'Seçili Panelica planına ait limitler')); ?></p>
            </div>
        </div>
        <div class="panelica-service__limits">
            <?php foreach ($limits as $limit): ?>
            <div class="panelica-service__limit">
                <span class="panelica-service__limit-label"><?php echo $h($limit['label']); ?></span>
                <span class="panelica-service__limit-value"><?php echo $h($limit['value']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($nameServers): ?>
    <section class="panelica-service__section">
        <div class="panelica-service__section-head">
            <div>
                <h3 class="panelica-service__section-title"><?php echo $h($t('activation_nameservers', 'Name Server bilgileri')); ?></h3>
                <p class="panelica-service__section-note"><?php echo $h($t('activation_nameservers_note', 'Alan adınız için tanımlı ad sunucuları')); ?></p>
            </div>
        </div>
        <div class="panelica-service__dns">
            <?php foreach ($nameServers as $nameServer): ?>
            <div class="panelica-service__dns-item"><?php echo $h($nameServer); ?></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="panelica-service__actions">
        <p class="panelica-service__hint"><?php echo $h($t('activation_login_note', 'Güvenli giriş bağlantısını müşteri panelinizdeki hizmet işlemlerinden kullanın. Hesap parolası burada gösterilmez.')); ?></p>
        <?php if ($panelUrl !== ''): ?>
        <a class="panelica-service__button" href="<?php echo $h($panelUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo $h($t('activation_open_panel', 'Panelica panelini aç')); ?> <span aria-hidden="true">↗</span>
        </a>
        <?php endif; ?>
    </div>
</section>
