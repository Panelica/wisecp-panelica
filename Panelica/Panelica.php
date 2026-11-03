<?php

/** Panelica hosting module for the WISECP 3 server-module contract. */
class Panelica_Module extends ServerModule
{
    private $api;
    private $apiReady = false;
    private $apiServerId = '';

    public function __construct($server, $options = array())
    {
        $this->_name = __CLASS__;
        parent::__construct($server, $options);
    }

    protected function define_server_info($server = array())
    {
        require_once __DIR__ . '/lib/PanelicaAPI.php';
        $host = isset($server['ip']) ? $server['ip'] : (isset($server['hostname']) ? $server['hostname'] : '');
        $port = isset($server['port']) ? (int) $server['port'] : 8443;
        $serverFields = isset($server['fields']) && is_array($server['fields']) ? $server['fields'] : array();
        $key = isset($serverFields['api_key']) ? trim((string) $serverFields['api_key']) : '';
        $username = isset($server['username']) ? trim((string) $server['username']) : '';
        $secret = isset($serverFields['api_secret']) ? trim((string) $serverFields['api_secret']) : '';
        $moduleConfig = include __DIR__ . '/config.php';
        $verifyTls = is_array($moduleConfig) && array_key_exists('panelica-verify-tls', $moduleConfig)
            ? (bool) $moduleConfig['panelica-verify-tls']
            : true;
        foreach (array('verify_ssl', 'ssl_verify', 'verify_ssl_certificate', 'ssl-verify') as $verifyKey) {
            if (array_key_exists($verifyKey, $server)) {
                $verifyTls = filter_var($server[$verifyKey], FILTER_VALIDATE_BOOLEAN);
                break;
            }
        }
        $this->api = new PanelicaAPI($host, $port, $key, $secret, $verifyTls);
        $this->apiReady = ($host !== '' && $username !== '' && $key !== '' && $secret !== '');
        $this->apiServerId = isset($server['id']) ? (string) $server['id'] : (isset($server['server_id']) ? (string) $server['server_id'] : '');
    }

    public function testConnect()
    {
        if (!$this->apiReady) {
            $this->error = 'Panelica bağlantısı için kullanıcı adı (Root/Reseller), API_KEY ve API_SECRET alanlarını doldurun. Parola alanı kullanılmaz.';
            return false;
        }
        try {
            $result = $this->api->me();
            if (!isset($result['status']) || $result['status'] !== 'success') {
                $this->error = 'Panelica API beklenmeyen bir yanıt verdi.';
                return false;
            }
            if (!$this->getPlans()) {
                $this->error = 'Panelica bağlantısı kuruldu ancak plan listesi boş. API anahtarında plans:read yetkisi olduğundan ve Panelica üzerinde hosting paketi bulunduğundan emin olun.';
                return false;
            }
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function config_options($data = array())
    {
        $plans = array();
        $planAliases = array();
        $loadError = '';
        try {
            // Rebind to the server currently selected in this product form.
            // The module instance can already have been initialized for a
            // different server before WISECP invokes this callback.
            $this->initializeProductServer($data);
            if (!$this->apiReady) {
                throw new Exception('WISECP ürün ayarları yüklenirken seçilen Panelica sunucusunun IP/host, API key veya Access Hash bilgisi modüle ulaşmadı. Sunucu kaydını kaydedip ürünü yeniden açın.');
            }
            foreach ($this->getPlans() as $plan) {
                if (!is_array($plan)) { continue; }
                $planId = $this->firstValue($plan, array('id', 'plan_id'));
                if ($planId === '') { continue; }
                $name = $this->planName($plan);
                $plans[$planId] = $name !== '' ? $name : $planId;
                foreach (array('id', 'plan_id', 'name', 'plan_name', 'title', 'slug') as $alias) {
                    if (isset($plan[$alias]) && is_scalar($plan[$alias]) && trim((string) $plan[$alias]) !== '') {
                        $planAliases[(string) $plan[$alias]] = $planId;
                    }
                }
            }
            if ($plans && method_exists($this, 'save_log')) {
                self::save_log(
                    'Servers',
                    $this->_name,
                    __FUNCTION__,
                    array('endpoint' => 'GET /v1/plans', 'server_id' => $this->apiServerId),
                    json_encode(array('plan_count' => count($plans)), JSON_UNESCAPED_SLASHES),
                    ''
                );
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $loadError = $e->getMessage();
            if (method_exists($this, 'save_log')) {
                $requestInfo = array('endpoint' => 'GET /v1/plans', 'server_id' => $this->apiServerId);
                $apiError = array('message' => $e->getMessage());
                if (method_exists($e, 'getHttpStatus')) { $apiError['http_status'] = $e->getHttpStatus(); }
                if (method_exists($e, 'getApiCode')) { $apiError['api_code'] = $e->getApiCode(); }
                self::save_log('Servers', $this->_name, __FUNCTION__, $requestInfo, json_encode($apiError, JSON_UNESCAPED_SLASHES), $e->getTraceAsString());
            }
        }
        if (!$plans && $loadError === '') {
            $loadError = 'Panelica plan listesi boş. API anahtarının plans:read yetkisini ve Panelica üzerinde hosting paketi bulunduğunu kontrol edin.';
            if (method_exists($this, 'save_log')) {
                self::save_log(
                    'Servers',
                    $this->_name,
                    __FUNCTION__,
                    array('endpoint' => 'GET /v1/plans', 'server_id' => $this->apiServerId),
                    json_encode(array('message' => 'API returned no usable hosting plans.'), JSON_UNESCAPED_SLASHES),
                    ''
                );
            }
        }
        $selected = $this->selectedPlanValue($data);
        if ($selected !== '' && isset($planAliases[$selected])) { $selected = $planAliases[$selected]; }
        if ($selected !== '' && !isset($plans[$selected])) {
            // Keep an existing name/ID visible if the API is temporarily down,
            // so saving the product cannot silently erase its selection.
            $plans[$selected] = $selected . ' (kayıtlı seçim; sunucuda doğrulanamadı)';
        }
        if (!$plans) { $plans[''] = 'Plan listesi yüklenemedi — modül kayıtlarını kontrol edin'; }
        return array('plan' => array(
            'wrap_width' => 100,
            'name' => 'Panelica Hosting Plan',
            'description' => $loadError !== '' ? $loadError : 'Panelica sunucusunda kullanılacak hosting paketini seçin.',
            'type' => 'dropdown',
            'options' => $plans,
            'value' => $selected,
        ));
    }

    public function create($domain_or_options = '', $order_options = array())
    {
        try {
            $providedDomain = '';
            if (is_array($domain_or_options)) {
                // Some WISECP V3 call paths pass only the options array.
                $order_options = $domain_or_options;
            } elseif (is_scalar($domain_or_options)) {
                // WISECP 3.1.9.8.2 hosting orders pass domain first, options second.
                $providedDomain = trim((string) $domain_or_options);
            }
            if (!is_array($order_options)) {
                $order_options = array();
            }

            $values = array_merge($this->serviceValues(), $order_options);
            if ($providedDomain !== '') {
                $values['domain'] = $providedDomain;
            }
            $domain = strtolower(trim($this->firstValue($values, array('domain', 'domain_name'))));
            if (!$this->validDomain($domain)) {
                throw new Exception('Geçerli bir alan adı gerekli.');
            }
            $planName = $this->selectedPlanValue($values);
            $productPlanSources = array();
            if ($planName === '') {
                // WISECP keeps the product's module selection on the product
                // record; it is not necessarily copied into order options.
                $productPlanSources = $this->productPlanSources();
                foreach ($productPlanSources as $productData) {
                    $planName = $this->selectedPlanValue($productData);
                    if ($planName !== '') { break; }
                }
            }
            if ($planName === '') { $planName = trim($this->savedValue('plan')); }
            if ($planName === '') {
                $this->logPlanSelectionDiagnostic($order_options, $values, $productPlanSources);
                throw new Exception('WISECP ürün ayarlarında Panelica hosting paketi seçilmemiş. Ürün > Otomasyon > Modül Yapılandırması bölümünden paketi seçip kaydedin.');
            }
            $plan = $this->findPlan($planName);
            if (!$plan || empty($plan['id'])) {
                throw new Exception('Seçilen Panelica paketi sunucudaki planlarla eşleşmiyor. Ürün modül ayarındaki paketi yenileyip tekrar kaydedin.');
            }

            $user = isset($this->user) && is_array($this->user) ? $this->user : array();
            $email = trim($this->firstValue(array_merge($values, $user), array('email', 'mail')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('WISECP müşterisinin geçerli bir e-posta adresi olmalıdır.');
            }
            $name = trim($this->firstValue($user, array('name', 'full_name')));
            if ($name === '') {
                $name = trim((isset($user['firstname']) ? $user['firstname'] : '') . ' ' . (isset($user['lastname']) ? $user['lastname'] : ''));
            }

            $username = $this->savedValue('user');
            if ($username === '') {
                $username = $this->firstValue($values, array('username', 'user'));
            }
            if ($username === '') {
                $username = preg_replace('/[^a-z0-9]/', '', strtolower(substr(strtok($domain, '.'), 0, 10)));
                $orderId = isset($this->order['id']) ? preg_replace('/[^0-9]/', '', (string) $this->order['id']) : '';
                $username = substr($username, 0, 10) . ($orderId !== '' ? substr($orderId, -5) : substr(md5($domain), 0, 5));
            }
            $username = substr(preg_replace('/[^a-zA-Z0-9_]/', '', $username), 0, 16);
            if ($username === '') {
                throw new Exception('Hosting kullanıcı adı üretilemedi.');
            }

            $password = $this->savedPassword();
            if ($password === '') {
                $password = bin2hex(function_exists('random_bytes') ? random_bytes(12) : openssl_random_pseudo_bytes(12));
            }
            $this->setSaved('user', $username);
            $this->setSaved('password', $this->encode_str($password));
            $this->persistOptions();
            $account = $this->api->findAccountByUsername($username);
            if (!$account) {
                try {
                    $created = $this->api->createAccount($username, $password, $email, $name, (string) $plan['id']);
                } catch (Exception $e) {
                    $taggedEmail = $this->taggedEmail($email);
                    if ($taggedEmail === '' || stripos($e->getMessage(), 'email') === false) { throw $e; }
                    $created = $this->api->createAccount($username, $password, $taggedEmail, $name, (string) $plan['id']);
                }
                $account = isset($created['data']) && is_array($created['data']) ? $created['data'] : array();
                if (empty($account['id'])) {
                    $account = $this->api->findAccountByUsername($username);
                }
            }
            if (!$account || empty($account['id'])) {
                throw new Exception('Panelica hesabı oluşturuldu ancak hesap kimliği alınamadı.');
            }
            $accountId = (string) $account['id'];
            $this->setSaved('panelica_account_id', $accountId);
            $this->persistOptions();

            $domains = $this->api->listAccountDomains($accountId);
            $found = false;
            foreach ((isset($domains['data']) && is_array($domains['data']) ? $domains['data'] : array()) as $existing) {
                $existingName = isset($existing['name']) ? $existing['name'] : (isset($existing['domain']) ? $existing['domain'] : '');
                if (strcasecmp($existingName, $domain) === 0) { $found = true; break; }
            }
            if (!$found) { $this->api->createDomain($domain, $accountId); }
            $this->persistOptions();
            return array(
                'ip' => isset($this->server['ip']) ? $this->server['ip'] : '',
                'login' => array('username' => $username, 'password' => $this->encode_str($password)),
                'config' => array(
                    'panelica_account_id' => $accountId,
                    'plan' => $this->planName($plan) !== '' ? $this->planName($plan) : $planName,
                    'plan_id' => (string) $plan['id'],
                    'user' => $username,
                    'password' => $this->encode_str($password),
                ),
            );
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function suspend() { return $this->accountAction('suspendAccount'); }
    public function unsuspend() { return $this->accountAction('unsuspendAccount'); }

    public function terminate()
    {
        try {
            $account = $this->findAccount();
            if (!$account || empty($account['id'])) {
                throw new Exception('Panelica hesabı silinemedi: WISECP hizmet kaydında hesaba ait kimlik veya kullanıcı adı bulunamadı.');
            }
            $this->api->deleteAccount((string) $account['id']);
            $this->logLifecycleResult('terminate', true, array('account_id_found' => true));
            return true;
        } catch (Exception $e) {
            $this->logLifecycleResult('terminate', false, array('message' => $e->getMessage()));
            return $this->fail($e);
        }
    }

    /** WISECP 3 may dispatch cancellation through cancel() in legacy flows. */
    public function cancel()
    {
        return $this->terminate();
    }

    public function apply_updowngrade($params = array())
    {
        try {
            $planName = $this->selectedPlanValue($params);
            if ($planName === '') { $planName = trim($this->savedValue('plan')); }
            $plan = $this->findPlan($planName);
            if (!$plan || empty($plan['id'])) { throw new Exception('Yeni Panelica paketi bulunamadı.'); }
            $account = $this->findAccount();
            if (!$account) { throw new Exception('WISECP hizmetine bağlı Panelica hesabı bulunamadı.'); }
            $this->api->updateAccount($account['id'], array('plan_id' => (string) $plan['id']));
            $this->persistOptions();
            return true;
        } catch (Exception $e) { return $this->fail($e); }
    }

    public function use_clientArea_SingleSignOn() { return $this->redirectSso(); }
    public function use_adminArea_SingleSignOn() { return $this->redirectSso(); }

    /** Render the hosting activation details in WISECP's client area and notices. */
    public function activation_infos($type = 'html', $order = array(), $lang = '')
    {
        $type = strtolower((string) $type);
        if (!in_array($type, array('html', 'text'), true)) { $type = 'html'; }
        if (is_object($order)) { $order = (array) $order; }
        if (!is_array($order)) { $order = array(); }
        $optionSources = array(
            isset($this->options) ? $this->options : array(),
            isset($this->service['options']) ? $this->service['options'] : array(),
            isset($this->order['options']) ? $this->order['options'] : array(),
            isset($order['options']) ? $order['options'] : array(),
        );
        $options = array();
        $savedConfig = array();
        foreach ($optionSources as $source) {
            if (is_string($source)) {
                $decoded = function_exists('json_decode') ? json_decode($source, true) : null;
                if (!is_array($decoded) && class_exists('Utility') && method_exists('Utility', 'jdecode')) {
                    $decoded = Utility::jdecode($source, true);
                }
                $source = is_array($decoded) ? $decoded : array();
            } elseif (is_object($source)) {
                $source = (array) $source;
            }
            if (!is_array($source)) { continue; }

            $sourceConfig = isset($source['config']) ? $source['config'] : array();
            if (is_string($sourceConfig)) {
                $decodedConfig = json_decode($sourceConfig, true);
                $sourceConfig = is_array($decodedConfig) ? $decodedConfig : array();
            }
            if (is_object($sourceConfig)) { $sourceConfig = (array) $sourceConfig; }
            if (!is_array($sourceConfig)) { $sourceConfig = array(); }
            unset($source['config']);
            $options = array_merge($options, $source);
            $savedConfig = array_merge($savedConfig, $sourceConfig);
        }
        if ($savedConfig) { $options['config'] = $savedConfig; }

        if (empty($options['domain'])) {
            foreach (array(isset($this->service) ? $this->service : array(), isset($this->order) ? $this->order : array(), $order) as $record) {
                if (is_array($record) && !empty($record['domain'])) {
                    $options['domain'] = $record['domain'];
                    break;
                }
            }
        }

        $data = array(
            'module' => $this,
            'options' => $options,
            'server' => isset($this->server) && is_array($this->server) ? $this->server : array(),
            'translations' => $this->activationTranslations($lang),
        );

        return Modules::getPage('Servers', 'Panelica', 'activation-' . $type, $data);
    }

    /** Load the small set of strings used by the customer-facing activation page. */
    private function activationTranslations($lang)
    {
        $language = strtolower(substr(trim((string) $lang), 0, 2));
        $file = __DIR__ . '/lang/' . ($language === 'en' ? 'en' : 'tr') . '.php';
        if (!is_file($file)) { return array(); }
        $translations = include $file;
        return is_array($translations) ? $translations : array();
    }

    public function use_clientArea_change_password()
    {
        if (!Filter::isPOST()) { return false; }
        $password = Filter::init('POST/password', 'password');
        try {
            if (!$password || strlen($password) < 8) { throw new Exception('Şifre en az 8 karakter olmalıdır.'); }
            $account = $this->findAccount();
            if (!$account) { throw new Exception('Panelica hesabı bulunamadı.'); }
            $this->api->changeAccountPassword($account['id'], $password);
            $this->setSaved('password', $this->encode_str($password));
            $this->persistOptions();
            echo Utility::jencode(array('status' => 'successful', 'message' => 'Şifre güncellendi.'));
            return true;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            echo Utility::jencode(array('status' => 'error', 'message' => $e->getMessage()));
            return false;
        }
    }

    private function accountAction($method)
    {
        try {
            $account = $this->findAccount();
            if (!$account) { throw new Exception('WISECP hizmetine bağlı Panelica hesabı bulunamadı.'); }
            $this->api->$method($account['id']);
            return true;
        } catch (Exception $e) { return $this->fail($e); }
    }

    private function redirectSso()
    {
        try {
            $account = $this->findAccount();
            if (!$account) { throw new Exception('Panelica hesabı bulunamadı.'); }
            $result = $this->api->ssoLogin($account['id']);
            $url = isset($result['data']['url']) ? $result['data']['url'] : '';
            $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));
            $serverHost = strtolower((string) (isset($this->server['ip']) ? $this->server['ip'] : (isset($this->server['hostname']) ? $this->server['hostname'] : '')));
            $serverHost = preg_replace('#^https?://#i', '', $serverHost);
            $serverHost = preg_replace('/:\\d+$/', '', explode('/', $serverHost, 2)[0]);
            if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || $urlHost === '' || $urlHost !== $serverHost) {
                throw new Exception('Panelica güvenli giriş bağlantısı sunucu adresiyle eşleşmiyor.');
            }
            Utility::redirect($url);
            return true;
        } catch (Exception $e) { $this->fail($e); echo 'Panelica giriş bağlantısı oluşturulamadı.'; return false; }
    }

    private function findAccount()
    {
        $id = $this->savedValue('panelica_account_id');
        $username = $this->savedValue('user');
        if ($username === '') { $username = $this->savedValue('username'); }
        if ($username === '' && isset($this->options['login']['username'])) { $username = $this->options['login']['username']; }
        if ($id === '' && $username === '') {
            throw new Exception('Panelica hesabı bulunamadı: WISECP modül çağrısında hizmet hesap bilgileri yok.');
        }
        $result = $this->api->listAccounts();
        foreach ((isset($result['data']) && is_array($result['data']) ? $result['data'] : array()) as $account) {
            if (($id !== '' && (string) (isset($account['id']) ? $account['id'] : '') === $id)
                || ($username !== '' && strcasecmp(isset($account['username']) ? $account['username'] : '', $username) === 0)) {
                if (!isset($account['role']) || strtoupper($account['role']) === 'USER') { return $account; }
            }
        }
        return null;
    }

    private function findPlan($name)
    {
        foreach ($this->getPlans() as $plan) {
            foreach (array('name', 'plan_name', 'title', 'slug', 'id', 'plan_id') as $key) {
                if (isset($plan[$key]) && strcasecmp((string) $plan[$key], (string) $name) === 0) { return $plan; }
            }
        }
        return null;
    }

    private function getPlans()
    {
        $response = $this->api->listPlans();
        return $this->normalizePlans($response);
    }

    /** Normalize both the Panelica raw-list response and its enveloped variants. */
    private function normalizePlans($payload, $depth = 0)
    {
        if (!is_array($payload) || !$payload || $depth > 3) { return array(); }
        if (isset($payload['id']) || isset($payload['plan_id']) || isset($payload['name']) || isset($payload['plan_name'])) {
            return array($payload);
        }

        $isList = true;
        foreach (array_keys($payload) as $index => $key) {
            if ($key !== $index) { $isList = false; break; }
        }
        if ($isList) { return array_values($payload); }

        foreach (array('plans', 'items', 'results', 'result', 'data') as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $plans = $this->normalizePlans($payload[$key], $depth + 1);
                if ($plans) { return $plans; }
            }
        }

        // Some panel versions key the list by plan UUID instead of returning a numeric list.
        $values = array_values($payload);
        foreach ($values as $value) {
            if (!is_array($value) || (!isset($value['id']) && !isset($value['plan_id']) && !isset($value['name']) && !isset($value['plan_name']))) {
                return array();
            }
        }
        return $values;
    }

    private function planName($plan)
    {
        if (!is_array($plan)) { return ''; }
        foreach (array('name', 'plan_name', 'title', 'slug') as $key) {
            if (isset($plan[$key]) && trim((string) $plan[$key]) !== '') { return trim((string) $plan[$key]); }
        }
        return '';
    }

    private function serviceValues()
    {
        $values = array();
        $sources = array(
            isset($this->options) ? $this->options : array(),
            isset($this->service['options']) ? $this->service['options'] : array(),
            isset($this->order['options']) ? $this->order['options'] : array(),
            isset($this->service['creation_info']) ? $this->service['creation_info'] : array(),
            isset($this->order['creation_info']) ? $this->order['creation_info'] : array(),
        );
        foreach ($sources as $source) {
            if (is_string($source)) {
                $decoded = json_decode($source, true);
                if (!is_array($decoded) && class_exists('Utility') && method_exists('Utility', 'jdecode')) {
                    $decoded = Utility::jdecode($source, true);
                }
                $source = is_array($decoded) ? $decoded : array();
            } elseif (is_object($source)) {
                $source = (array) $source;
            }
            if (!is_array($source)) { continue; }
            foreach (array('config', 'creation_info', 'login') as $nestedKey) {
                if (isset($source[$nestedKey]) && is_string($source[$nestedKey])) {
                    $decoded = json_decode($source[$nestedKey], true);
                    if (is_array($decoded)) { $source[$nestedKey] = $decoded; }
                } elseif (isset($source[$nestedKey]) && is_object($source[$nestedKey])) {
                    $source[$nestedKey] = (array) $source[$nestedKey];
                }
            }
            $values = array_merge($values, $source);
            foreach (array('config', 'creation_info', 'login') as $nestedKey) {
                if (isset($source[$nestedKey]) && is_array($source[$nestedKey])) {
                    $values = array_merge($values, $source[$nestedKey]);
                }
            }
        }
        return $values;
    }

    /** Record lifecycle callbacks without exposing API credentials or customer data. */
    private function logLifecycleResult($action, $success, array $details)
    {
        if (!method_exists($this, 'save_log')) { return; }
        $details['callback'] = $action;
        $details['success'] = (bool) $success;
        self::save_log(
            'Servers',
            $this->_name,
            $action,
            array(
                'order_id' => isset($this->order['id']) ? $this->order['id'] : null,
                'service_id' => isset($this->service['id']) ? $this->service['id'] : null,
                'server_id' => $this->apiServerId,
                'endpoint' => $success ? 'DELETE /v1/accounts/{id}' : 'resolve account before DELETE /v1/accounts/{id}',
            ),
            json_encode($details, JSON_UNESCAPED_SLASHES),
            ''
        );
    }

    /** Return product records that may hold WISECP's saved module configuration. */
    private function productPlanSources()
    {
        $sources = array();
        if (isset($this->product) && is_array($this->product)) { $sources[] = $this->product; }
        if (isset($this->service['product']) && is_array($this->service['product'])) { $sources[] = $this->service['product']; }
        if (isset($this->order['product']) && is_array($this->order['product'])) { $sources[] = $this->order['product']; }

        $productId = '';
        if (isset($this->product['id'])) { $productId = (string) $this->product['id']; }
        if ($productId === '' && isset($this->order['product_id'])) { $productId = (string) $this->order['product_id']; }
        if ($productId !== '' && ctype_digit($productId)) {
            if (!class_exists('Products') && class_exists('Helper')) { Helper::Load('Products'); }
            if (class_exists('Products')) {
                foreach (array('get', 'get_product') as $getter) {
                    if (!method_exists('Products', $getter)) { continue; }
                    $product = Products::$getter((int) $productId);
                    if (is_array($product) && $product) { $sources[] = $product; }
                    if ($sources && $this->selectedPlanValue($product)) { break; }
                }
            }
        }
        return $sources;
    }

    /** Load the selected server record when WISECP passes only its server ID to the product form. */
    private function initializeProductServer($data)
    {
        $serverId = $this->findServerId($data);
        if ($serverId === '' && isset($_REQUEST['server_id'])) {
            $serverId = preg_replace('/[^0-9]/', '', (string) $_REQUEST['server_id']);
        }
        if ($serverId === '' && isset($this->options) && is_array($this->options)) {
            $serverId = $this->findServerId($this->options);
        }
        if ($serverId === '' && isset($this->order['options']) && is_array($this->order['options'])) {
            $serverId = $this->findServerId($this->order['options']);
        }
        if ($serverId === '') { return $this->apiReady; }
        if ($this->apiReady && $this->apiServerId !== '' && $this->apiServerId === $serverId) { return true; }
        $this->apiReady = false;
        $this->apiServerId = $serverId;

        if (!class_exists('Products') && class_exists('Helper')) { Helper::Load('Products'); }
        if (!class_exists('Products') || !method_exists('Products', 'get_server')) { return false; }
        $server = Products::get_server($serverId);
        if (!is_array($server) || !$server) { return false; }

        $this->define_server_info($server);
        return $this->apiReady;
    }

    /** Log only plan-related key presence; never dump customer or server secrets. */
    private function logPlanSelectionDiagnostic($orderOptions, $values, $productSources = array())
    {
        if (!method_exists($this, 'save_log')) { return; }
        $orderOptions = is_array($orderOptions) ? $orderOptions : array();
        $values = is_array($values) ? $values : array();
        $productSources = is_array($productSources) ? $productSources : array();
        $productKeys = array();
        $productModuleDataKeys = array();
        $productPlanFound = false;
        foreach ($productSources as $productData) {
            if (!is_array($productData)) { continue; }
            $productKeys = array_values(array_unique(array_merge($productKeys, array_intersect(array_keys($productData), array('id', 'module', 'module_data', 'options')))));
            if (isset($productData['module_data']) && is_array($productData['module_data'])) {
                $productModuleDataKeys = array_values(array_unique(array_merge($productModuleDataKeys, array_intersect(array_keys($productData['module_data']), array('plan', 'plan_id', 'plan_name', 'panelica_plan', 'creation_info')))));
            }
            if ($this->selectedPlanValue($productData) !== '') { $productPlanFound = true; }
        }
        $diagnostic = array(
            'order_option_keys' => array_values(array_intersect(array_keys($orderOptions), array('plan', 'plan_name', 'plan_id', 'panelica_plan', 'config', 'creation_info', 'options', 'module_config', 'module_data'))),
            'order_config_keys' => isset($orderOptions['config']) && is_array($orderOptions['config']) ? array_values(array_intersect(array_keys($orderOptions['config']), array('plan', 'plan_name', 'plan_id', 'panelica_plan'))) : array(),
            'order_creation_info_keys' => isset($orderOptions['creation_info']) && is_array($orderOptions['creation_info']) ? array_values(array_intersect(array_keys($orderOptions['creation_info']), array('plan', 'plan_name', 'plan_id', 'panelica_plan'))) : array(),
            'module_option_keys' => isset($this->options) && is_array($this->options) ? array_values(array_intersect(array_keys($this->options), array('plan', 'plan_name', 'plan_id', 'panelica_plan', 'config', 'creation_info', 'options', 'module_config', 'module_data'))) : array(),
            'product_source_count' => count($productSources),
            'product_keys' => $productKeys,
            'product_module_data_keys' => $productModuleDataKeys,
            'product_plan_value_found' => $productPlanFound,
            'plan_value_found_after_normalization' => $this->selectedPlanValue($values) !== '',
        );
        self::save_log(
            'Servers',
            $this->_name,
            'create_plan_selection',
            array('order_id' => isset($this->order['id']) ? $this->order['id'] : null),
            json_encode($diagnostic, JSON_UNESCAPED_SLASHES),
            ''
        );
    }

    private function findServerId($source, $depth = 0)
    {
        if (!is_array($source) || $depth > 3) { return ''; }
        if (isset($source['server_id']) && is_scalar($source['server_id'])) {
            $id = preg_replace('/[^0-9]/', '', (string) $source['server_id']);
            if ($id !== '') { return $id; }
        }
        if (isset($source['server']) && is_scalar($source['server'])) {
            $id = preg_replace('/[^0-9]/', '', (string) $source['server']);
            if ($id !== '') { return $id; }
        }
        if (isset($source['server']) && is_array($source['server']) && isset($source['server']['id'])) {
            $id = preg_replace('/[^0-9]/', '', (string) $source['server']['id']);
            if ($id !== '') { return $id; }
        }
        foreach (array('options', 'module_config', 'module_data', 'server_config', 'creation_info') as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                $id = $this->findServerId($source[$key], $depth + 1);
                if ($id !== '') { return $id; }
            }
        }
        return '';
    }

    private function selectedPlanValue($source, $depth = 0)
    {
        if (is_object($source)) { $source = (array) $source; }
        if (!is_array($source) || $depth > 2) { return ''; }
        $plan = trim($this->firstValue($source, array('plan', 'plan_name', 'plan_id', 'panelica_plan')));
        if ($plan !== '') { return $plan; }
        if (isset($source['plan']) && (is_array($source['plan']) || is_object($source['plan']))) {
            $planField = (array) $source['plan'];
            $plan = trim($this->firstValue($planField, array('value', 'plan_id', 'id', 'name')));
            if ($plan !== '') { return $plan; }
        }
        foreach (array('config', 'creation_info', 'options', 'module_config', 'module_data') as $key) {
            if (!isset($source[$key])) { continue; }
            $nested = $source[$key];
            if (is_string($nested)) {
                $decoded = json_decode($nested, true);
                if (is_array($decoded)) { $nested = $decoded; }
            }
            if (is_array($nested) || is_object($nested)) {
                $plan = $this->selectedPlanValue($nested, $depth + 1);
                if ($plan !== '') { return $plan; }
            }
        }
        return '';
    }

    private function savedValue($key)
    {
        $values = $this->serviceValues();
        if (isset($values[$key]) && is_scalar($values[$key])) { return (string) $values[$key]; }
        return '';
    }

    private function savedPassword()
    {
        $saved = $this->savedValue('password');
        if ($saved === '') { return ''; }
        $decoded = $this->decode_str($saved);
        return $decoded === false ? $saved : $decoded;
    }

    private function setSaved($key, $value)
    {
        if (!isset($this->options['config']) || !is_array($this->options['config'])) { $this->options['config'] = array(); }
        $this->options['config'][$key] = $value;
    }

    private function persistOptions()
    {
        if (isset($this->order['id']) && class_exists('Orders') && class_exists('Utility')) {
            Orders::set($this->order['id'], array('options' => Utility::jencode($this->options)));
        }
    }

    private function firstValue($source, $keys)
    {
        foreach ($keys as $key) { if (isset($source[$key]) && is_scalar($source[$key]) && trim((string) $source[$key]) !== '') { return (string) $source[$key]; } }
        return '';
    }

    private function validDomain($domain)
    {
        return $domain !== '' && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function taggedEmail($email)
    {
        $at = strrpos($email, '@');
        if ($at === false) { return ''; }
        $local = substr($email, 0, $at);
        $plus = strpos($local, '+');
        if ($plus !== false) { $local = substr($local, 0, $plus); }
        $serviceId = isset($this->order['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->order['id']) : '';
        if ($serviceId === '') { return ''; }
        return $local . '+wisecp' . $serviceId . substr($email, $at);
    }

    private function fail($e)
    {
        $this->error = $e->getMessage();
        if (method_exists($this, 'save_log')) {
            self::save_log('Servers', $this->_name, __FUNCTION__, array('order' => isset($this->order) ? $this->order : array()), $e->getMessage(), $e->getTraceAsString());
        }
        return false;
    }
}
