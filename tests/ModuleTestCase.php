<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Utility;
use WISECP\Modules\Servers\TestPanelica;

/**
 * One behaviour test, run on both WISECP generations.
 *
 * The two hosts disagree about where the service's data is, where the account's
 * id may be kept, and how a failure is reported. Those differences live here
 * and nowhere else, so a test in Shared/ reads the same on both and still
 * exercises each host's real contract: on WISECP 5 a failure is an exception,
 * on WISECP 3 it is a false return with the message on $this->error.
 */
abstract class ModuleTestCase extends TestCase
{
    protected FakeApi $api;

    protected TestPanelica $module;

    /** The username the service carries, for the older host's lookups. */
    protected string $serviceUser = 'customer';

    protected function setUp(): void
    {
        parent::setUp();

        Utility::$redirects = [];

        // The older host logs to a static, so it is emptied between tests.
        if (property_exists('ServerModule', 'staticLogs')) {
            $host = 'ServerModule';
            $host::$staticLogs = [];
        }
    }

    protected function generation(): int
    {
        return (int) (getenv('WISECP_GENERATION') ?: '5');
    }

    /** WISECP 3: no save_options(), no save_login_password(), static save_log(). */
    protected function isLegacy(): bool
    {
        return $this->generation() === 3;
    }

    /**
     * A module with a server, a service and the options a package hands it.
     *
     * @param array<string,mixed> $options
     * @param array<string,mixed> $service
     * @param array<string,mixed> $server
     */
    protected function module(array $options = [], array $service = [], array $server = []): TestPanelica
    {
        $this->api = new FakeApi;

        $module = new TestPanelica;          // no server yet: the fake goes in first
        $module->fake = $this->api;
        $module->set_server($server + [
            'id' => 1,
            'hostname' => 'panel.example.test',
            'ip' => '203.0.113.10',
            'port' => 8443,
            'password' => 'pk_key',
            'access_hash' => 'sk_secret',
            'ns1' => 'ns1.example.test',
            'ns2' => 'ns2.example.test',
        ]);

        $options += ['plan_id' => 'plan-uuid-1'];
        $service += ['id' => 42, 'domain' => 'customer.test', 'user_email' => 'buyer@example.test', 'user_name' => 'A Buyer'];

        if ($this->isLegacy()) {
            // WISECP 3: the package's answers reach the order as creation_info,
            // and the service's own record sits on config.
            $module->order = $service + ['options' => ['creation_info' => $options]];
            $module->options = ['creation_info' => $options];
            $module->config = ['user' => $this->serviceUser, 'ip' => '203.0.113.10'];
            // The buyer sits on its own property here, not on the service.
            $module->user = ['id' => 7, 'email' => 'buyer@example.test', 'name' => 'A', 'surname' => 'Buyer', 'lang' => 'tr'];
        } else {
            $module->options = $options;
            $module->service = $service + ['username' => $this->serviceUser];
        }

        return $this->module = $module;
    }

    /**
     * The service already has an account on the panel.
     *
     * WISECP 5 lets the module keep the account's id, so it is simply there.
     * WISECP 3 gives the module nowhere to write it, so the module has to find
     * the account by the username the service carries - which means the panel
     * is asked. Both are the real thing for their host.
     *
     * @param array<string,mixed> $account extra fields the panel reports
     */
    protected function givenAccount(string $id = 'acc-1', array $account = []): void
    {
        $account += ['id' => $id, 'username' => $this->serviceUser];

        if ($this->isLegacy()) {
            $this->api->answer('GET', '/v1/accounts', [$account]);

            return;
        }

        $this->module->options['panelica_account_id'] = $id;
    }

    /**
     * The username this host would give an account for this domain.
     *
     * Each generation ships its own generator and the module defers to it, so
     * the name is not the same on both: WISECP 3's shortens, and the module
     * falls back to its own derivation only where there is no generator. A test
     * that hard-codes one name is really testing one host.
     */
    protected function expectedUsername(string $domain = 'customer.test'): string
    {
        if (method_exists($this->module, 'generate_username')) {
            return str_replace('-', '', $this->module->generate_username($domain));
        }

        return substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($domain)), 0, 16);
    }

    // ------------------------------------------------- the verbs, by host name

    /** @param array<string,mixed> $order_options */
    protected function create(string $domain = '', array $order_options = []): mixed
    {
        return $this->module->create($domain, $order_options);
    }

    protected function delete(): mixed
    {
        return $this->isLegacy() ? $this->module->terminate() : $this->module->cancel();
    }

    protected function testConnection(): mixed
    {
        return $this->isLegacy() ? $this->module->testConnect() : $this->module->test_connect();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,array<string,mixed>>
     */
    protected function packageForm(array $data = []): array
    {
        return $this->isLegacy() ? $this->module->config_options($data) : $this->module->product_configuration($data);
    }

    /** @param array<string,mixed> $product */
    protected function moveToPackage(array $product): mixed
    {
        return $this->isLegacy() ? $this->module->apply_updowngrade([], $product) : $this->module->upgrade($product);
    }

    /**
     * One service's own form in the admin panel.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function serviceForm(): array
    {
        return $this->isLegacy() ? $this->module->adminArea_service_fields() : $this->module->service_configuration();
    }

    /**
     * That form, saved with a new plan.
     *
     * WISECP 3 hands the module both the old and the new values and expects the
     * four bags back; WISECP 5 passes one array by reference. The caller here
     * only says which plan was chosen.
     */
    protected function saveServiceForm(string $plan, string $was = 'plan-uuid-1'): mixed
    {
        if ($this->isLegacy()) {
            return $this->module->save_adminArea_service_fields([
                'old' => ['creation_info' => ['plan_id' => $was], 'config' => ['user' => $this->serviceUser], 'ftp_info' => [], 'options' => []],
                'new' => ['creation_info' => ['plan_id' => $plan], 'config' => ['user' => $this->serviceUser], 'ftp_info' => [], 'options' => []],
            ]);
        }

        $values = ['plan_id' => $plan];
        $this->module->save_service_configuration($values);

        return $values;
    }

    // ---------------------------------------------------- failure, by host name

    /**
     * The call fails, in the way this host reports a failure.
     *
     * WISECP 5 catches the exception and shows it. WISECP 3 reads $this->error
     * and a false return, and never sees an exception - a module that throws
     * there shows the customer a blank screen. So this asserts each host's own
     * contract rather than pretending they are the same.
     *
     * @param callable():mixed $call
     */
    protected function assertFails(callable $call, ?string $contains = null): void
    {
        $this->module->error = '';

        if ($this->isLegacy()) {
            $result = $call();

            $this->assertFalse($result, 'WISECP 3 reads a false return as the failure.');
            $this->assertNotSame('', $this->module->error, 'WISECP 3 shows $this->error, so it must be set.');

            if ($contains !== null) {
                $this->assertStringContainsString($contains, $this->module->error);
            }

            return;
        }

        try {
            $call();
        } catch (Exception $e) {
            if ($contains !== null) {
                $this->assertStringContainsString($contains, $e->getMessage());
            } else {
                $this->assertNotSame('', $e->getMessage(), 'WISECP 5 shows the exception message, so it must say something.');
            }

            return;
        }

        $this->fail('WISECP 5 reports a failure by throwing, and nothing was thrown.');
    }

    // ------------------------------------------- what was kept, by host name

    /**
     * The account's id was kept where this host keeps it.
     *
     * On WISECP 5 that is the service's options, through save_options(). On
     * WISECP 3 there is nowhere to keep it, and the module must not pretend
     * otherwise: it is found again from the username on the next call, and
     * save_options() must never have been reached, because calling a method
     * that host does not have is a fatal error on a live install.
     */
    protected function assertAccountRemembered(string $id): void
    {
        if ($this->isLegacy()) {
            $this->assertFalse(
                method_exists($this->module, 'save_options'),
                'WISECP 3 has no save_options(); the module must not rely on one.'
            );

            return;
        }

        $this->assertSame(1, $this->module->savedOptions, 'the account id is kept on the service');
        $this->assertSame($id, $this->module->options['panelica_account_id']);
    }

    /** The password was kept where this host keeps it, or nowhere if it has nowhere. */
    protected function assertPasswordRemembered(string $password): void
    {
        if ($this->isLegacy()) {
            $this->assertFalse(
                method_exists($this->module, 'save_login_password'),
                'WISECP 3 has no save_login_password(); it stores what create() returns instead.'
            );

            return;
        }

        $this->assertContains($password, $this->module->savedPasswords);
    }

    /**
     * The account's id as create() reported it.
     *
     * WISECP 5 takes it back on the result; WISECP 3 stores only the username,
     * the password and the FTP block, so there is nothing to read there.
     *
     * @param array<string,mixed> $result
     */
    protected function createdId(array $result): ?string
    {
        return isset($result['panelica_account_id']) ? (string) $result['panelica_account_id'] : null;
    }

    /**
     * The username create() reported, from wherever this host keeps it.
     *
     * WISECP 3 stores a flat username, password and FTP block. WISECP 5 reads
     * every key as an instruction and keeps the identity under "config" and
     * what the customer is shown under "login", so there is no flat username
     * to read there - and putting one in would write a stray option.
     *
     * @param array<string,mixed> $result
     */
    protected function createdUsername(array $result): string
    {
        return (string) ($result['username'] ?? $result['config']['user'] ?? '');
    }

    /** @param array<string,mixed> $result */
    protected function createdPassword(array $result): string
    {
        return (string) ($result['password'] ?? $result['login']['password'] ?? '');
    }

    /** What the module logged, however this host stores it. */
    protected function logs(): array
    {
        return $this->module->logs();
    }
}
