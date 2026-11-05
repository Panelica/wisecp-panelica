<?php

declare(strict_types=1);

/*
 * WISECP 5, imitated.
 *
 * The module is plain PHP that extends a class WISECP owns, so the tests stand
 * that class in and run the module with no WISECP, no database and no network.
 * What is here is only the surface the module actually touches; anything else
 * would be a guess about code we cannot see.
 *
 * This is the newer host: it resolves the package options onto $this->options,
 * keeps what the module stores (save_options, save_login_password) and reads a
 * failure from the exception. Its counterpart is stubs-v3.php, and the same
 * behaviour tests run on both - which is the only way "it works on both
 * generations" can be more than a claim.
 */

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class ServerModule
{
    public string $_name = '';

    /** @var array<string,mixed> */
    public mixed $server = [];

    /** @var array<string,mixed> */
    public array $service = [];

    /** @var array<string,mixed> */
    public array $options = [];

    /** @var array<string,mixed> */
    public array $lang = [];

    public string $error = '';

    /** Which host this is, for the tests to branch on. */
    public int $generation = 5;

    protected bool $dashboard_cache = true;

    /** @var list<array{action:string,request:string,response:string}> what the module logged (tests read this) */
    public array $logs = [];

    /** @var list<string> every password handed to save_login_password() (tests read this) */
    public array $savedPasswords = [];

    /** How many times the module asked for its options to be stored. */
    public int $savedOptions = 0;

    /**
     * @param array<string,mixed>|int $server
     * @param array<string,mixed> $options
     */
    public function __construct($server = [], $options = [])
    {
        $this->options = $options ?: [];
        // The module's own strings. PNL_MODULE_DIR lets the live harness point
        // this at an installed module, where the repository layout does not
        // exist; a missing file leaves the strings empty rather than assigning
        // include()'s false to an array property.
        $langFile = (getenv('PNL_MODULE_DIR') ?: __DIR__ . '/../coremio/modules/Servers/Panelica') . '/lang/en.php';
        $this->lang = is_file($langFile) ? (array) include $langFile : [];
        $this->generation = 5;

        if ($server) {
            $this->set_server($server);
        } elseif (method_exists($this, 'configure_features')) {
            $this->configure_features();
        }
    }

    /** @param array<string,mixed>|int $server */
    public function set_server(array|int $server = []): void
    {
        $this->server = is_array($server) ? $server : ['id' => $server];

        if (method_exists($this, 'define_server_info')) {
            $this->define_server_info($this->server);
        }

        if (method_exists($this, 'configure_features')) {
            $this->configure_features();
        }
    }

    protected function save_log(string $action = '', string $request = '', string $response = '', string $processed = ''): bool
    {
        $this->logs[] = ['action' => $action, 'request' => $request, 'response' => $response];

        return true;
    }

    protected function save_login_password(string $plain): bool
    {
        $this->savedPasswords[] = $plain;

        return true;
    }

    /** What the module logged, in the shape the tests read on both hosts. */
    public function logs(): array
    {
        return $this->logs;
    }

    /**
     * The real one encrypts; this one only marks, so a test can tell a sealed
     * secret from a clear one without reproducing WISECP's cipher.
     */
    protected function encode_str(string $plain): string
    {
        return 'sealed:' . base64_encode($plain);
    }

    protected function decode_str(string $sealed): string
    {
        return (string) base64_decode(substr($sealed, strlen('sealed:')), true);
    }

    public function save_options(): bool
    {
        $this->savedOptions++;

        return true;
    }
}
