<?php
defined('CORE_FOLDER') OR exit('You can not get in here!');

$LANG = isset($module->lang) && is_array($module->lang) ? $module->lang : array();
$order = isset($order) && $order ? $order : array();
$options = isset($order['options']) && is_array($order['options']) ? $order['options'] : array();
$config = isset($options['config']) && is_array($options['config']) ? $options['config'] : array();
$creation_info = isset($options['creation_info']) && is_array($options['creation_info']) ? $options['creation_info'] : array();
$plann = isset($creation_info['plan']) ? (string) $creation_info['plan'] : (isset($config['plan']) ? (string) $config['plan'] : '');
$reseller = isset($creation_info['reseller']) && in_array(strtolower((string) $creation_info['reseller']), array('1', 'true', 'yes', 'on'), true);
$h = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$planField = static function ($plan, $key) {
    if (!isset($plan[$key]) || !is_numeric($plan[$key]) || (float) $plan[$key] <= 0) { return 'unlimited'; }
    return (string) $plan[$key];
};
$ownerRole = isset($module) && is_object($module) && method_exists($module, 'getApiOwnerRole') ? $module->getApiOwnerRole() : '';
$canCreateReseller = in_array(strtoupper((string) $ownerRole), array('ROOT', 'ADMIN'), true);
$plans = array();
try {
    if (isset($module) && is_object($module) && method_exists($module, 'getPlans')) { $plans = $module->getPlans(); }
} catch (Exception $e) { $module->error = $e->getMessage(); }
$username = isset($config['user']) ? (string) $config['user'] : '';
$password = isset($module) && is_object($module) && method_exists($module, 'getSavedPasswordForAdminTemplate')
    ? $module->getSavedPasswordForAdminTemplate()
    : '';
?>

<div class="formcon">
    <div class="yuzde30"><?php echo __('admin/orders/hosting-config-username'); ?></div>
    <div class="yuzde70">
        <input name="config[user]" type="text" value="<?php echo $h($username); ?>" autocomplete="off">
        <span class="kinfo"><?php echo __('admin/orders/hosting-config-username-info'); ?></span>
    </div>
</div>

<div class="formcon">
    <div class="yuzde30"><?php echo __('admin/orders/hosting-config-password'); ?></div>
    <div class="yuzde70">
        <input name="config[password]" type="text" placeholder="*******" value="<?php echo $h($password); ?>" autocomplete="new-password">
        <span class="kinfo"><?php echo __('admin/orders/hosting-config-password-info'); ?> Panelica sunucusundaki parola ile eşit tutulur.</span>
    </div>
</div>

<?php if ($canCreateReseller): ?>
    <div class="formcon">
        <div class="yuzde30">Panelica reseller hesabı</div>
        <div class="yuzde70">
            <input type="hidden" name="creation_info[reseller]" value="0">
            <input<?php echo $reseller ? ' checked' : ''; ?> type="checkbox" name="creation_info[reseller]" value="1" id="config_reseller" class="sitemio-checkbox">
            <label for="config_reseller" class="sitemio-checkbox-label"></label>
            <span class="kinfo">Açıkken Panelica hesabı RESELLER rolüyle oluşturulur. Bu API anahtarı ROOT veya ADMIN rolüne ait olmalıdır.</span>
        </div>
    </div>
<?php elseif ($reseller): ?>
    <input type="hidden" name="creation_info[reseller]" value="1">
    <div class="formcon"><div class="yuzde30">Panelica rolü</div><div class="yuzde70"><span class="kinfo">Bu hesap RESELLER rolünde. Bağlı API kullanıcısı rol değiştirme yetkisine sahip değil.</span></div></div>
<?php else: ?>
    <input type="hidden" name="creation_info[reseller]" value="0">
<?php endif; ?>

<?php if ($reseller): ?>
    <div class="formcon">
        <div class="yuzde30">Reseller kaynak ve ACL ayarları</div>
        <div class="yuzde70">
            <span class="kinfo">Panelica'nın belgelenmiş dış API'si reseller hesabı ve hosting planı atamasını destekler. CyberPanel'e özgü hesap açma kotası, özel disk/trafik kotası ve ACL listesi bu API'de belgelenmediğinden burada gösterilmez veya gönderilmez. Resellerin kaynakları Panelica'da kendisine tanımlanan plan ve rol sınırlarıyla yönetilir.</span>
        </div>
    </div>
<?php endif; ?>

<div class="formcon" id="plans_wrap">
    <div class="yuzde30">Panelica hosting paketi</div>
    <div class="yuzde70">
        <?php if ($plans): ?>
            <script type="text/javascript">
                $(document).ready(function () {
                    $("#creation_info_plan").change(panelicaPlanSelected);
                    panelicaPlanSelected();
                });
                function panelicaPlanSelected() {
                    var selected = $("option:selected", $("#creation_info_plan"));
                    var bindings = [
                        ["disk_limit", "disk_limit_unlimited", "quota"],
                        ["bandwidth_limit", "bandwidth_limit_unlimited", "bwlimit"],
                        ["email_limit", "email_limit_unlimited", "maxemail"],
                        ["database_limit", "database_limit_unlimited", "maxdb"],
                        ["ftp_limit", "ftp_limit_unlimited", "maxftp"]
                    ];
                    $.each(bindings, function (_, binding) {
                        var value = selected.data(binding[2]);
                        var input = $("#" + binding[0]);
                        var unlimited = $("#" + binding[1]);
                        input.attr("readonly", true);
                        unlimited.attr("disabled", true);
                        if (!value || value === "unlimited") {
                            input.val("");
                            unlimited.prop("checked", true);
                        } else {
                            input.val(value);
                            unlimited.prop("checked", false);
                        }
                    });
                }
            </script>
            <select name="creation_info[plan]" id="creation_info_plan">
                <option value=""><?php echo __('needs/none'); ?></option>
                <?php foreach ($plans as $plan):
                    if (!is_array($plan)) { continue; }
                    $planName = isset($plan['name']) ? (string) $plan['name'] : (isset($plan['plan_name']) ? (string) $plan['plan_name'] : '');
                    $planId = isset($plan['id']) ? (string) $plan['id'] : (isset($plan['plan_id']) ? (string) $plan['plan_id'] : '');
                    if ($planName === '' || strtolower($planName) === 'default') { continue; }
                ?>
                    <option value="<?php echo $h($planName); ?>"<?php echo strcasecmp($planName, $plann) === 0 || $planId === $plann ? ' selected' : ''; ?>
                        data-quota="<?php echo $h($planField($plan, 'disk_space_mb')); ?>"
                        data-bwlimit="<?php echo $h($planField($plan, 'bandwidth_mb')); ?>"
                        data-maxemail="<?php echo $h($planField($plan, 'max_email_accounts')); ?>"
                        data-maxdb="<?php echo $h($planField($plan, 'max_databases')); ?>"
                        data-maxftp="<?php echo $h($planField($plan, 'max_ftp_accounts')); ?>"><?php echo $h($planName); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="kinfo">Disk, trafik, e-posta, veritabanı ve FTP limitleri Panelica planından alınır. Formdaki seçimin kaydedilmesi hesabın planını Panelica API üzerinden günceller.</span>
        <?php elseif (!empty($module->error)): ?>
            <div class="red-info"><div class="padding10">Panelica plan listesi yüklenemedi: <strong><?php echo $h($module->error); ?></strong></div></div>
        <?php else: ?>
            <div class="red-info"><div class="padding10">Panelica sunucusunda kullanılabilir hosting planı bulunamadı.</div></div>
        <?php endif; ?>
    </div>
</div>

