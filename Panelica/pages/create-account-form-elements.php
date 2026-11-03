<?php
/**
 * Displays live Panelica plans in WISECP product/account creation forms.
 * config_options() binds the module to the Panelica server selected in the form
 * and fetches plans from GET /v1/plans.
 */
$panelicaProduct = isset($product) && is_array($product) ? $product : array();
$panelicaModuleData = isset($panelicaProduct['module_data']) ? $panelicaProduct['module_data'] : array();
if (is_string($panelicaModuleData)) {
    $panelicaDecoded = json_decode($panelicaModuleData, true);
    if (is_array($panelicaDecoded)) { $panelicaModuleData = $panelicaDecoded; }
}
if (!is_array($panelicaModuleData)) { $panelicaModuleData = array(); }
$panelicaPlans = array();
$panelicaCreationInfo = isset($creation_info) && is_array($creation_info) ? $creation_info : array();
$panelicaSelected = '';
$panelicaDescription = '';
$panelicaError = '';

if (isset($module) && is_object($module) && method_exists($module, 'config_options')) {
    try {
        $panelicaConfigFields = $module->config_options($panelicaProduct);
        if (isset($panelicaConfigFields['plan']) && is_array($panelicaConfigFields['plan'])) {
            $panelicaField = $panelicaConfigFields['plan'];
            $panelicaPlans = isset($panelicaField['options']) && is_array($panelicaField['options']) ? $panelicaField['options'] : array();
            $panelicaSelected = isset($panelicaField['value']) && is_scalar($panelicaField['value']) ? (string) $panelicaField['value'] : '';
            $panelicaDescription = isset($panelicaField['description']) ? (string) $panelicaField['description'] : '';
        } else {
            $panelicaError = 'WISECP modül yapılandırması plan alanını döndürmedi.';
        }
    } catch (Throwable $e) {
        $panelicaError = $e->getMessage();
    }
} else {
    $panelicaError = 'Panelica modül yapılandırmasına erişilemiyor.';
}

if (isset($panelicaModuleData['plan']) && is_scalar($panelicaModuleData['plan'])) {
    $panelicaSelected = (string) $panelicaModuleData['plan'];
}
if (isset($panelicaCreationInfo['plan']) && is_scalar($panelicaCreationInfo['plan'])) {
    $panelicaSelected = (string) $panelicaCreationInfo['plan'];
}
$panelicaEscape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$panelicaHasUsablePlan = false;
foreach ($panelicaPlans as $panelicaPlanKey => $panelicaPlanLabel) {
    if ((string) $panelicaPlanKey !== '' && (string) $panelicaPlanKey !== $panelicaSelected) {
        $panelicaHasUsablePlan = true;
        break;
    }
    if ((string) $panelicaPlanKey !== '') {
        $panelicaHasUsablePlan = true;
        break;
    }
}
?>
<div class="formcon">
    <div class="yuzde30"><label for="panelica_plan">Panelica Hosting Planı</label></div>
    <div class="yuzde70">
        <?php if ($panelicaHasUsablePlan): ?>
            <select name="module_data[plan]" id="panelica_plan">
                <option value="">Hosting planı seçin</option>
                <?php foreach ($panelicaPlans as $panelicaPlanKey => $panelicaPlanLabel): ?>
                    <?php if ((string) $panelicaPlanKey === '') { continue; } ?>
                    <option value="<?php echo $panelicaEscape($panelicaPlanKey); ?>"<?php echo (string) $panelicaPlanKey === $panelicaSelected ? ' selected' : ''; ?>><?php echo $panelicaEscape($panelicaPlanLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($panelicaDescription !== ''): ?><div class="small-text"><?php echo $panelicaEscape($panelicaDescription); ?></div><?php endif; ?>
        <?php else: ?>
            <div class="red-info"><div class="padding10">
                <?php echo $panelicaEscape($panelicaError !== '' ? $panelicaError : ($panelicaDescription !== '' ? $panelicaDescription : 'Panelica sunucusunda kullanılabilir hosting planı bulunamadı.')); ?>
                Sunucu bağlantısını, API anahtarındaki <code>plans:read</code> iznini ve Panelica sunucusunda tanımlı hosting planlarını kontrol edin.
            </div></div>
        <?php endif; ?>
    </div>
</div>
