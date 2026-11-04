<?php

declare(strict_types=1);

namespace WISECP\Modules\Servers;

use Exception;
use ServerModule;

/*
 * Panelica hosting accounts, driven from WISECP.
 *
 * The core calls the verbs below by name; a name it does not know is never
 * called. The first version of this module carried two generations of names at
 * once - testConnect() beside test_connect(), terminate() and removeAccount()
 * beside cancel(), apply_updowngrade() and change_plan() beside upgrade(),
 * clientArea_home() and client_area_data() beside dashboard_data() - so for
 * every job one name was read and the other sat unused. That is why the client
 * area showed nothing and deleting an account in WISECP left it standing on the
 * panel. There is one generation here, the current one.
 *
 * Failure is reported by throwing: WISECP catches it, writes it to the module
 * log and shows it. Returning false says "done, nothing to do".
 */
class Panelica extends ServerModule
{
    /** Where the account's id on the panel is kept between calls. */
    private const ACCOUNT_ID = 'panelica_account_id';

    protected PanelicaApi $api;

    /**
     * @param array<string,mixed>|int $server
     * @param array<string,mixed> $options
     */
    public function __construct($server = [], $options = [])
    {
        $this->_name = __CLASS__;
        parent::__construct($server, $options);
    }

    /**
     * Called once $this->server is filled and its secrets decoded.
     *
     * @param array<string,mixed> $server
     */
    protected function define_server_info($server = []): void
    {
        include_once __DIR__ . DIRECTORY_SEPARATOR . 'PanelicaApi.php';
        include_once __DIR__ . DIRECTORY_SEPARATOR . 'ApiClient.php';

        $this->api = new ApiClient($server, function (string $path, array $request, array $response): void {
            $this->save_log('api', json_encode(['path' => $path] + $request, JSON_UNESCAPED_UNICODE) ?: '', json_encode($response, JSON_UNESCAPED_UNICODE) ?: '');
        });
    }

    /** Which cards and buttons the service page offers. */
    public function configure_features(): void
    {
        $this->dashboard_cache = false;
    }

    public function test_connect(): bool
    {
        // /v1/plans needs the key, the secret and the clock to be right, and it
        // reads nothing: a failed signature answers 401 and throws.
        $this->api->get('/v1/plans');

        return true;
    }

    /**
     * The product form: which Panelica plan a package hands out.
     *
     * @param array<string,mixed> $data
     * @return array<string,array<string,mixed>>
     */
    public function product_configuration(array $data = []): array
    {
        try {
            $plans = $this->planChoices();
            $unavailable = null;
        } catch (Exception $e) {
            $plans = null;
            $unavailable = $this->text('plans_unavailable', ['error' => $e->getMessage()]);
        }

        $plan = $plans === null
            ? ['name' => $this->text('setting_plan'), 'description' => $unavailable, 'type' => 'text', 'value' => (string) ($data['plan_id'] ?? ''), 'wrap_width' => 'col-lg-6']
            : ['name' => $this->text('setting_plan'), 'description' => $this->text('setting_plan_description'), 'type' => 'dropdown', 'options' => $plans, 'value' => (string) ($data['plan_id'] ?? ''), 'wrap_width' => 'col-lg-6'];

        return [
            'plan_id' => $plan,
            'account_role' => [
                'name' => $this->text('setting_role'),
                'description' => $this->text('setting_role_description'),
                'type' => 'dropdown',
                'options' => ['USER' => $this->text('role_user'), 'RESELLER' => $this->text('role_reseller')],
                'value' => (string) ($data['account_role'] ?? 'USER'),
                'wrap_width' => 'col-lg-6',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function save_product_configuration(array $data = []): array
    {
        // The plan is what every later call reads, so a package without one is
        // refused here rather than at the customer's first order.
        if (trim((string) ($data['plan_id'] ?? '')) === '') {
            throw new Exception($this->text('plan_required'));
        }

        $role = strtoupper(trim((string) ($data['account_role'] ?? 'USER')));

        return ['plan_id' => trim((string) $data['plan_id']), 'account_role' => in_array($role, ['USER', 'RESELLER'], true) ? $role : 'USER'];
    }

    /**
     * Open the account. What is returned is written onto the service.
     *
     * @return array<string,mixed>
     */
    public function create(): array
    {
        $domain = $this->domain();
        $username = $this->username($domain);
        $password = $this->password();

        $existing = $this->findAccount($username);
        if ($existing !== null) {
            // A retry after a half-finished order: the account is there, so the
            // service is given what it needs instead of a duplicate failing.
            return $this->created((string) $existing['id'], $username, $password, $domain);
        }

        $account = $this->api->post('/v1/accounts', [
            'username' => $username,
            'email' => $this->email(),
            'password' => $password,
            'full_name' => $this->fullName(),
            'role' => $this->role(),
            'plan_id' => $this->planId(),
            'auto_username' => true,
        ]);

        $id = (string) ($account['id'] ?? '');
        if ($id === '') {
            throw new Exception($this->text('no_account_returned'));
        }

        // auto_username: the panel may have made the name readable and free.
        $username = (string) ($account['username'] ?? $username);

        if ($domain !== '') {
            $this->api->post('/v1/domains', ['user_id' => $id, 'domain_name' => $domain]);
        }

        return $this->created($id, $username, $password, $domain);
    }

    public function suspend(): bool
    {
        $this->api->post('/v1/accounts/' . $this->accountId() . '/suspend', ['reason' => trim((string) ($this->service['suspended_reason'] ?? '')) ?: 'Suspended from WISECP']);

        return true;
    }

    public function unsuspend(): bool
    {
        $this->api->post('/v1/accounts/' . $this->accountId() . '/unsuspend');

        return true;
    }

    /**
     * Delete the account on the panel.
     *
     * The first version never reached the panel: WISECP calls cancel(), and the
     * delete lived in removeAccount()/terminate(), which nothing calls. An
     * account already gone counts as done, so a repeated cancellation does not
     * leave the service stuck.
     */
    public function cancel(): bool
    {
        $id = $this->accountId(required: false);

        if ($id === '') {
            return true;
        }

        try {
            $this->api->delete('/v1/accounts/' . $id);
        } catch (Exception $e) {
            if (! $this->api->lastStatusWas(404)) {
                throw $e;
            }
        }

        return true;
    }

    public function change_password(string $password = ''): bool
    {
        $password = trim($password);
        if ($password === '') {
            throw new Exception($this->text('password_required'));
        }

        $this->api->post('/v1/accounts/' . $this->accountId() . '/change-password', ['password' => $password]);
        $this->save_login_password($password);

        return true;
    }

    /**
     * Move the account to the plan of the package it was moved to.
     *
     * @param array<string,mixed> $new_product
     */
    public function upgrade(array $new_product = []): bool
    {
        $module = is_array($new_product['module_data'] ?? null) ? $new_product['module_data'] : [];
        $plan = trim((string) ($module['plan_id'] ?? ''));

        if ($plan === '') {
            // Nothing to move to: the new package has no Panelica plan, which
            // is a configuration mistake and not something to do quietly.
            throw new Exception($this->text('plan_required'));
        }

        $this->api->patch('/v1/accounts/' . $this->accountId(), ['plan_id' => $plan]);

        return true;
    }

    /**
     * What the customer sees on the service page.
     *
     * @return array<string,mixed>
     */
    public function dashboard_data(): array
    {
        $account = $this->api->get('/v1/accounts/' . $this->accountId());
        $usage = $this->usage();
        $host = $this->panelHost();

        return [
            'gauges' => array_filter([
                'disk' => $usage['disk'] ?? null,
                'bandwidth' => $usage['bandwidth'] ?? null,
            ]),
            'server_info' => array_filter([
                'domain' => $this->item($this->domain(), copyable: true),
                'username' => $this->item((string) ($account['username'] ?? ''), copyable: true),
                'status' => $this->statusItem((string) ($account['status'] ?? 'active')),
                'plan' => $this->item((string) ($account['plan']['name'] ?? $account['plan_name'] ?? '')),
                'panel' => $this->item($host === '' ? '' : 'https://' . $host . ':' . $this->panelPort(), link: true),
                'nameservers' => $this->item($this->nameservers()),
            ]),
        ];
    }

    /**
     * Disk and traffic for the usage cron and the gauges.
     *
     * @return array<string,float|int>
     */
    public function metrics_usage(): array
    {
        $usage = $this->usage();

        return array_filter([
            'disk' => $usage['disk']['used'] ?? null,
            'bandwidth' => $usage['bandwidth']['used'] ?? null,
        ], static fn ($value): bool => $value !== null);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The account's id on the panel, kept on the service options by create().
     */
    private function accountId(bool $required = true): string
    {
        $id = trim((string) ($this->options[self::ACCOUNT_ID] ?? ''));

        if ($id === '' && $required) {
            throw new Exception($this->text('no_account_id'));
        }

        return $id;
    }

    /**
     * @return array<string,mixed>
     */
    private function created(string $id, string $username, string $password, string $domain): array
    {
        $this->options[self::ACCOUNT_ID] = $id;
        $this->save_options();
        $this->save_login_password($password);

        $host = $this->panelHost();

        return [
            'username' => $username,
            'password' => $password,
            self::ACCOUNT_ID => $id,
            'ftp_info' => [
                'ip' => (string) ($this->server['ip'] ?? ''),
                'host' => $host !== '' ? $host : (string) ($this->server['ip'] ?? ''),
                'username' => $username,
                'password' => $password,
                'port' => 21,
            ],
            'domain' => $domain,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findAccount(string $username): ?array
    {
        if ($username === '') {
            return null;
        }

        try {
            $accounts = $this->api->get('/v1/accounts');
        } catch (Exception) {
            return null;    // the list is a convenience; a failure must not stop an order
        }

        foreach (is_array($accounts) ? $accounts : [] as $account) {
            if (is_array($account) && strcasecmp((string) ($account['username'] ?? ''), $username) === 0) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function usage(): array
    {
        $id = $this->accountId(required: false);
        if ($id === '') {
            return [];
        }

        $out = [];

        try {
            $quota = $this->api->get('/v1/resource-quota/users/' . $id);
            $used = (float) ($quota['disk_used_mb'] ?? $quota['disk_usage_mb'] ?? 0);
            $limit = (float) ($quota['disk_limit_mb'] ?? $quota['disk_quota_mb'] ?? 0);
            if ($limit > 0 || $used > 0) {
                $out['disk'] = ['used' => $used, 'total' => $limit, 'unit' => 'MB'];
            }
        } catch (Exception) {
            // A panel that cannot answer leaves the gauge off the page rather
            // than taking the page down with it.
        }

        try {
            $bandwidth = $this->api->get('/v1/bandwidth/accounts/' . $id);
            $used = (float) ($bandwidth['used_mb'] ?? $bandwidth['total_mb'] ?? 0);
            $limit = (float) ($bandwidth['limit_mb'] ?? 0);
            if ($limit > 0 || $used > 0) {
                $out['bandwidth'] = ['used' => $used, 'total' => $limit, 'unit' => 'MB'];
            }
        } catch (Exception) {
        }

        return $out;
    }

    /** @return array<string,string> */
    private function planChoices(): array
    {
        $plans = $this->api->get('/v1/plans');
        $choices = [];

        foreach (is_array($plans) ? $plans : [] as $plan) {
            if (! is_array($plan)) {
                continue;
            }
            $id = (string) ($plan['id'] ?? '');
            if ($id !== '') {
                $choices[$id] = (string) ($plan['name'] ?? $id);
            }
        }

        return $choices;
    }

    private function planId(): string
    {
        $plan = trim((string) ($this->options['plan_id'] ?? ''));

        if ($plan === '') {
            throw new Exception($this->text('plan_required'));
        }

        return $plan;
    }

    private function role(): string
    {
        $role = strtoupper(trim((string) ($this->options['account_role'] ?? 'USER')));

        return in_array($role, ['USER', 'RESELLER'], true) ? $role : 'USER';
    }

    private function domain(): string
    {
        foreach ([$this->service['domain'] ?? null, $this->options['domain'] ?? null] as $value) {
            $domain = strtolower(trim((string) $value));
            if ($domain !== '') {
                return $domain;
            }
        }

        return '';
    }

    private function username(string $domain): string
    {
        $given = trim((string) ($this->options['username'] ?? ''));
        if ($given !== '') {
            return $given;
        }

        // From the domain, as the panel wants it: letters and digits, starting
        // with a letter, at most sixteen characters.
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($domain)) ?? '';
        if ($base === '' || ! ctype_alpha($base[0])) {
            $base = 'pnl' . $base;
        }

        return substr($base, 0, 16);
    }

    private function password(): string
    {
        $given = trim((string) ($this->options['password'] ?? ''));
        if ($given !== '') {
            return $given;
        }

        // Long enough for the panel's own rule (eight characters) with room to
        // spare, and from a source meant for secrets.
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%*-_';
        $password = '';
        for ($i = 0; $i < 16; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function email(): string
    {
        foreach ([$this->service['user_email'] ?? null, $this->options['email'] ?? null] as $value) {
            $email = trim((string) $value);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return $email;
            }
        }

        $domain = $this->domain();

        return $domain !== '' ? 'hostmaster@' . $domain : 'hostmaster@localhost';
    }

    private function fullName(): string
    {
        $name = trim((string) ($this->service['user_name'] ?? ''));

        return $name !== '' ? $name : $this->domain();
    }

    private function panelHost(): string
    {
        foreach ([$this->server['hostname'] ?? null, $this->server['name'] ?? null, $this->server['ip'] ?? null] as $value) {
            $host = trim((string) $value);
            if ($host !== '') {
                return preg_replace('#^https?://#i', '', $host) ?? $host;
            }
        }

        return '';
    }

    private function panelPort(): int
    {
        $port = (int) ($this->server['port'] ?? 8443);

        return $port > 0 && $port < 65536 ? $port : 8443;
    }

    private function nameservers(): string
    {
        $ns = [];
        foreach (['ns1', 'ns2', 'ns3', 'ns4'] as $key) {
            $value = trim((string) ($this->server[$key] ?? ''));
            if ($value !== '') {
                $ns[] = $value;
            }
        }

        return implode(', ', $ns);
    }

    /** @return array<string,mixed> */
    private function item(string $value, bool $copyable = false, bool $link = false): array
    {
        if (trim($value) === '') {
            return [];
        }

        $item = ['value' => $value];
        if ($copyable) {
            $item['copyable'] = true;
        }
        if ($link) {
            $item['link'] = $value;
        }

        return $item;
    }

    /** @return array<string,mixed> */
    private function statusItem(string $status): array
    {
        $status = strtolower($status) ?: 'active';

        return [
            'value' => $this->text('status_' . $status) ?: $status,
            'badge_color' => match ($status) {
                'active' => 'success',
                'suspended', 'terminated', 'deleted' => 'danger',
                default => 'warning',
            },
        ];
    }

    /**
     * A string from lang/, with :placeholders filled.
     *
     * @param array<string,string> $replace
     */
    private function text(string $key, array $replace = []): string
    {
        $text = (string) ($this->lang[$key] ?? '');

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, $value, $text);
        }

        return $text;
    }
}
