<?php defined('CORE_FOLDER') OR exit('You can not get in here!'); $plans = isset($plans) && is_array($plans) ? $plans : array(); ?>
<div class="hizmetblok" id="block_module_details_con">
    <div class="block_module_details-title formcon"><h4>Hosting paketini değiştir</h4></div>
    <div class="clear"></div><br>
    <p>Seçtiğiniz plan Panelica sunucusunda onaylanıp hesabınıza uygulanır.</p>
    <?php if (!empty($error)): ?><p style="color:#a61b1b"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <form action="<?php echo htmlspecialchars($module->area_link, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="form-<?php echo htmlspecialchars($module->page, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="inc" value="panel_operation_method">
        <input type="hidden" name="method" value="<?php echo htmlspecialchars($module->page, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="panelica-plan-id">Panelica hosting paketi</label>
        <select name="plan_id" id="panelica-plan-id" required>
            <option value="">Paket seçin</option>
            <?php foreach ($plans as $id => $name): ?><option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"<?php echo isset($plan_id) && (string) $plan_id === (string) $id ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
        </select>
        <?php if (!$plans): ?><p>Panelica sunucusundan paket listesi alınamadı.</p><?php endif; ?>
        <a href="javascript:void(0);" id="panelica-change-plan-submit" class="yesilbtn gonderbtn" onclick='MioAjaxElement($(this),{"result":"t_form_handle"});'>Paketi Panelica sunucusunda güncelle</a>
    </form>
</div>

