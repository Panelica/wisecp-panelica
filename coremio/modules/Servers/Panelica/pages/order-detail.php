<?php

declare(strict_types=1);

/*
 * The service's own screen in the admin area.
 *
 * Like the package screen, WISECP 3 includes this page rather than calling the
 * module for it, so without the file the operator sees none of the module's
 * fields on an order.
 *
 * Three things, in the order the stock modules put them: the module's buttons,
 * the account's username as WISECP has it, and the fields the module offers for
 * this one service - the plan it is on - saved under "creation_info".
 *
 * There is no password box here on purpose. The stock modules print the panel
 * password into this page in clear; the operator can already set a new one
 * through WISECP's own action, which goes to the panel, so showing it buys
 * nothing and puts a live credential on a screen that does not need it.
 *
 * @var object $module
 * @var array<string,mixed> $order
 */

$order = isset($order) && is_array($order) ? $order : [];
$options = is_array($order['options'] ?? null) ? $order['options'] : [];
$creationInfo = is_array($options['creation_info'] ?? null) ? $options['creation_info'] : [];
$config = is_array($options['config'] ?? null) ? $options['config'] : [];
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$lang = is_array($module->lang ?? null) ? $module->lang : [];
$t = static fn (string $key, string $fallback): string => trim((string) ($lang[$key] ?? '')) !== '' ? (string) $lang[$key] : $fallback;

$buttons = method_exists($module, 'adminArea_buttons_output') ? (string) $module->adminArea_buttons_output() : '';
?>
<?php if ($buttons !== ''): ?>
    <div class="formcon"><?= $buttons ?></div>
    <div class="clear"></div>
<?php endif; ?>

<div class="formcon">
    <div class="yuzde30"><?= $e($t('username', 'Username')) ?></div>
    <div class="yuzde70">
        <input name="config[user]" type="text" value="<?= $e($config['user'] ?? '') ?>">
        <span class="kinfo"><?= $e($t('service_username_info', 'The account this service has on the panel. Changing it here only changes what WISECP records.')) ?></span>
    </div>
</div>
<div class="clear"></div>

<?php
if (method_exists($module, 'adminArea_service_fields') && $fields = $module->adminArea_service_fields()) {
    $module->config_options_output($fields, 'creation_info');
}
