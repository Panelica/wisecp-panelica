<?php

declare(strict_types=1);

/*
 * WISECP, imitated.
 *
 * The module is plain PHP that extends a class WISECP owns, so the tests stand
 * that class in and run the module with no WISECP, no database and no network.
 * What is here is only the surface the module actually touches; anything else
 * would be a guess about code we cannot see.
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
        $this->lang = include __DIR__ . '/../coremio/modules/Servers/Panelica/lang/en.php';

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

    public function save_options(): bool
    {
        $this->savedOptions++;

        return true;
    }
}
