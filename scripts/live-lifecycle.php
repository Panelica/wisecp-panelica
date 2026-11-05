<?php

declare(strict_types=1);

/*
 * The module's whole lifecycle against a running Panelica, with the real API
 * client - no fake anywhere. One run is one generation of WISECP, chosen with
 * WISECP_GENERATION, because a PHP process can define ServerModule only once.
 *
 * It opens a real account on the panel, reads it back, suspends it, changes its
 * password, moves its plan and deletes it again, and prints what each step
 * actually answered. The suite under tests/ proves the module's decisions; this
 * proves the endpoints exist, the field names match and the signing is
 * accepted - which is how the three faults in the README's live-run section
 * were found, none of which a faked answer could have shown.
 *
 * It opens and deletes a real account, so it is for a development panel.
 *
 *   PNL_KEY=pk_... PNL_SECRET=sk_... PNL_PLAN=<plan uuid> \
 *   PNL_DOMAIN=example.test WISECP_GENERATION=3 \
 *   php scripts/live-lifecycle.php
 *
 * PNL_PLAN2 is a second plan to move to, PNL_VERBOSE=1 prints every call. The
 * panel's own port and a trusted certificate are the usual way in; against a
 * development panel with a self-signed one, live-rewrite-proxy.php stands in
 * for the panel's nginx over plain HTTP on the loopback.
 */

$root = dirname(__DIR__);
$generation = getenv('WISECP_GENERATION') ?: '5';

// PNL_MODULE_DIR points this at a module that is already installed somewhere
// else - a real WISECP's modules/Servers/Panelica - so what is exercised is the
// copy that will actually run, on that machine's own PHP, and not a second one.
$module = getenv('PNL_MODULE_DIR') ?: $root . '/coremio/modules/Servers/Panelica';

require_once $root . '/tests/stubs-common.php';
require_once $root . '/tests/stubs-v' . $generation . '.php';
// The module file requires its own parts, the way WISECP loads it.
require_once $module . '/Panelica.php';

$key = getenv('PNL_KEY');
$secret = getenv('PNL_SECRET');
$plan = getenv('PNL_PLAN');
$plan2 = getenv('PNL_PLAN2') ?: $plan;
$domain = getenv('PNL_DOMAIN');

if (! $key || ! $secret || ! $plan || ! $domain) {
    fwrite(STDERR, "PNL_KEY, PNL_SECRET, PNL_PLAN and PNL_DOMAIN are required.\n");
    exit(2);
}

// PNL_HOST and PNL_PORT point at the panel. The default is the local rewrite
// proxy (live-rewrite-proxy.php), which is how a development panel with a
// self-signed certificate is reached; against a real panel, give its hostname
// and 8443 - the hostname its certificate covers, not an IP.
$host = getenv('PNL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PNL_PORT') ?: 8899);

$server = [
    'id' => 1,
    'hostname' => $host,
    'name' => $host,
    'ip' => $host,
    'port' => $port,
    'secure' => $port !== 8899,
    'password' => $key,
    'access_hash' => $secret,
    'ns1' => getenv('PNL_NS1') ?: 'ns1.' . $host,
    'ns2' => getenv('PNL_NS2') ?: 'ns2.' . $host,
];

$options = ['plan_id' => $plan, 'account_role' => 'USER'];
$service = ['id' => 4242, 'domain' => $domain, 'user_email' => 'wisecp-test@panelica.local', 'user_name' => 'WISECP Live Test'];

$module = new WISECP\Modules\Servers\Panelica;
$module->set_server($server);

if ((int) $generation === 3) {
    $module->order = $service + ['options' => ['creation_info' => $options]];
    $module->options = ['creation_info' => $options];
    $module->config = ['user' => '', 'ip' => '127.0.0.1'];
} else {
    $module->options = $options;
    $module->service = $service;
}

$failed = 0;
$step = function (string $name, callable $call) use (&$failed, $module): mixed {
    try {
        $result = $call();
    } catch (Throwable $e) {
        $failed++;
        printf("  %-34s THREW  %s\n", $name, $e->getMessage());

        return null;
    }

    if ($result === false) {
        $failed++;
        printf("  %-34s FALSE  %s\n", $name, $module->error !== '' ? $module->error : '(no reason given)');

        return false;
    }

    $shown = is_array($result)
        ? json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : var_export($result, true);
    printf("  %-34s ok     %s\n", $name, substr((string) $shown, 0, 260));

    return $result;
};

printf("\n=== WISECP %s, live against the panel ===\n", $generation);
$legacy = (int) $generation === 3;

$step($legacy ? 'testConnect' : 'test_connect', fn () => $legacy ? $module->testConnect() : $module->test_connect());
$step($legacy ? 'config_options' : 'product_configuration', function () use ($module, $legacy, $plan) {
    $form = $legacy ? $module->config_options(['plan_id' => $plan]) : $module->product_configuration(['plan_id' => $plan]);

    return ['type' => $form['plan_id']['type'], 'plans' => count($form['plan_id']['options'] ?? [])];
});

$created = $step('create', fn () => $module->create($legacy ? $domain : '', $legacy ? $options : []));

if (is_array($created)) {
    // What the host would have stored, stored.
    $username = (string) ($created['username'] ?? $created['config']['user'] ?? '');
    $id = (string) ($created['panelica_account_id'] ?? $created['config']['id'] ?? '');

    if ($legacy) {
        $module->config['user'] = $username;
    } else {
        $module->options['panelica_account_id'] = $id;
    }

    printf("  -> account %s, username %s\n", $id !== '' ? $id : '(found by username)', $username);
}

$step($legacy ? 'getDisk' : 'dashboard_data', fn () => $legacy ? $module->getDisk() : $module->dashboard_data());
$step($legacy ? 'getBandwidth' : 'metrics_usage', fn () => $legacy ? $module->getBandwidth() : $module->metrics_usage());

if ($legacy) {
    $step('clientArea (length)', fn () => strlen($module->clientArea()) . ' bytes of markup');
}

$step('suspend', fn () => $module->suspend());
$step('unsuspend', fn () => $module->unsuspend());
$step('change_password', fn () => $module->change_password('Live-Test-' . $generation . '-9876'));
$step($legacy ? 'apply_updowngrade' : 'upgrade', fn () => $legacy
    ? $module->apply_updowngrade([], ['module_data' => ['plan_id' => $plan2]])
    : $module->upgrade(['module_data' => ['plan_id' => $plan2]]));
$step($legacy ? 'adminArea_service_fields' : 'service_configuration', function () use ($module, $legacy) {
    $form = $legacy ? $module->adminArea_service_fields() : $module->service_configuration();

    return array_keys($form);
});
$step('sso login url', function () use ($module, $legacy) {
    if (! $legacy) {
        return $module->sso_panel_login();
    }
    ob_start();
    $ok = $module->use_clientArea_SingleSignOn();
    ob_end_clean();

    return $ok ? (Utility::$redirects[0] ?? '(nothing)') : false;
});
$step($legacy ? 'listAccounts (count)' : 'renew', fn () => $legacy ? count($module->listAccounts()) . ' accounts' : $module->renew());

// Always last, and always run: the account must not be left behind.
$step($legacy ? 'terminate' : 'cancel', fn () => $legacy ? $module->terminate() : $module->cancel());

if ($failed > 0 && getenv('PNL_VERBOSE')) {
    echo "\n--- what the module logged ---\n";
    foreach ($module->logs() as $line) {
        printf("  %s\n  req %s\n  res %s\n", $line['action'], substr($line['request'], 0, 400), substr($line['response'], 0, 600));
    }
}

printf("\n%s\n", $failed === 0 ? 'ALL STEPS OK' : $failed . ' STEP(S) FAILED');
exit($failed === 0 ? 0 : 1);
