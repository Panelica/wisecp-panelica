<?php

declare(strict_types=1);

/*
 * WISECP 3, imitated.
 *
 * The older host, and a deliberately hostile one: it has none of the helpers
 * the newer base class offers. There is no save_options() and no
 * save_login_password(), so a module that calls them dies here with a fatal -
 * which is exactly what would happen on a customer's install, and why the
 * module asks before it calls. save_log() is static and takes six arguments
 * rather than three. The package options live under $this->order["options"] and
 * the service's own record under $this->config, not on $this->service, which
 * does not exist at all. A failure is read from $this->error and a false
 * return, never from an exception.
 *
 * Every line of this is measured, not assumed: it follows WISECP's own
 * sample-hosting-panel-module and its cPanel module, both read in full.
 */

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class ServerModule
{
    public string $_name = '';

    /** @var array<string,mixed> */
    public mixed $server = [];

    /** @var array<string,mixed> the order, with the package's answers under ["options"] */
    public array $order = [];

    /** @var array<string,mixed> the service's own record: ["user"] is its username */
    public array $config = [];

    /** @var array<string,mixed> */
    public array $options = [];

    /**
     * The buyer. Measured on a live 3.1.9.8.2 install: every stock module reads
     * the address, the name and the surname from here, and nowhere else.
     *
     * @var array<string,mixed>
     */
    public array $user = [];

    /** @var array<string,mixed> */
    public array $lang = [];

    public string $error = '';

    /** Which client-area page is being asked for; empty means the first one. */
    public mixed $page = '';

    public bool $force_setup = false;

    /** Which host this is, for the tests to branch on. */
    public int $generation = 3;

    /** @var list<array{action:string,request:string,response:string}> what the module logged (tests read this) */
    public static array $staticLogs = [];

    /**
     * @param array<string,mixed>|int $server
     * @param array<string,mixed> $options
     */
    public function __construct($server = [], $options = [])
    {
        $this->options = $options ?: [];
        $this->lang = include __DIR__ . '/../coremio/modules/Servers/Panelica/lang/en.php';
        // WISECP's own strings, which the module falls back on for the client area.
        $this->lang['turn-back'] = 'Turn back';

        if ($server) {
            $this->set_server($server);
        }

        // Note what is NOT here: WISECP 3 never calls configure_features().
    }

    /** @param array<string,mixed>|int $server */
    public function set_server(array|int $server = []): void
    {
        $this->server = is_array($server) ? $server : ['id' => $server];

        if (method_exists($this, 'define_server_info')) {
            $this->define_server_info($this->server);
        }
    }

    /**
     * Static, six arguments, and the module's own name passed in.
     *
     * @param array<string,mixed> $request
     */
    protected static function save_log(string $group = '', string $module = '', string $action = '', array $request = [], string $response = '', string $trace = ''): bool
    {
        self::$staticLogs[] = [
            'action' => $action,
            'request' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            'response' => $response,
        ];

        return true;
    }

    /** What the module logged, in the shape the tests read on both hosts. */
    public function logs(): array
    {
        return self::$staticLogs;
    }

    /**
     * Renders pages/<name>.php. The real one injects $module; so does this, and
     * it renders the page for real - a page that throws fails the test.
     *
     * @param array<string,mixed> $vars
     */
    public function get_page(string $page_file = '', array $vars = []): string
    {
        $file = __DIR__ . '/../coremio/modules/Servers/Panelica/pages/' . $page_file . '.php';

        if (! is_file($file)) {
            return '';
        }

        $module = $this;
        extract($vars, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    /** The buttons, rendered. The real one is HTML; the keys are what matters. */
    public function clientArea_buttons_output(): string
    {
        $out = '';

        foreach ($this->clientArea_buttons() as $key => $button) {
            $out .= '<a data-key="' . htmlspecialchars((string) $key, ENT_QUOTES) . '">'
                . htmlspecialchars((string) ($button['text'] ?? ''), ENT_QUOTES) . '</a>';
        }

        return $out;
    }

    /** WISECP 3's own username generator; where it exists, it decides. */
    public function generate_username(string $domain = ''): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($domain)) ?? '';

        return substr($base === '' ? 'user' : $base, 0, 8);
    }
}
