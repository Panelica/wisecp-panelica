<?php

declare(strict_types=1);

/*
 * Two namespace blocks, because the two generations disagree about what the
 * class is called and a module file is loaded by name.
 *
 * Measured on a live WISECP 3.1.9.8.2 install: every one of its eighteen stock
 * server modules declares "class <Folder>_Module" in the global namespace, and
 * not one of them uses a namespace at all. WISECP 5 is the other way round -
 * namespace WISECP\Modules\Servers, class <Folder>. A file that is only one of
 * those is a module the other host cannot find, and a class the core cannot
 * find produces no error anywhere: nothing is called.
 *
 * So the work lives in the namespaced class and the global name is a two-line
 * subclass of it. Both hosts load the same code.
 */

namespace WISECP\Modules\Servers {

use Exception;
use ServerModule;

// The core includes <Folder>.php and nothing else in the directory, so the file
// pulls in its own parts. Without this the trait below is undefined and the
// module dies the moment WISECP loads it.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'PanelicaApi.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'PanelicaCore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ApiClient.php';

/*
 * Panelica hosting accounts, driven from WISECP.
 *
 * WISECP asks for its work by calling a method by name, and it only ever calls
 * a name its own version knows. There are two such vocabularies in the field:
 *
 *   job                WISECP 3                 WISECP 5
 *   test the server    testConnect()            test_connect()
 *   the package form   config_options()         product_configuration()
 *   delete             terminate()              cancel()
 *   move plan          apply_updowngrade()      upgrade()
 *   what the customer  clientArea() + pages/    dashboard_data()
 *     sees
 *   usage              getDisk()/getBandwidth() metrics_usage()
 *
 * A module that carries only one vocabulary is silent on the other host: the
 * client area shows nothing, deleting in WISECP leaves the account standing on
 * the panel, and no error is raised anywhere, because nothing was called. So
 * both vocabularies are here, each a few lines, and both hand the work to
 * PanelicaCore - which is written once and tested once, on both hosts.
 *
 * The generations also disagree on how a failure is reported, and this is not
 * cosmetic: WISECP 3 reads $this->error and a false return, WISECP 5 catches
 * the exception. So every WISECP 3 verb catches and converts; every WISECP 5
 * verb lets it through.
 */
class Panelica extends ServerModule
{
    use PanelicaCore;

    /** Where the account's id on the panel is kept between calls. */
    private const ACCOUNT_ID = 'panelica_account_id';

    /**
     * @param array<string,mixed>|int $server
     * @param array<string,mixed> $options
     */
    public function __construct($server = [], $options = [])
    {
        $this->_name = 'Panelica';
        parent::__construct($server, $options);
    }

    /**
     * Called once $this->server is filled and its secrets decoded.
     *
     * @param array<string,mixed> $server
     */
    protected function define_server_info($server = []): void
    {
        $this->api = new ApiClient($server, function (string $path, array $request, array $response): void {
            $this->logCall('api', ['path' => $path] + $request, $response);
        });
    }

    // ======================================================== both generations

    /**
     * Open the account and its domain.
     *
     * WISECP 5 calls this with no arguments and reads the service itself;
     * WISECP 3 passes the domain and the buyer's answers. One signature serves
     * both, and what is returned is what WISECP 3 documents: the username, the
     * password and the FTP block.
     *
     * @param array<string,mixed> $order_options
     * @return array<string,mixed>|false
     */
    public function create($domain = '', $order_options = [])
    {
        $given = is_array($order_options) ? $order_options : [];

        try {
            $account = $this->openAccount(is_string($domain) ? $domain : '', $given);
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }

        if (! $this->hostStoresOptions()) {
            // WISECP 3 stores exactly these three keys and drops the rest, so
            // the id is not kept here. It is resolved from the username on each
            // later call instead (see accountId()).
            return [
                'username' => $account['username'],
                'password' => $account['password'],
                'ftp_info' => $account['ftp_info'],
            ];
        }

        // WISECP 5 reads every key of this array as an instruction: "config"
        // and "login" are merged under those names, anything else is written to
        // the option root. The panel password goes in sealed, because its own
        // documentation says never to store one in clear.
        return [
            'config' => [
                'user' => $account['username'],
                'password' => $this->sealed($account['password']),
                'id' => $account[self::ACCOUNT_ID],
            ],
            // "login" is what the client area shows the customer, so it is
            // the readable one; sealing it would print the ciphertext as their
            // password. "config" is the stored identity, which the host's own
            // documentation says to encrypt.
            'login' => [
                'username' => $account['username'],
                'password' => $account['password'],
                'url' => $this->panelUrl(),
            ],
            self::ACCOUNT_ID => $account[self::ACCOUNT_ID],
            'ftp_info' => $account['ftp_info'],
        ];
    }

    /**
     * Nothing has to happen on the panel when a renewal is paid: the account is
     * already open and its plan has not changed.
     *
     * It is still declared, because a name the core cannot find is not a
     * no-op - the queue records "module method not found" and the renewal is
     * marked failed.
     */
    public function renew(): bool
    {
        return true;
    }

    /** @return bool */
    public function suspend()
    {
        try {
            return $this->suspendAccount();
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    /** @return bool */
    public function unsuspend()
    {
        try {
            return $this->unsuspendAccount();
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    /** @return bool */
    public function change_password($password = '')
    {
        try {
            return $this->setPassword(is_string($password) ? $password : '');
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    // ============================================================= WISECP 5

    /** Which cards and buttons the service page offers. */
    public function configure_features(): void
    {
        $this->dashboard_cache = false;
    }

    public function test_connect(): bool
    {
        return $this->connect();
    }

    /**
     * The product form: which Panelica plan a package hands out.
     *
     * @param array<string,mixed> $data
     * @return array<string,array<string,mixed>>
     */
    public function product_configuration(array $data = []): array
    {
        return $this->packageFields($data, 'col-lg-6');
    }

    /**
     * The package form, saved.
     *
     * This host passes the values by reference and expects nothing back, so the
     * normalised values are written into $values in place. Returning them
     * instead would silently discard the normalisation.
     *
     * @param array<string,mixed> $values
     */
    public function save_product_configuration(array &$values): void
    {
        $values = $this->checkedPackage($values) + $values;
    }

    public function cancel(): bool
    {
        // No catch: on this host the exception is what the caller wants.
        return $this->deleteAccount();
    }

    /**
     * @param array<string,mixed> $new_product
     */
    public function upgrade(array $new_product = []): bool
    {
        return $this->movePlan($new_product);
    }

    /**
     * The service's own form in the admin panel: which plan this one account is
     * on, whatever its package says.
     *
     * @return array<string,array<string,mixed>>
     */
    public function service_configuration(): array
    {
        return $this->serviceFields('col-lg-6');
    }

    /**
     * The service form, saved, and the change carried to the panel.
     *
     * Passed by reference and returning nothing, like the product form.
     *
     * @param array<string,mixed> $values
     */
    public function save_service_configuration(array &$values): void
    {
        $plan = trim((string) ($values['plan_id'] ?? ''));

        // The panel is changed first. If it refuses, the exception stops the
        // save, so WISECP does not end up recording a plan the account is not
        // on - which is the state that makes an invoice and an account
        // disagree with nobody noticing.
        $this->setPlan($plan, (string) ($this->orderOptions()['plan_id'] ?? ''));

        $values['plan_id'] = $plan;
    }

    /**
     * What the customer sees on the service page.
     *
     * @return array<string,mixed>
     */
    public function dashboard_data(): array
    {
        $facts = $this->accountFacts();

        return [
            'gauges' => array_filter([
                'disk' => $this->gaugeItem($facts['usage']['disk'] ?? null),
                'bandwidth' => $this->gaugeItem($facts['usage']['bandwidth'] ?? null),
            ]),
            'server_info' => array_filter([
                'domain' => $this->item($facts['domain'], copyable: true),
                'username' => $this->item($facts['username'], copyable: true),
                'status' => $this->statusItem($facts['status']),
                'plan' => $this->item($facts['plan']),
                'panel' => $this->item($facts['panel'], link: true),
                'nameservers' => $this->item($facts['nameservers']),
            ]),
        ];
    }

    /**
     * One gauge, in this host's shape: raw numbers and a unit, with the word
     * "unlimited" where there is no ceiling. The theme works out the ratio and
     * the colour itself, so no percentage is sent.
     *
     * @param array{used:float,limit:float}|null $usage
     * @return array<string,mixed>
     */
    private function gaugeItem(?array $usage): array
    {
        if ($usage === null) {
            return [];
        }

        return [
            'used' => round($usage['used'], 2),
            'total' => $usage['limit'] > 0 ? round($usage['limit'], 2) : 'unlimited',
            'unit' => 'MB',
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

    /**
     * The panel, opened as the account's own user.
     *
     * This host asks for the URL and sends the customer itself.
     *
     * There is deliberately no sso_root_panel_login() beside this: Panelica's
     * External API offers a one-time login for an account and nothing for the
     * panel's own operator, and a declared verb with no endpoint behind it is a
     * button that fails in front of whoever presses it. The operator opens the
     * panel and signs in.
     */
    public function sso_panel_login(): string
    {
        return $this->panelLoginUrl('/v1/accounts/' . $this->accountId() . '/sso-login');
    }

    // ============================================================= WISECP 3

    /** @return bool */
    public function testConnect()
    {
        try {
            return $this->connect();
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,array<string,mixed>>
     */
    public function config_options($data = [])
    {
        return $this->packageFields(is_array($data) ? $data : [], 100);
    }

    /** @return bool */
    public function terminate()
    {
        try {
            return $this->deleteAccount();
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    /**
     * @param array<string,mixed> $orderopt
     * @param array<string,mixed> $product
     * @return bool
     */
    public function apply_updowngrade($orderopt = [], $product = [])
    {
        try {
            return $this->movePlan(is_array($product) ? $product : []);
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }
    }

    /** @return array<string,mixed>|false */
    public function getDisk()
    {
        return $this->gauge('disk', 'MB', 1.0);
    }

    /** @return array<string,mixed>|false */
    public function getBandwidth()
    {
        // The panel counts traffic in megabytes; WISECP 3 labels this gauge in
        // gigabytes, so the number is converted with the label.
        return $this->gauge('bandwidth', 'GB', 1 / 1024);
    }

    /**
     * The accounts on this server, for the server's account list.
     *
     * @return array<int,array<string,mixed>>|false
     */
    public function listAccounts()
    {
        try {
            $accounts = $this->api()->get('/v1/accounts');
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }

        $rows = [];

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $rows[] = [
                'cdate' => substr((string) ($account['created_at'] ?? ''), 0, 19) ?: '',
                'domain' => (string) ($account['primary_domain'] ?? $account['domain'] ?? ''),
                'username' => (string) ($account['username'] ?? ''),
                'plan' => (string) ($account['plan']['name'] ?? $account['plan_name'] ?? ''),
                'suspended' => in_array(strtolower((string) ($account['status'] ?? '')), ['suspended', 'inactive'], true),
            ];
        }

        return $rows;
    }

    /**
     * The service's own fields in the admin panel.
     *
     * @return array<string,array<string,mixed>>
     */
    public function adminArea_service_fields()
    {
        return $this->serviceFields(100);
    }

    /**
     * The service fields, saved, and the change carried to the panel.
     *
     * This host hands over both the old and the new values and expects the
     * four bags back, or false with the reason on $this->error.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>|false
     */
    public function save_adminArea_service_fields($data = [])
    {
        $data = is_array($data) ? $data : [];
        $old = is_array($data['old'] ?? null) ? $data['old'] : [];
        $new = is_array($data['new'] ?? null) ? $data['new'] : [];
        $array = static fn (mixed $value): array => is_array($value) ? $value : [];

        $before = $array($old['creation_info'] ?? null);
        $after = $array($new['creation_info'] ?? null);

        try {
            // The panel first: a refusal must stop the save, or WISECP records
            // a plan the account is not on and nothing says so.
            $this->setPlan((string) ($after['plan_id'] ?? ''), (string) ($before['plan_id'] ?? ''));
        } catch (Exception $e) {
            return $this->failed(__FUNCTION__, $e);
        }

        return [
            'creation_info' => $after,
            'config' => $array($new['config'] ?? null),
            'ftp_info' => $array($new['ftp_info'] ?? null),
            'options' => $array($new['options'] ?? null),
        ];
    }

    /**
     * What the customer sees on the service page, WISECP 3's way: the buttons,
     * then the page under pages/.
     *
     * @return string
     */
    public function clientArea()
    {
        $content = $this->clientArea_buttons_output();
        $page = $this->currentPage();

        if ($page === '') {
            $page = 'home';
        }

        try {
            $vars = $this->accountFacts() + ['error' => ''];
        } catch (Exception $e) {
            // The page still renders; it says what went wrong instead of
            // leaving the customer an empty panel.
            $this->logCall(__FUNCTION__, [], ['error' => $e->getMessage()]);
            $vars = $this->emptyFacts() + ['error' => $e->getMessage()];
        }

        return $content . $this->get_page('clientArea-' . $page, $vars);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function clientArea_buttons()
    {
        $page = $this->currentPage();

        if ($page !== '' && $page !== 'home') {
            // "turn-back" is WISECP's own string, already in the operator's
            // language; ours is only the fallback.
            $back = (string) ($this->hostArray('lang')['turn-back'] ?? '');

            return ['home' => ['text' => $back !== '' ? $back : $this->text('back'), 'type' => 'page-loader']];
        }

        return [
            'SingleSignOn' => ['text' => $this->text('open_panel'), 'type' => 'function', 'target_blank' => true],
            'webMail' => ['text' => $this->text('open_webmail'), 'type' => 'function', 'target_blank' => true],
        ];
    }

    /** The panel, opened as the account's own user. */
    public function use_clientArea_SingleSignOn()
    {
        return $this->openPanel('/v1/accounts/' . $this->accountId() . '/sso-login');
    }

    /** Webmail, which needs no panel session. */
    public function use_clientArea_webMail()
    {
        $domain = $this->domain('', $this->orderOptions());
        $url = $domain !== '' ? 'https://webmail.' . $domain : rtrim($this->panelUrl(), '/') . '/webmail';

        return $this->redirect($url);
    }

    /** The same panel, opened from the admin side. */
    public function use_adminArea_SingleSignOn()
    {
        return $this->openPanel('/v1/accounts/' . $this->accountId() . '/sso-login');
    }

    /** Which client-area page WISECP 3 is asking for. */
    private function currentPage(): string
    {
        if (! property_exists($this, 'page')) {
            return '';
        }

        return is_scalar($this->page) ? trim((string) $this->page) : '';
    }

    // ---------------------------------------------------------- presentation

    /**
     * The package form, in the generation's own measurement for widths.
     *
     * @param array<string,mixed> $data
     * @param string|int $width
     * @return array<string,array<string,mixed>>
     */
    private function packageFields(array $data, $width): array
    {
        try {
            $plans = $this->planChoices();
            $unavailable = null;
        } catch (\Throwable $e) {
            // Throwable, not Exception: this runs before a server is chosen,
            // and what came back from an uninitialised client was an Error.
            // Letting one escape here is what left the form with no plan
            // selector at all.
            $plans = null;
            $unavailable = $this->text('plans_unavailable', ['error' => $e->getMessage()]);
        }

        // A plan list the panel cannot answer for leaves a text box, so a
        // package can still be configured by hand instead of not at all.
        $plan = $plans === null
            ? ['name' => $this->text('setting_plan'), 'description' => $unavailable, 'type' => 'text', 'value' => (string) ($data['plan_id'] ?? ''), 'wrap_width' => $width]
            : ['name' => $this->text('setting_plan'), 'description' => $this->text('setting_plan_description'), 'type' => 'dropdown', 'options' => $plans, 'value' => (string) ($data['plan_id'] ?? ''), 'wrap_width' => $width];

        return [
            'plan_id' => $plan,
            'account_role' => [
                'name' => $this->text('setting_role'),
                'description' => $this->text('setting_role_description'),
                'type' => 'dropdown',
                'options' => ['USER' => $this->text('role_user'), 'RESELLER' => $this->text('role_reseller')],
                'value' => (string) ($data['account_role'] ?? 'USER'),
                'wrap_width' => $width,
            ],
        ];
    }

    /**
     * One service's own fields, in the generation's own measurement for widths.
     *
     * @param string|int $width
     * @return array<string,array<string,mixed>>
     */
    private function serviceFields($width): array
    {
        $options = $this->orderOptions();
        $fields = $this->packageFields($options, $width);

        // The package's role is decided when the account is opened; what an
        // operator changes here is the plan.
        unset($fields['account_role']);

        $fields['plan_id']['description'] = $this->text('service_plan_description');

        $id = (string) ($options[self::ACCOUNT_ID] ?? '');
        if ($id !== '') {
            $fields['panelica_account'] = [
                'name' => $this->text('service_account'),
                'type' => 'output',
                'value' => htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'wrap_width' => $width,
            ];
        }

        return $fields;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function checkedPackage(array $data): array
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
     * Everything both client areas show, read once.
     *
     * @return array<string,mixed>
     */
    private function accountFacts(): array
    {
        $account = $this->accountSnapshot();

        return [
            'domain' => $this->domain('', $this->orderOptions()),
            'username' => (string) ($account['username'] ?? $this->serviceUsername()),
            'status' => strtolower((string) ($account['status'] ?? 'active')) ?: 'active',
            'plan' => (string) ($account['plan']['name'] ?? $account['plan_name'] ?? ''),
            'panel' => $this->panelUrl(),
            'nameservers' => $this->nameservers(),
            'usage' => $this->usage(),
        ];
    }

    /** @return array<string,mixed> */
    private function emptyFacts(): array
    {
        return [
            'domain' => $this->domain('', $this->orderOptions()),
            'username' => $this->serviceUsername(),
            'status' => 'active',
            'plan' => '',
            'panel' => $this->panelUrl(),
            'nameservers' => $this->nameservers(),
            'usage' => [],
        ];
    }

    /**
     * One WISECP 3 gauge, in that generation's shape.
     *
     * @return array<string,mixed>|false
     */
    private function gauge(string $which, string $unit, float $factor)
    {
        try {
            $usage = $this->usage();
        } catch (Exception $e) {
            return $this->failed($which, $e);
        }

        if (! isset($usage[$which])) {
            // Nothing to show is not an error; WISECP 3 reads false as "this
            // gauge has no value".
            return false;
        }

        $used = $usage[$which]['used'] * $factor;
        $limit = $usage[$which]['limit'] * $factor;
        $percent = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0;

        return [
            'limit' => $limit > 0 ? $limit : 0,
            'used' => $used > 0 ? $used : 0,
            'used-percent' => $percent,
            'format-limit' => $limit > 0 ? $this->formatNumber($limit) . ' ' . $unit : '∞',
            'format-used' => $used > 0 ? $this->formatNumber($used) . ' ' . $unit : '0 ' . $unit,
        ];
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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

    // --------------------------------------------------------------- plumbing

    /**
     * A one-time link to the panel, followed.
     *
     * @return bool
     */
    private function openPanel(string $path)
    {
        try {
            $answer = $this->api()->post($path);
        } catch (Exception $e) {
            // A WISECP 3 button answers the browser itself, so the reason is
            // printed for the customer rather than thrown at a caller that is
            // not listening.
            $this->error = $e->getMessage();
            $this->logCall(__FUNCTION__, ['path' => $path], ['error' => $e->getMessage()]);
            echo $this->text('panel_unavailable', ['error' => $e->getMessage()]);

            return false;
        }

        $url = $this->urlFrom($answer);

        if ($url === '') {
            echo $this->text('panel_unavailable', ['error' => $this->text('no_sso_url')]);

            return false;
        }

        return $this->redirect($url);
    }

    /** A one-time link to the panel, as a URL for the caller to follow. */
    private function panelLoginUrl(string $path): string
    {
        $url = $this->urlFrom($this->api()->post($path));

        if ($url === '') {
            throw new Exception($this->text('no_sso_url'));
        }

        return $url;
    }

    /**
     * The link out of what the panel answered, however it phrased it.
     *
     * @param array<string,mixed> $answer
     */
    private function urlFrom(array $answer): string
    {
        $url = trim((string) ($answer['url'] ?? $answer['login_url'] ?? ''));

        if ($url !== '') {
            return $url;
        }

        $token = trim((string) ($answer['token'] ?? ''));

        return $token === '' ? '' : rtrim($this->panelUrl(), '/') . '/sso?token=' . urlencode($token);
    }

    /** @return bool */
    private function redirect(string $url)
    {
        if (class_exists('\Utility') && method_exists('\Utility', 'redirect')) {
            \Utility::redirect($url);
        } else {
            header('Location: ' . $url);
        }

        return true;
    }

    /**
     * A failure, reported the way this host reports one.
     *
     * The two generations do not agree, and the difference is not cosmetic.
     * WISECP 5 expects the exception and shows its message; its own
     * documentation calls assigning to $this->error "a leftover of the previous
     * generation ... throw a translated exception and the caller surfaces the
     * message". WISECP 3 is the other way round: its caller does not catch, so
     * an exception thrown there reaches the customer as a blank page - which is
     * why every WISECP 3 module, including WISECP's own samples, catches inside
     * the module and returns false with the message on $this->error.
     *
     * So the message is always written where WISECP 3 reads it, always logged,
     * and then on a WISECP 5 host thrown on to the caller that is waiting for
     * it. The verbs above need no branch of their own.
     *
     * @return false
     */
    private function failed(string $action, Exception $e): bool
    {
        $this->error = $e->getMessage();
        $this->logCall($action, ['service' => $this->serviceUsername()], ['error' => $e->getMessage()]);

        if ($this->hostStoresOptions()) {
            throw $e;
        }

        return false;
    }
}
}

namespace {
    /*
     * The name WISECP 3 loads. Measured on a live 3.1.9.8.2 install: the core
     * includes modules/Servers/<Folder>/<Folder>.php and looks for the class
     * <Folder>_Module in the global namespace. Thirteen of its stock modules
     * declare exactly that and extend ServerModule; five extend nothing.
     *
     * There is no second implementation here on purpose. Everything is in the
     * class above, and this is the name the older core can see it by.
     */
    class Panelica_Module extends \WISECP\Modules\Servers\Panelica
    {
    }
}
