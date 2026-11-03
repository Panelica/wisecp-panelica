<?php
$config = isset($options['config']) && is_array($options['config']) ? $options['config'] : array();
$domain = isset($options['domain']) ? (string) $options['domain'] : '';
$accountId = isset($config['panelica_account_id']) ? (string) $config['panelica_account_id'] : '';
$plan = isset($config['plan']) ? (string) $config['plan'] : (isset($options['creation_info']['plan']) ? (string) $options['creation_info']['plan'] : (isset($options['module_data']['plan']) ? (string) $options['module_data']['plan'] : ''));
$username = isset($config['user']) ? (string) $config['user'] : (isset($options['login']['username']) ? (string) $options['login']['username'] : '');
$serverHost = isset($server['hostname']) && $server['hostname'] !== '' ? (string) $server['hostname'] : (isset($server['name']) && $server['name'] !== '' ? (string) $server['name'] : (isset($server['ip']) ? (string) $server['ip'] : ''));
$serverHost = preg_replace('#^https?://#i', '', trim($serverHost));
$serverHost = trim(explode('/', $serverHost, 2)[0]);
$port = isset($server['port']) ? (int) $server['port'] : 8443;
?>
Panelica hosting hizmetiniz etkinleştirildi.
<?php if ($domain !== ''): ?>Alan adı: <?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
<?php endif; ?><?php if ($plan !== ''): ?>Hosting planı: <?php echo htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'); ?>
<?php endif; ?><?php if ($accountId !== ''): ?>Panelica hesap numarası: <?php echo htmlspecialchars($accountId, ENT_QUOTES, 'UTF-8'); ?>
<?php endif; ?><?php if ($username !== ''): ?>Kullanıcı adı: <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
<?php endif; ?><?php if ($serverHost !== ''): ?>Panelica paneli: https://<?php echo htmlspecialchars($serverHost, ENT_QUOTES, 'UTF-8'); ?><?php echo $port > 0 ? ':' . $port : ''; ?>
<?php endif; ?>Giriş için müşteri panelindeki Panelica güvenli giriş bağlantısını kullanın. Güvenlik nedeniyle hesap parolası bu sayfada gösterilmez.
<?php foreach (array('disk_limit' => 'Disk alanı', 'bandwidth_limit' => 'Trafik', 'email_limit' => 'E-posta hesabı', 'database_limit' => 'Veritabanı', 'subdomain_limit' => 'Alt alan adı', 'ftp_limit' => 'FTP hesabı') as $key => $title): ?><?php if (isset($options[$key]) && $options[$key] !== ''): ?>
- <?php echo $title; ?>: <?php echo htmlspecialchars($options[$key], ENT_QUOTES, 'UTF-8'); ?>
<?php endif; ?><?php endforeach; ?>
