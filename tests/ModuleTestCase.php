<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use PHPUnit\Framework\TestCase;
use WISECP\Modules\Servers\TestPanelica;

abstract class ModuleTestCase extends TestCase
{
    protected FakeApi $api;

    protected TestPanelica $module;

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
        $module->options = $options + ['plan_id' => 'plan-uuid-1'];
        $module->service = $service + ['id' => 42, 'domain' => 'customer.test', 'user_email' => 'buyer@example.test', 'user_name' => 'A Buyer'];

        return $this->module = $module;
    }
}
