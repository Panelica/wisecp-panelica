<?php defined('CORE_FOLDER') OR exit('You can not get in here!'); ?>
<div class="hizmetblok" id="block_module_details_con">
    <div class="block_module_details-title formcon"><h4>Hosting parolasını değiştir</h4></div>
    <div class="clear"></div><br>
    <p>Yeni parola Panelica sunucusunda aynı anda güncellenir.</p>
    <form action="<?php echo htmlspecialchars($module->area_link, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="form-<?php echo htmlspecialchars($module->page, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="inc" value="panel_operation_method">
        <input type="hidden" name="method" value="<?php echo htmlspecialchars($module->page, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="panelica-new-password">Yeni hosting parolası</label>
        <input name="password" id="panelica-new-password" class="yuzde50inpt" type="password" minlength="8" autocomplete="new-password" required>
        <a href="javascript:void(0);" id="panelica-change-password-submit" class="yesilbtn gonderbtn" onclick='MioAjaxElement($(this),{"result":"t_form_handle"});'>Parolayı Panelica sunucusunda değiştir</a>
    </form>
</div>

