<?php
$config = isset($options['config']) && is_array($options['config']) ? $options['config'] : array();
$domain = isset($options['domain']) ? (string) $options['domain'] : '';
$accountId = isset($config['panelica_account_id']) ? (string) $config['panelica_account_id'] : '';
$plan = isset($config['plan']) ? (string) $config['plan'] : '';
$h = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<table width="100%" border="0" cellpadding="3" cellspacing="0">
    <tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Otomasyon modülü</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">Panelica</td></tr>
    <?php if ($domain !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Alan adı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($domain); ?></td></tr><?php endif; ?>
    <?php if ($plan !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Panelica planı</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($plan); ?></td></tr><?php endif; ?>
    <?php if ($accountId !== ''): ?><tr><td style="width:30%;padding:8px;border-bottom:1px solid #eee;"><strong>Panelica hesap numarası</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><?php echo $h($accountId); ?></td></tr><?php endif; ?>
    <tr><td colspan="2" style="padding:8px;color:#666;">Hesap parolası ve API kimlik bilgileri bu sipariş görünümünde gösterilmez.</td></tr>
</table>