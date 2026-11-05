<?php
declare(strict_types=1);

$order = is_array($order ?? null) ? $order : [];
$options = is_array($order['options'] ?? null) ? $order['options'] : (is_array($options ?? null) ? $options : []);
$creationInfo = is_array($options['creation_info'] ?? null) ? $options['creation_info'] : [];
$selectedPlan = trim((string) ($creationInfo['plan'] ?? $options['config']['plan'] ?? ''));
$plans = isset($module) && is_object($module) && method_exists($module, 'getPlans') ? $module->getPlans() : false;
$moduleError = isset($module) && is_object($module) ? (string) ($module->error ?? '') : '';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="formcon">
    <div class="yuzde30">Panelica Hosting Planı</div>
    <div class="yuzde70">
        <?php if (is_array($plans)): ?>
            <select name="creation_info[plan]" required>
                <option value="">Plan seçin</option>
                <?php foreach ($plans as $plan): ?>
                    <?php $name = trim((string) ($plan['name'] ?? '')); if ($name === '') continue; ?>
                    <option value="<?= $escape($name) ?>"<?= $selectedPlan === $name ? ' selected' : '' ?>><?= $escape($name) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div class="red-info"><div class="padding10"><?= $escape($moduleError !== '' ? $moduleError : 'Panelica planları alınamadı.') ?></div></div>
        <?php endif; ?>
    </div>
</div>

