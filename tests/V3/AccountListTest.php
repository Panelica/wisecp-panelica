<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The server's account list, which WISECP 3 reads with listAccounts() to let an
 * operator import accounts that already exist on the panel.
 */
final class AccountListTest extends ModuleTestCase
{
    public function test_the_accounts_on_the_panel_are_listed_in_this_hosts_shape(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [
            ['id' => 'acc-1', 'username' => 'first', 'created_at' => '2026-01-02 03:04:05.000000Z', 'primary_domain' => 'first.test', 'plan' => ['name' => 'Starter'], 'status' => 'active'],
            ['id' => 'acc-2', 'username' => 'second', 'created_at' => '2026-02-03 04:05:06', 'domain' => 'second.test', 'plan_name' => 'Business', 'status' => 'suspended'],
        ]);

        $this->assertSame([
            ['cdate' => '2026-01-02 03:04:05', 'domain' => 'first.test', 'username' => 'first', 'plan' => 'Starter', 'suspended' => false],
            ['cdate' => '2026-02-03 04:05:06', 'domain' => 'second.test', 'username' => 'second', 'plan' => 'Business', 'suspended' => true],
        ], $this->module->listAccounts());
    }

    public function test_a_panel_with_no_accounts_lists_nothing_rather_than_failing(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', []);

        $this->assertSame([], $this->module->listAccounts());
    }

    public function test_a_panel_that_cannot_be_reached_is_reported(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', new Exception('invalid signature (HTTP 401)'), 401);

        $this->assertFails(fn () => $this->module->listAccounts(), 'invalid signature');
    }
}
