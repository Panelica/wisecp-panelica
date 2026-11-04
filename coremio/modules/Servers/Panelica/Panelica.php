<?php
declare(strict_types=1);

namespace WISECP\Modules\Servers {

use RuntimeException;
use ServerModule;

if (class_exists(\ServerModule::class)) {
class Panelica extends ServerModule
{
    private ?ApiClient $api = null;
    private array $serverInfo = [];

    protected function define_server_info(array $server = []): void
    {
        $this->serverInfo = $server ?: (array) $this->server;
        $this->config['Api_key'] = (string) ($this->serverInfo['password'] ?? '');
        $this->config['Api_secret'] = (string) ($this->serverInfo['access_hash'] ?? '');
    }

    public function test_connect(): array|bool
    {
        $response = $this->client()->request('GET', '/v1/me');
        $data = $this->client()->data($response);
        return ['status' => 'successful', 'message' => 'Panelica API bağlantısı başarılı.', 'account' => $data['user']['username'] ?? ''];
    }

    /** WiseCP 3.1.x uses the camelCase server connection hook. */
    public function testConnect(): bool
    {
        try {
            $this->test_connect();
            return true;
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    /** @return array<string,mixed> */
    public function product_configuration(array $data = []): array
    {
        $plans = [];
        foreach ($this->client()->listPlans() as $plan) {
            $name = trim((string) ($plan['name'] ?? ''));
            if ($name !== '') {
                $plans[$name] = $name;
            }
        }
        if ($plans === []) {
            throw new RuntimeException('Panelica sunucusunda kullanılabilir hosting planı bulunamadı.');
        }
        return [
            'plan' => [
                'type' => 'select',
                'name' => 'Panelica Hosting Planı',
                'options' => $plans,
                'value' => (string) ($data['plan'] ?? ''),
                'required' => true,
            ],
        ];
    }

    public function create(): array|bool
    {
        $domain = $this->domain();
        $email = filter_var((string) ($this->user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!is_string($email)) {
            throw new RuntimeException('Hesap oluşturmak için geçerli bir müşteri e-posta adresi gerekli.');
        }

        $creation = (array) ($this->options['creation_info'] ?? []);
        $planName = trim((string) ($creation['plan'] ?? ''));
        $plan = $this->findPlan($planName);
        $username = trim((string) ($this->options['config']['user'] ?? ''));
        if ($username === '') {
            $username = (string) self::username_generator($domain);
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,31}$/', $username)) {
            throw new RuntimeException('Panelica için oluşturulan kullanıcı adı geçersiz.');
        }

        $storedPassword = !empty($this->options['config']['password']);
        $password = '';
        if ($storedPassword) {
            $password = (string) $this->decode_str((string) $this->options['config']['password']);
        }
        if (!$storedPassword) {
            $password = \Utility::generate_hash(20);
        }

        $account = $this->findAccountByUsername($username);
        $createdNewAccount = false;
        if ($account !== null && !$storedPassword) {
            throw new RuntimeException('Panelica hesabı mevcut ancak kayıtlı hizmet parolası yok; güvenli yeniden oluşturma yapılamadı.');
        }
        if ($account !== null && strcasecmp((string) ($account['email'] ?? ''), $email) !== 0) {
            throw new RuntimeException('Panelica kullanıcı adı başka bir e-posta adresine ait; hesap güvenliği için işlem durduruldu.');
        }
        if ($account === null) {
            $this->options['config']['user'] = $username;
            $this->options['config']['password'] = $this->encode_str($password);
            $this->save_options();
            $fullName = trim((string) ($this->user['full_name'] ?? ''));
            $created = $this->client()->request('POST', '/v1/accounts', [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'full_name' => $fullName,
                'role' => 'USER',
                'plan_id' => (string) ($plan['id'] ?? ''),
            ]);
            $account = $this->client()->data($created);
            if (empty($account['id'])) {
                $account = $this->findAccountByUsername($username);
            }
            $createdNewAccount = is_array($account) && !empty($account['id']);
        }
        if (!is_array($account) || empty($account['id'])) {
            throw new RuntimeException('Panelica hesabı oluşturdu ancak hesap kimliği yanıtlanmadı.');
        }

        try {
            $domainId = $this->ensureDomain($domain, (string) $account['id']);
        } catch (\Throwable $exception) {
            if ($createdNewAccount) {
                try {
                    $this->client()->request('DELETE', '/v1/accounts/' . rawurlencode((string) $account['id']));
                } catch (\Throwable) {
                    throw new RuntimeException('Alan adı oluşturulamadı ve yeni Panelica hesabı otomatik temizlenemedi. Panelica panelinden hesabı kontrol edin.', 0, $exception);
                }
            }
            throw $exception;
        }

        return [
            'config' => [
                'user' => $username,
                'password' => $this->encode_str($password),
                'panelica_account_id' => (string) $account['id'],
                'panelica_domain_id' => $domainId,
                'plan' => $planName,
            ],
            'ip' => (string) ($this->server['ip'] ?? ''),
            'hostname' => (string) ($this->server['hostname'] ?? $this->server['ip'] ?? ''),
        ];
    }

    public function suspend(): bool
    {
        $this->accountAction('suspend');
        return true;
    }

    public function unsuspend(): bool
    {
        $this->accountAction('unsuspend');
        return true;
    }

    public function cancel(): bool
    {
        $id = trim((string) ($this->options['config']['panelica_account_id'] ?? ''));
        if ($id === '') {
            $account = $this->findAccountByUsername((string) ($this->options['config']['user'] ?? ''));
            if ($account === null) {
                return true;
            }
            $id = (string) ($account['id'] ?? '');
        } elseif (!$this->accountExistsById($id)) {
            return true;
        }
        if ($id === '') {
            return true;
        }
        $this->client()->request('DELETE', '/v1/accounts/' . rawurlencode($id));
        return true;
    }

    public function terminate(): bool
    {
        return $this->cancel();
    }

    public function upgrade(array $new_product = []): bool
    {
        $newOptions = (array) ($new_product['module_data'] ?? $new_product['creation_info'] ?? []);
        if (isset($newOptions['Panelica']) && is_array($newOptions['Panelica'])) {
            $newOptions = $newOptions['Panelica'];
        }
        $planName = trim((string) ($newOptions['plan'] ?? $this->options['creation_info']['plan'] ?? ''));
        $plan = $this->findPlan($planName);
        $this->client()->request('PATCH', '/v1/accounts/' . rawurlencode($this->accountId()), [
            'plan_id' => (string) ($plan['id'] ?? ''),
        ]);
        $this->options['creation_info']['plan'] = $planName;
        $this->options['config']['plan'] = $planName;
        $this->save_options();
        return true;
    }

    public function change_password(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 255) {
            throw new RuntimeException('Parola 8 ile 255 karakter arasında olmalıdır.');
        }
        $this->client()->request('POST', '/v1/accounts/' . rawurlencode($this->accountId()) . '/change-password', [
            'new_password' => $password,
        ]);
        $this->options['config']['password'] = $this->encode_str($password);
        $this->save_options();
        return true;
    }

    /** @return array<string,mixed> */
    public function dashboard_data(): array
    {
        $accountId = $this->accountId();
        $diskResponse = $this->client()->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/disk-usage');
        $statsResponse = $this->client()->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/stats');
        $bandwidthResponse = $this->client()->request('GET', '/v1/bandwidth/accounts/' . rawurlencode($accountId));

        return [
            'domain' => $this->domain(),
            'username' => (string) ($this->options['config']['user'] ?? ''),
            'plan' => (string) ($this->options['config']['plan'] ?? ''),
            'disk' => $this->client()->data($diskResponse),
            'stats' => $this->client()->data($statsResponse),
            'bandwidth' => $this->client()->data($bandwidthResponse),
            'panel_url' => $this->panelUrl(),
            'webmail_url' => $this->webmailUrl(),
        ];
    }

    public function page_dashboard(): string
    {
        return $this->get_page('dashboard', ['data' => $this->dashboard_data()]);
    }

    private function accountAction(string $action): void
    {
        $id = $this->accountId();
        $account = $this->client()->data($this->client()->request('GET', '/v1/accounts/' . rawurlencode($id)));
        $desired = $action === 'suspend' ? 'suspended' : 'active';
        if (strcasecmp((string) ($account['status'] ?? ''), $desired) === 0) {
            return;
        }
        $this->client()->request('POST', '/v1/accounts/' . rawurlencode($id) . '/' . $action, []);
    }

    private function accountExistsById(string $id): bool
    {
        for ($page = 1; $page <= 100; $page++) {
            $accounts = $this->client()->listAccounts($page, 100);
            foreach ($accounts as $account) {
                if ((string) ($account['id'] ?? '') === $id) {
                    return true;
                }
            }
            if (count($accounts) < 100) {
                return false;
            }
        }
        throw new RuntimeException('Panelica hesap listesi güvenli biçimde doğrulanamadı.');
    }

    private function accountId(): string
    {
        $id = trim((string) ($this->options['config']['panelica_account_id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $account = $this->findAccountByUsername((string) ($this->options['config']['user'] ?? ''));
        if ($account === null || empty($account['id'])) {
            throw new RuntimeException('Panelica hesabı bulunamadı; işlem uygulanamadı.');
        }
        $id = (string) $account['id'];
        $this->options['config']['panelica_account_id'] = $id;
        $this->save_options();
        return $id;
    }

    /** @return array<string,mixed>|null */
    private function findAccountByUsername(string $username): ?array
    {
        for ($page = 1; $page <= 100; $page++) {
            $accounts = $this->client()->listAccounts($page, 100);
            foreach ($accounts as $account) {
                if (strcasecmp((string) ($account['username'] ?? ''), $username) === 0
                    && (!isset($account['role']) || strtoupper((string) $account['role']) === 'USER')) {
                    return $account;
                }
            }
            if (count($accounts) < 100) {
                break;
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function findPlan(string $name): array
    {
        if ($name === '') {
            throw new RuntimeException('Ürün ayarlarında Panelica hosting planı seçilmelidir.');
        }
        foreach ($this->client()->listPlans() as $plan) {
            if (strcasecmp(trim((string) ($plan['name'] ?? '')), $name) === 0 && !empty($plan['id'])) {
                return $plan;
            }
        }
        throw new RuntimeException('Seçilen Panelica planı bu sunucuda bulunamadı. Ürün planını kontrol edin.');
    }

    private function ensureDomain(string $domain, string $accountId): string
    {
        for ($page = 1; $page <= 100; $page++) {
            $domains = $this->client()->listDomains($page, 100);
            foreach ($domains as $existing) {
                if (strcasecmp((string) ($existing['domain_name'] ?? ''), $domain) === 0
                    && (string) ($existing['user_id'] ?? '') === $accountId) {
                    $existingId = trim((string) ($existing['id'] ?? ''));
                    if ($existingId === '') {
                        throw new RuntimeException('Panelica mevcut alan adı kaydının kimliği alınamadı.');
                    }
                    return $existingId;
                }
            }
            if (count($domains) < 100) {
                break;
            }
        }
        $created = $this->client()->request('POST', '/v1/domains', [
            'name' => $domain,
            'user_id' => $accountId,
        ]);
        $domainId = trim((string) ($this->client()->data($created)['id'] ?? ''));
        if ($domainId === '') {
            throw new RuntimeException('Panelica alan adını oluşturdu ancak alan adı kimliğini yanıtlamadı.');
        }
        return $domainId;
    }

    private function domain(): string
    {
        $domain = strtolower(rtrim(trim((string) ($this->options['domain'] ?? '')), '.'));
        if (strlen($domain) > 253 || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new RuntimeException('Hizmet alan adı geçersiz veya eksik.');
        }
        return $domain;
    }

    private function panelUrl(): string
    {
        $host = trim((string) ($this->server['hostname'] ?? $this->server['ip'] ?? ''));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $host = '[' . $host . ']';
        }
        if (preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])$/D', $host) !== 1) {
            throw new RuntimeException('Panelica panel adresi geçersiz.');
        }
        $scheme = !empty($this->server['secure']) ? 'https' : 'http';
        return $scheme . '://' . $host . ':' . (int) ($this->server['port'] ?? 8443) . '/';
    }

    private function webmailUrl(): string
    {
        return 'https://webmail.' . $this->domain() . '/';
    }

    private function client(): ApiClient
    {
        if ($this->api === null) {
            include_once __DIR__ . DS . 'ApiClient.php';
            $server = $this->serverInfo ?: (array) $this->server;
            $server['password'] = (string) ($this->config['Api_key'] ?? $server['password'] ?? '');
            $server['access_hash'] = (string) ($this->config['Api_secret'] ?? $server['access_hash'] ?? '');
            $this->api = new ApiClient($server, function (string $action, array $request, array $response): void {
                $this->save_log($action, $request, $response);
            });
        }
        return $this->api;
    }
}
}
}

namespace {

/** WiseCP 3.1.x legacy server-module entry point. */
if (!class_exists('WISECP\\Modules\\Servers\\ServerModule') && !class_exists('Panelica_Module')) {
    class Panelica_Module
    {
        private array $server;
        private array $moduleOptions = [];
        private ?\WISECP\Modules\Servers\ApiClient $api = null;

        public array $config = [];
        public array $options = [];
        public array $user = [];
        public array $order = [];
        public array $product = [];
        public $lang = [];
        public $error = '';

        public function __construct($server, $options = [])
        {
            $this->server = is_array($server) ? $server : [];
            $this->moduleOptions = is_array($options) ? $options : [];
            $this->options = $this->moduleOptions;
            $moduleConfig = \Modules::Config('Servers', 'Panelica');
            $externalConfig = $this->moduleOptions['config'] ?? $this->moduleOptions;
            $this->config = array_merge(is_array($moduleConfig) ? $moduleConfig : [], is_array($externalConfig) ? $externalConfig : []);
            $this->config['Api_key'] = (string) ($this->server['password'] ?? '');
            $this->config['Api_secret'] = (string) ($this->server['access_hash'] ?? '');
            $this->lang = \Modules::Lang('Servers', 'Panelica');
        }

        public function set_order($order = []): void
        {
            $this->order = is_array($order) ? $order : [];
            if ($this->order === []) {
                return;
            }
            \Helper::Load(['Products', 'User']);
            $this->product = \Products::get($this->order['type'] ?? '', $this->order['product_id'] ?? 0) ?: [];
            $this->user = \User::getData($this->order['owner_id'] ?? 0, 'id,name,surname,full_name,email,phone,lang,country', 'array') ?: [];
        }

        public function testConnect(): bool
        {
            try {
                $this->api()->request('GET', '/v1/me');
                return true;
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        public function test_connect(): bool
        {
            return $this->testConnect();
        }

        public function getPlans(): array|false
        {
            try {
                return $this->api()->listPlans();
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        public function createAccount($domain, $options = []): array|false
        {
            try {
                $domain = strtolower(rtrim(trim((string) $domain), '.'));
                if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                    throw new \RuntimeException('Hizmet alan adı geçersiz veya eksik.');
                }
                $options = is_array($options) ? $options : [];
                $creation = (array) ($options['creation_info'] ?? $options['module_data'] ?? []);
                if (isset($creation['create_account']) && is_array($creation['create_account'])) {
                    $creation = $creation['create_account'];
                }
                $planName = trim((string) ($creation['plan'] ?? ''));
                $plan = $this->findPlan($planName);
                $email = filter_var((string) ($this->user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
                if (!is_string($email)) {
                    throw new \RuntimeException('Geçerli müşteri e-posta adresi bulunamadı.');
                }

                $username = trim((string) ($options['config']['user'] ?? $options['username'] ?? ''));
                if ($username === '') {
                    $base = strtolower((string) strtok($domain, '.'));
                    $username = preg_replace('/[^a-z0-9_-]/', '', $base) ?: 'user';
                    $username = substr($username, 0, 20) . substr(bin2hex(random_bytes(4)), 0, 6);
                }
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,31}$/', $username)) {
                    throw new \RuntimeException('Panelica kullanıcı adı geçersiz.');
                }

                $password = \Utility::generate_hash(20);
                $account = $this->findAccount($username);
                if ($account !== null) {
                    if (strcasecmp((string) ($account['email'] ?? ''), $email) !== 0) {
                        throw new \RuntimeException('Panelica kullanıcı adı başka bir e-posta adresine ait.');
                    }
                    throw new \RuntimeException('Bu Panelica hesabı zaten mevcut. Mevcut hizmet bilgileriyle yeniden oluşturma yapın.');
                }

                $created = $this->api()->request('POST', '/v1/accounts', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'full_name' => trim((string) ($this->user['full_name'] ?? '')),
                    'role' => 'USER',
                    'plan_id' => (string) ($plan['id'] ?? ''),
                ]);
                $account = $this->api()->data($created);
                if (empty($account['id'])) {
                    throw new \RuntimeException('Panelica hesap kimliğini yanıtlamadı.');
                }
                try {
                    $domainResult = $this->api()->request('POST', '/v1/domains', [
                        'name' => $domain,
                        'user_id' => (string) $account['id'],
                    ]);
                } catch (\Throwable $exception) {
                    try {
                        $this->api()->request('DELETE', '/v1/accounts/' . rawurlencode((string) $account['id']));
                    } catch (\Throwable) {
                        throw new \RuntimeException('Alan adı oluşturulamadı ve yeni Panelica hesabı otomatik temizlenemedi. Panelica panelinden hesabı kontrol edin.', 0, $exception);
                    }
                    throw $exception;
                }
                $domainData = $this->api()->data($domainResult);
                if (empty($domainData['id'])) {
                    throw new \RuntimeException('Panelica alan adını oluşturdu ancak alan adı kimliğini yanıtlamadı.');
                }

                return [
                    'username' => $username,
                    'password' => $password,
                    'panelica_account_id' => (string) $account['id'],
                    'panelica_domain_id' => (string) ($domainData['id'] ?? ''),
                    'plan' => $planName,
                ];
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        public function suspend(): bool
        {
            return $this->accountAction('suspend');
        }

        public function unsuspend(): bool
        {
            return $this->accountAction('unsuspend');
        }

        public function removeAccount($username = false): bool
        {
            try {
                $id = trim((string) ($this->config['panelica_account_id'] ?? $this->moduleOptions['panelica_account_id'] ?? ''));
                if ($id === '') {
                    $account = $this->findAccount((string) ($username ?: $this->config['user'] ?? ''));
                    if ($account === null) {
                        return true;
                    }
                    $id = (string) ($account['id'] ?? '');
                }
                if ($id !== '') {
                    $this->api()->request('DELETE', '/v1/accounts/' . rawurlencode($id));
                }
                return true;
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        public function terminate(): bool
        {
            return $this->removeAccount();
        }

        public function change_plan($planName): bool
        {
            try {
                $plan = $this->findPlan(trim((string) $planName));
                $serviceConfig = (array) ($this->moduleOptions['config'] ?? []);
                $id = trim((string) ($serviceConfig['panelica_account_id'] ?? $this->moduleOptions['panelica_account_id'] ?? ''));
                if ($id === '') {
                    $username = trim((string) ($serviceConfig['user'] ?? $serviceConfig['username'] ?? $this->config['user'] ?? ''));
                    $account = $this->findAccount($username);
                    $id = (string) ($account['id'] ?? '');
                }
                if ($id === '') {
                    throw new \RuntimeException('Panelica hesabı bulunamadı.');
                }
                $this->api()->request('PATCH', '/v1/accounts/' . rawurlencode($id), ['plan_id' => (string) $plan['id']]);
                return true;
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        /** WiseCP 3.x order settings save hook; applies the selected plan to the active account. */
        public function apply_options($old_options, $new_options = []): array|false
        {
            $old_options = is_array($old_options) ? $old_options : [];
            $new_options = is_array($new_options) ? $new_options : [];
            $oldPlan = trim((string) ($old_options['creation_info']['plan'] ?? ''));
            $newPlan = trim((string) ($new_options['creation_info']['plan'] ?? ''));

            if ($newPlan === '') {
                $this->error = 'Panelica Hosting Planı seçilmelidir.';
                return false;
            }

            $this->moduleOptions = $old_options;
            $this->options = $old_options;
            if ($newPlan !== $oldPlan && !$this->change_plan($newPlan)) {
                return false;
            }

            $new_options['creation_info'] = (array) ($new_options['creation_info'] ?? []);
            $new_options['creation_info']['plan'] = $newPlan;
            $new_options['config'] = (array) ($new_options['config'] ?? $old_options['config'] ?? []);
            $new_options['config']['plan'] = $newPlan;
            $this->moduleOptions = $new_options;
            $this->options = $new_options;

            return $new_options;
        }

        /** WiseCP 3.1.x calls this after an approved hosting upgrade/downgrade. */
        public function apply_updowngrade($orderopt = [], $product = []): bool
        {
            $this->moduleOptions = is_array($orderopt) ? $orderopt : [];
            $this->options = $this->moduleOptions;
            $product = is_array($product) ? $product : [];
            $moduleData = (array) ($product['module_data'] ?? []);
            $creation = (array) ($moduleData['create_account'] ?? []);
            if ($creation === [] || array_key_exists('plan', $moduleData)) {
                $creation = $moduleData;
            }
            $planName = trim((string) ($creation['plan'] ?? ''));
            if ($planName === '') {
                $this->error = 'Yükseltme ürününde Panelica Hosting Planı seçilmelidir.';
                return false;
            }

            return $this->change_plan($planName);
        }

        public function activation_infos($type = 'html', $order = [], $lang = '')
        {
            $this->moduleOptions = is_array($order['options'] ?? null) ? $order['options'] : [];
            $this->options = $this->moduleOptions;
            $data = [
                'module' => $this,
                'options' => $this->moduleOptions,
                'server' => $this->server,
                'hosting' => $this->hostingDetails(),
            ];
            return \Modules::getPage('Servers', 'Panelica', 'activation-' . $type, $data);
        }

        /** WiseCP 3.x client-area hook for the active hosting service page. */
        public function clientArea_home(): string
        {
            return \Modules::getPage('Servers', 'Panelica', 'clientArea-home', [
                'module' => $this,
                'options' => $this->moduleOptions,
                'server' => $this->server,
                'hosting' => $this->hostingDetails(),
            ]);
        }

        /** @return array<string,mixed> */
        public function client_area_data(): array
        {
            return $this->hostingDetails();
        }

        /** @return array<string,mixed> */
        private function hostingDetails(): array
        {
            $service = (array) ($this->moduleOptions['config'] ?? []);
            $domain = strtolower(rtrim(trim((string) ($this->moduleOptions['domain'] ?? $service['domain'] ?? '')), '.'));
            $username = trim((string) ($service['user'] ?? $service['username'] ?? ''));
            $accountId = trim((string) ($service['panelica_account_id'] ?? $this->moduleOptions['panelica_account_id'] ?? ''));
            if ($accountId === '' && $username !== '') {
                try {
                    $account = $this->findAccount($username);
                    $accountId = (string) ($account['id'] ?? '');
                } catch (\Throwable $exception) {
                    $this->error = $exception->getMessage();
                }
            }

            $disk = $stats = $bandwidth = [];
            if ($accountId !== '') {
                $encodedId = rawurlencode($accountId);
                foreach ([
                    'disk' => '/v1/accounts/' . $encodedId . '/disk-usage',
                    'stats' => '/v1/accounts/' . $encodedId . '/stats',
                    'bandwidth' => '/v1/bandwidth/accounts/' . $encodedId,
                ] as $key => $path) {
                    try {
                        ${$key} = $this->api()->data($this->api()->request('GET', $path));
                    } catch (\Throwable $exception) {
                        $this->error = $exception->getMessage();
                    }
                }
            }

            $host = trim((string) ($this->server['hostname'] ?? $this->server['ip'] ?? ''));
            $host = preg_replace('#^https?://#i', '', $host) ?? $host;
            $host = trim($host, '/');
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $host = '[' . $host . ']';
            }
            if (preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])$/D', $host) !== 1) {
                $host = '';
            }
            $scheme = !empty($this->server['secure']) ? 'https' : 'http';

            return [
                'domain' => $domain,
                'username' => $username,
                'plan' => (string) ($service['plan'] ?? $this->moduleOptions['creation_info']['plan'] ?? ''),
                'disk' => $disk,
                'stats' => $stats,
                'bandwidth' => $bandwidth,
                'panel_url' => $host !== '' ? $scheme . '://' . $host . ':' . (int) ($this->server['port'] ?? 8443) . '/' : '',
                'webmail_url' => $domain !== '' && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false ? 'https://webmail.' . $domain . '/' : '',
            ];
        }

        private function accountAction(string $action): bool
        {
            try {
                $id = (string) ($this->config['panelica_account_id'] ?? $this->moduleOptions['panelica_account_id'] ?? '');
                if ($id === '') {
                    $account = $this->findAccount((string) ($this->config['user'] ?? ''));
                    $id = (string) ($account['id'] ?? '');
                }
                if ($id === '') {
                    throw new \RuntimeException('Panelica hesabı bulunamadı.');
                }
                $this->api()->request('POST', '/v1/accounts/' . rawurlencode($id) . '/' . $action, []);
                return true;
            } catch (\Throwable $exception) {
                $this->error = $exception->getMessage();
                return false;
            }
        }

        private function findPlan(string $name): array
        {
            foreach ($this->api()->listPlans() as $plan) {
                if (strcasecmp(trim((string) ($plan['name'] ?? '')), $name) === 0 && !empty($plan['id'])) {
                    return $plan;
                }
            }
            throw new \RuntimeException('Panelica hosting planı bulunamadı.');
        }

        private function findAccount(string $username): ?array
        {
            if ($username === '') {
                return null;
            }
            foreach ($this->api()->listAccounts() as $account) {
                if (strcasecmp((string) ($account['username'] ?? ''), $username) === 0
                    && (!isset($account['role']) || strtoupper((string) $account['role']) === 'USER')) {
                    return $account;
                }
            }
            return null;
        }

        private function api(): \WISECP\Modules\Servers\ApiClient
        {
            if ($this->api === null) {
                include_once __DIR__ . DS . 'ApiClient.php';
                $server = $this->server;
                $server['password'] = (string) ($this->config['Api_key'] ?? '');
                $server['access_hash'] = (string) ($this->config['Api_secret'] ?? '');
                $this->api = new \WISECP\Modules\Servers\ApiClient($server, function (string $action, array $request, array $response): void {
                    \Modules::save_log('Servers', 'Panelica', $action, $request, $response);
                });
            }
            return $this->api;
        }
    }
}

}

