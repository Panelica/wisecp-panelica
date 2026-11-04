<?php
declare(strict_types=1);

$product = is_array($product ?? null) ? $product : [];
$options = is_array($options ?? null) ? $options : [];
$productData = $product['module_data'] ?? [];
if (is_string($productData) && $productData !== '') {
    $productData = class_exists('Utility') ? (Utility::jdecode($productData, true) ?: []) : [];
}
$productData = is_array($productData) ? $productData : [];
$createData = is_array($productData['create_account'] ?? null) ? $productData['create_account'] : $productData;
$selectedPlan = (string) ($createData['plan'] ?? $options['creation_info']['plan'] ?? $options['module_data']['plan'] ?? '');
$plans = $module->getPlans();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="formcon">
    <div class="yuzde30">Panelica Hosting Planı</div>
    <div class="yuzde70">
        <?php if (is_array($plans)): ?>
            <select name="module_data[plan]" id="module_data_plan" required>
                <option value="">Plan seçin</option>
                <?php foreach ($plans as $plan): ?>
                    <?php $name = (string) ($plan['name'] ?? ''); if ($name === '') continue; ?>
                    <option value="<?= $escape($name) ?>"<?= $selectedPlan === $name ? ' selected' : '' ?>><?= $escape($name) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div class="red-info"><div class="padding10"><?= $escape($module->error ?: 'Panelica planları alınamadı.') ?></div></div>
        <?php endif; ?>
    </div>
</div>

