<?php

declare(strict_types=1);

namespace WISECP\Modules\Servers;

use Exception;

/*
 * Everything the module does, once, for both generations of WISECP.
 *
 * WISECP 3 and WISECP 5 ask for the same work under different names, from
 * different places, with different helpers on the base class:
 *
 *            WISECP 3                      WISECP 5
 *   data     $this->order['options']       $this->service + $this->options
 *   log      self::save_log(6 args)        $this->save_log(3 args)
 *   store    - (the core writes it)        $this->save_options()
 *   password - (returned from create)      $this->save_login_password()
 *
 * A module that calls a helper the host does not have dies with a fatal, so
 * every call that is not in both goes through a compatibility method here that
 * asks what it is running on first. The verbs themselves live in Panelica.php,
 * one set per generation, and both hand the work to this trait: the core is
 * written once and tested once, against both hosts.
 */
trait PanelicaCore
{
    // ACCOUNT_ID is declared on the class that uses this trait, not here: a
    // constant in a trait needs PHP 8.2, and the module has to run on the
    // installs that are out there.

    protected PanelicaApi $api;

    /** The id looked up from the username, for the rest of this call. */
    private ?string $resolvedAccountId = null;

    /** What was last handed to the host to store, so it is not stored twice. */
    private ?string $lastStoredPassword = null;

    /**
     * A property only one generation declares.
     *
     * WISECP 5 has $this->service; WISECP 3 has $this->order, $this->config and
     * $this->page. Reading one straight off the other host raises a warning on
     * every call, so every such read comes through here.
     *
     * @return array<string,mixed>
     */
    private function hostArray(string $name): array
    {
        if (! property_exists($this, $name)) {
            return [];
        }

        $value = $this->{$name};

        return is_array($value) ? $value : [];
    }

    // ------------------------------------------------------- the host we are on

    /** WISECP 5 and later: the base class stores options and passwords for us. */
    private function hostStoresOptions(): bool
    {
        return method_exists($this, 'save_options');
    }

    private function hostStoresPassword(): bool
    {
        return method_exists($this, 'save_login_password');
    }

    /**
     * One log line, written the way this host writes them.
     *
     * @param array<string,mixed> $request
     * @param array<string,mixed> $response
     */
    private function logCall(string $action, array $request, array $response): void
    {
        $asJson = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        // WISECP 5: an instance method, three strings.
        if (method_exists($this, 'save_log')) {
            $reflection = new \ReflectionMethod($this, 'save_log');

            if ($reflection->getNumberOfParameters() <= 4) {
                $this->save_log($action, $asJson($request), $asJson($response));

                return;
            }

            // WISECP 3: static, and it wants the module's own name and the order.
            $name = property_exists($this, '_name') && is_string($this->_name) && $this->_name !== '' ? $this->_name : 'Panelica';
            $reflection->invoke($reflection->isStatic() ? null : $this, 'Servers', $name, $action, $request, $asJson($response), '');
        }
    }

    /**
     * Keep the account's id where the next call will find it.
     *
     * Idempotent: asking twice for the same id stores once. The id is kept the
     * moment the account exists and again when the account is handed back, and
     * only one of those is ever a change.
     */
    private function rememberAccountId(string $id): void
    {
        if (($this->options[self::ACCOUNT_ID] ?? null) === $id) {
            return;
        }

        $this->options[self::ACCOUNT_ID] = $id;

        if ($this->hostStoresOptions()) {
            $this->save_options();
        }
        // WISECP 3 writes what create() returns onto the service itself, so the
        // id travels in the return value instead.
    }

    /** Idempotent for the same reason as rememberAccountId(). */
    private function rememberPassword(string $password): void
    {
        if ($this->lastStoredPassword === $password) {
            return;
        }

        $this->lastStoredPassword = $password;

        if ($this->hostStoresPassword()) {
            $this->save_login_password($password);
        }
    }

    /**
     * A secret on its way into storage.
     *
     * WISECP 5 offers encode_str() and says plainly never to store a panel
     * password in clear, so where that exists it is used. WISECP 3 encrypts
     * what create() returns itself.
     */
    private function sealed(string $secret): string
    {
        return method_exists($this, 'encode_str') ? (string) $this->encode_str($secret) : $secret;
    }

    /**
     * The buyer's answers, from wherever this host keeps them.
     *
     * @return array<string,mixed>
     */
    private function orderOptions(): array
    {
        $order = $this->hostArray('order');
        $fromOrder = is_array($order['options'] ?? null) ? $order['options'] : [];
        $array = static fn (mixed $value): array => is_array($value) ? $value : [];

        // WISECP 5 resolves the package's answers onto $this->options. WISECP 3
        // keeps them one level down: what config_options() saved becomes the
        // product's module_data and reaches the order as creation_info (which
        // is how WISECP's own cPanel module reads its plan). All of it is read,
        // the most explicit first.
        return $this->options
            + $fromOrder
            + $array($fromOrder['creation_info'] ?? $this->options['creation_info'] ?? null)
            + $array($fromOrder['module_data'] ?? $this->options['module_data'] ?? null);
    }

    /**
     * A field of the service, under either generation's spelling.
     */
    private function serviceValue(string ...$keys): string
    {
        $service = $this->hostArray('service');
        $order = $this->hostArray('order');
        // WISECP 3 keeps the service's own record on $this->config.
        $config = $this->hostArray('config');
        $options = $this->orderOptions();

        foreach ($keys as $key) {
            foreach ([$service[$key] ?? null, $order[$key] ?? null, $config[$key] ?? null, $options[$key] ?? null] as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    // -------------------------------------------------------------- the work

    /** Does the key, the secret and the clock add up? */
    private function connect(): bool
    {
        $this->api->get('/v1/plans');

        return true;
    }

    /** @return array<string,string> plan id => name */
    private function planChoices(): array
    {
        $choices = [];

        foreach ($this->api->get('/v1/plans') as $plan) {
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

    /**
     * Open the account and its domain.
     *
     * @param array<string,mixed> $given what the caller was handed, if anything
     * @return array<string,mixed>
     */
    private function openAccount(string $domain = '', array $given = []): array
    {
        $options = $given + $this->orderOptions();
        $domain = $this->domain($domain, $options);
        $username = $this->username($domain, $options);
        $password = $this->password($options);

        $existing = $this->findAccount($username);
        if ($existing !== null) {
            // A retry after a half-finished order. Handing back what the
            // service needs is only half of it: the order may have failed
            // exactly because the domain was refused, so the domain is seen to
            // as well, or the retry would report success on a service with no
            // website.
            $id = (string) $existing['id'];
            $username = (string) ($existing['username'] ?? $username);

            $this->rememberAccountId($id);
            $this->addDomain($id, $domain, $username);

            return $this->accountResult($id, $username, $password, $domain);
        }

        $body = [
            'username' => $username,
            'email' => $this->email($domain, $options),
            'password' => $password,
            'full_name' => $this->fullName($domain),
            'role' => $this->role($options),
            'plan_id' => $this->planId($options),
            'auto_username' => true,
        ];

        try {
            $account = $this->api->post('/v1/accounts', $body);
        } catch (Exception $e) {
            // The panel keeps one account per address. A customer's second
            // order carries the same address as their first, so without this
            // the second service cannot be provisioned at all - measured on a
            // live panel: "Failed to create - apiErrors.users.emailAlreadyExists"
            // for an order that had been paid for.
            $tagged = $this->taggedEmail($body['email'], $e);

            if ($tagged === null) {
                throw $e;
            }

            $body['email'] = $tagged;
            $account = $this->api->post('/v1/accounts', $body);
        }

        $id = (string) ($account['id'] ?? '');
        if ($id === '') {
            throw new Exception($this->text('no_account_returned'));
        }

        // auto_username: the panel may have made the name free and readable.
        $username = (string) ($account['username'] ?? $username);

        // Stored here, before the domain, and not at the end of this method.
        // The account exists from this line on, and anything that fails after
        // it must still leave something to act on: measured on a live panel,
        // a domain the panel refused meant create() reported a failure, the id
        // never reached WISECP, and the cancellation that followed found
        // nothing to delete and reported success - an account left open on the
        // panel that nothing in WISECP could reach again.
        $this->rememberAccountId($id);
        $this->rememberPassword($password);

        $this->addDomain($id, $domain, $username);

        return $this->accountResult($id, $username, $password, $domain);
    }

    /**
     * The account's domain, added unless it is already there.
     *
     * Asking first is what makes a retry safe: the account may already have the
     * domain from an attempt that failed later on, and creating it twice would
     * turn a recoverable order into one that can never finish.
     */
    private function addDomain(string $id, string $domain, string $username): void
    {
        if ($domain === '') {
            return;
        }

        try {
            foreach ($this->api->get('/v1/accounts/' . $id . '/domains') as $existing) {
                if (is_array($existing) && strcasecmp((string) ($existing['name'] ?? $existing['domain_name'] ?? ''), $domain) === 0) {
                    return;
                }
            }
        } catch (Exception) {
            // Not knowing is not a reason to stop; the create below will say
            // if the domain is already there.
        }

        try {
            // "name", which is what the endpoint calls it. "domain_name" was
            // refused with "Field validation for 'Name' failed on the required
            // tag", and no faked test could have known that.
            $this->api->post('/v1/domains', ['user_id' => $id, 'name' => $domain]);
        } catch (Exception $e) {
            // The account stays: a second attempt finds it by its username and
            // comes back here rather than opening another one.
            $this->logCall('create', ['account' => $id, 'domain' => $domain], ['error' => $e->getMessage()]);

            throw new Exception($this->text('domain_failed', ['domain' => $domain, 'username' => $username, 'error' => $e->getMessage()]));
        }
    }

    private function suspendAccount(): bool
    {
        $reason = $this->serviceValue('suspended_reason', 'suspend_reason');
        $this->api->post('/v1/accounts/' . $this->accountId() . '/suspend', ['reason' => $reason !== '' ? $reason : 'Suspended from WISECP']);

        return true;
    }

    private function unsuspendAccount(): bool
    {
        $this->api->post('/v1/accounts/' . $this->accountId() . '/unsuspend');

        return true;
    }

    /** An account already gone counts as deleted, so a repeat does not stick. */
    private function deleteAccount(): bool
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

    private function setPassword(string $password): bool
    {
        $password = trim($password);
        if ($password === '') {
            throw new Exception($this->text('password_required'));
        }

        // "new_password", which is what the endpoint requires; "password" was
        // refused with "Invalid Request Body", measured on a live panel.
        $this->api->post('/v1/accounts/' . $this->accountId() . '/change-password', ['new_password' => $password]);
        $this->rememberPassword($password);

        return true;
    }

    /**
     * Move the account to the plan the new package carries.
     *
     * @param array<string,mixed> $product the package moved to, as the host passes it
     */
    private function movePlan(array $product = []): bool
    {
        $module = [];
        foreach (['module_data', 'options', 'module_options'] as $key) {
            if (is_array($product[$key] ?? null)) {
                $module += $product[$key];
            }
        }

        // A package without a plan is a configuration mistake, not something
        // to do quietly: setPlan() refuses an empty one.
        $this->setPlan((string) ($module['plan_id'] ?? $product['plan_id'] ?? ''));

        return true;
    }

    /**
     * The account's plan, set to this one.
     *
     * Returns false when it is already that plan: an operator who saves the
     * service page without touching the plan must not send a plan change to
     * the panel, because on a plan with different limits that is a real
     * operation, not a no-op.
     */
    private function setPlan(string $planId, string $current = ''): bool
    {
        $planId = trim($planId);

        if ($planId === '') {
            throw new Exception($this->text('plan_required'));
        }

        if ($planId === trim($current)) {
            return false;
        }

        $this->api->patch('/v1/accounts/' . $this->accountId(), ['plan_id' => $planId]);

        return true;
    }

    /** @return array<string,mixed> */
    private function accountSnapshot(): array
    {
        return $this->api->get('/v1/accounts/' . $this->accountId());
    }

    /**
     * Disk and traffic in megabytes, each left out when the panel cannot answer
     * for it.
     *
     * The two readings come from different places, and each endpoint's own
     * names are used rather than a guess at a common pattern:
     *
     *   disk      GET /v1/accounts/{id}/disk-usage   used_mb, quota_mb
     *   traffic   GET /v1/bandwidth/accounts/{id}    bytes_used, bytes_total
     *
     * The traffic endpoint reports what was counted this month but leaves the
     * ceiling at zero, so the ceiling is read from the account's quota, where
     * it is kept as bytes per month.
     *
     * @return array<string,array{used:float,limit:float}>
     */
    private function usage(): array
    {
        $id = $this->accountId(required: false);
        if ($id === '') {
            return [];
        }

        $out = [];

        try {
            $disk = $this->api->get('/v1/accounts/' . $id . '/disk-usage');
            $out['disk'] = [
                'used' => (float) ($disk['used_mb'] ?? 0),
                'limit' => (float) ($disk['quota_mb'] ?? 0),
            ];
        } catch (Exception) {
            // A gauge the panel cannot answer is left off the page rather than
            // taking the page down with it.
        }

        try {
            $traffic = $this->api->get('/v1/bandwidth/accounts/' . $id);
            $out['bandwidth'] = [
                'used' => (float) ($traffic['bytes_used'] ?? 0) / 1048576,
                'limit' => (float) ($traffic['bytes_total'] ?? 0) / 1048576,
            ];

            if ($out['bandwidth']['limit'] <= 0) {
                $out['bandwidth']['limit'] = $this->trafficCeiling($id);
            }
        } catch (Exception) {
        }

        return $out;
    }

    /**
     * The account's monthly traffic allowance in megabytes, or zero when it has
     * none or cannot be read. Never fatal: a missing ceiling only means the
     * gauge shows no limit.
     */
    private function trafficCeiling(string $id): float
    {
        try {
            $quota = $this->api->get('/v1/resource-quota/users/' . $id);
        } catch (Exception) {
            return 0.0;
        }

        return (float) ($quota['bandwidth_quota_bytes'] ?? 0) / 1048576;
    }

    // ------------------------------------------------------------- the pieces

    /**
     * The account's id on the panel.
     *
     * WISECP 5 lets the module keep it on the service, so it is read straight
     * back. WISECP 3 gives the module nowhere to write - what create() returns
     * becomes the username and nothing else - so there the id is resolved from
     * the username the service carries, the way every WISECP 3 module addresses
     * its account. The lookup is also what heals a WISECP 5 service whose
     * stored id was lost.
     */
    private function accountId(bool $required = true): string
    {
        $id = trim((string) ($this->orderOptions()[self::ACCOUNT_ID] ?? ''));

        if ($id === '' && $this->resolvedAccountId !== null) {
            $id = $this->resolvedAccountId;
        }

        if ($id === '') {
            $username = $this->serviceUsername();

            if ($username !== '') {
                $account = $this->findAccount($username);
                $id = trim((string) ($account['id'] ?? ''));

                // Remembered for the rest of this call, and written back where
                // the host can keep it.
                if ($id !== '') {
                    $this->resolvedAccountId = $id;
                    if ($this->hostStoresOptions()) {
                        $this->rememberAccountId($id);
                    }
                }
            }
        }

        if ($id === '' && $required) {
            throw new Exception($this->text('no_account_id'));
        }

        return $id;
    }

    /** The account's username as the service carries it, under either spelling. */
    private function serviceUsername(): string
    {
        return $this->serviceValue('user', 'username');
    }

    /** @return array<string,mixed> */
    private function accountResult(string $id, string $username, string $password, string $domain): array
    {
        $this->rememberAccountId($id);
        $this->rememberPassword($password);

        $host = $this->panelHost();

        return [
            'username' => $username,
            'password' => $password,
            self::ACCOUNT_ID => $id,
            'domain' => $domain,
            'ftp_info' => [
                'ip' => (string) ($this->hostArray('server')['ip'] ?? ''),
                'host' => $host !== '' ? $host : (string) ($this->hostArray('server')['ip'] ?? ''),
                'username' => $username,
                'password' => $password,
                'port' => 21,
            ],
        ];
    }

    /**
     * The address to try again with when the panel refused the customer's own
     * because another account already has it - or null when that is not why it
     * refused, or there is nothing to tag the address with.
     *
     * buyer@example.com on service 77 becomes buyer+wisecp77@example.com, which
     * still delivers to the same inbox. A tag already on the address is
     * replaced rather than stacked.
     */
    private function taggedEmail(string $email, Exception $refusal): ?string
    {
        $service = (int) $this->serviceValue('id');

        if ($service <= 0 || ! str_contains($email, '@')) {
            return null;
        }

        $taken = stripos($refusal->getMessage(), 'emailAlreadyExists') !== false;

        if (! $taken) {
            // Should the panel answer with a translated sentence one day, the
            // key is gone from the reply, so the account list is asked instead.
            // This runs only after a refusal, never on the ordinary path.
            foreach ($this->accountList() as $account) {
                if (strcasecmp((string) ($account['email'] ?? ''), $email) === 0) {
                    $taken = true;
                    break;
                }
            }
        }

        if (! $taken) {
            return null;
        }

        $at = strrpos($email, '@');
        $local = substr($email, 0, $at);
        $plus = strpos($local, '+');

        if ($plus !== false) {
            $local = substr($local, 0, $plus);
        }

        return $local . '+wisecp' . $service . substr($email, $at);
    }

    /** @return array<int,array<string,mixed>> */
    private function accountList(): array
    {
        try {
            $accounts = $this->api->get('/v1/accounts');
        } catch (Exception) {
            return [];
        }

        return array_values(array_filter($accounts, 'is_array'));
    }

    /** @return array<string,mixed>|null */
    private function findAccount(string $username): ?array
    {
        if ($username === '') {
            return null;
        }

        // A convenience; its failure must not stop an order.
        foreach ($this->accountList() as $account) {
            if (strcasecmp((string) ($account['username'] ?? ''), $username) === 0) {
                return $account;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $options */
    private function domain(string $given, array $options): string
    {
        foreach ([$given, $options['domain'] ?? '', $this->serviceValue('domain')] as $value) {
            $domain = strtolower(trim((string) $value));
            if ($domain !== '') {
                return $domain;
            }
        }

        return '';
    }

    /** @param array<string,mixed> $options */
    private function username(string $domain, array $options): string
    {
        $given = trim((string) ($options['username'] ?? ''));
        if ($given !== '') {
            return $given;
        }

        // Each generation ships its own generator - generate_username() on
        // WISECP 3, the static username_generator() on WISECP 5 - and where one
        // exists it decides, so every account on an install is named the same
        // way whichever module opened it.
        foreach (['generate_username', 'username_generator'] as $generator) {
            if (! method_exists($this, $generator)) {
                continue;
            }

            $generated = trim((string) $this->{$generator}($domain));

            if ($generated !== '') {
                return str_replace('-', '', $generated);
            }
        }

        $base = preg_replace('/[^a-z0-9]/', '', strtolower($domain)) ?? '';
        if ($base === '' || ! ctype_alpha($base[0])) {
            $base = 'pnl' . $base;
        }

        return substr($base, 0, 16);
    }

    /** @param array<string,mixed> $options */
    private function password(array $options): string
    {
        $given = trim((string) ($options['password'] ?? ''));
        if ($given !== '') {
            return $given;
        }

        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%*-_';
        $password = '';
        for ($i = 0; $i < 16; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /** @param array<string,mixed> $options */
    private function email(string $domain, array $options): string
    {
        foreach ([$options['email'] ?? '', $this->serviceValue('user_email', 'email', 'client_email')] as $value) {
            $email = trim((string) $value);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return $email;
            }
        }

        return $domain !== '' ? 'hostmaster@' . $domain : 'hostmaster@localhost';
    }

    private function fullName(string $domain): string
    {
        $name = $this->serviceValue('user_name', 'client_name', 'name');

        return $name !== '' ? $name : $domain;
    }

    /** @param array<string,mixed> $options */
    private function planId(array $options): string
    {
        $plan = trim((string) ($options['plan_id'] ?? ''));

        if ($plan === '') {
            throw new Exception($this->text('plan_required'));
        }

        return $plan;
    }

    /** @param array<string,mixed> $options */
    private function role(array $options): string
    {
        $role = strtoupper(trim((string) ($options['account_role'] ?? 'USER')));

        return in_array($role, ['USER', 'RESELLER'], true) ? $role : 'USER';
    }

    private function panelHost(): string
    {
        $server = $this->hostArray('server');

        foreach (['hostname', 'name', 'ip'] as $key) {
            $host = trim((string) ($server[$key] ?? ''));
            if ($host !== '') {
                return preg_replace('#^https?://#i', '', $host) ?? $host;
            }
        }

        return '';
    }

    private function panelPort(): int
    {
        $port = (int) ($this->hostArray('server')['port'] ?? 8443);

        return $port > 0 && $port < 65536 ? $port : 8443;
    }

    private function panelUrl(): string
    {
        $host = $this->panelHost();

        return $host === '' ? '' : 'https://' . $host . ':' . $this->panelPort();
    }

    private function nameservers(): string
    {
        $ns = [];
        $server = $this->hostArray('server');

        foreach (['ns1', 'ns2', 'ns3', 'ns4'] as $key) {
            $value = trim((string) ($server[$key] ?? ''));
            if ($value !== '') {
                $ns[] = $value;
            }
        }

        return implode(', ', $ns);
    }

    /**
     * A string from lang/, with :placeholders filled.
     *
     * @param array<string,string> $replace
     */
    private function text(string $key, array $replace = []): string
    {
        $text = (string) ($this->hostArray('lang')[$key] ?? '');

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, $value, $text);
        }

        return $text;
    }

    /** Megabytes as WISECP 3 prints them: a number, or ∞ for no limit. */
    private function formatMb(float $value): string
    {
        return $value > 0 ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' MB' : '0 MB';
    }
}
