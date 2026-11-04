<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use Exception;

/**
 * The order's whole life, which is the fifth thing the module was asked for:
 * open the account, show it, change the package, suspend it, delete it.
 */
final class LifecycleTest extends ModuleTestCase
{
    public function test_create_opens_the_account_and_its_domain(): void
    {
        $module = $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', ['id' => 'dom-1']);

        $result = $module->create();

        $this->assertSame('acc-1', $result['panelica_account_id']);
        $this->assertSame('customertest', $result['username']);
        $this->assertNotSame('', $result['password']);
        $this->assertSame('customer.test', $result['domain']);

        // The plan the package carries, the buyer's address, and a name the
        // panel may adjust rather than refuse.
        $created = $this->api->calls[1]['body'];
        $this->assertSame('plan-uuid-1', $created['plan_id']);
        $this->assertSame('buyer@example.test', $created['email']);
        $this->assertTrue($created['auto_username']);
        $this->assertSame('USER', $created['role']);

        $this->assertTrue($this->api->made('POST', '/v1/domains', ['user_id' => 'acc-1', 'domain_name' => 'customer.test']));
        $this->assertSame(1, $module->savedOptions, 'the account id is kept on the service');
        $this->assertSame([$result['password']], $module->savedPasswords);
    }

    public function test_create_takes_the_username_and_password_the_order_carries(): void
    {
        $module = $this->module(['username' => 'chosen', 'password' => 'Chosen-Pass-123']);
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-2', 'username' => 'chosen'])
            ->answer('POST', '/v1/domains', []);

        $result = $module->create();

        $this->assertSame('chosen', $this->api->calls[1]['body']['username']);
        $this->assertSame('Chosen-Pass-123', $result['password']);
    }

    public function test_create_after_a_half_finished_order_does_not_open_a_second_account(): void
    {
        $module = $this->module(['username' => 'chosen']);
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-old', 'username' => 'Chosen']]);

        $result = $module->create();

        $this->assertSame('acc-old', $result['panelica_account_id']);
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/accounts'), 'no duplicate account');
    }

    public function test_create_without_a_plan_refuses_before_it_asks_the_panel(): void
    {
        $module = $this->module();
        $module->options = ['plan_id' => ''];
        $this->api->answer('GET', '/v1/accounts', []);

        $this->expectExceptionMessage('no Panelica plan');
        $module->create();
    }

    public function test_suspend_and_unsuspend_reach_the_panel(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1'], ['suspended_reason' => 'unpaid']);
        $this->api->answer('POST', '/v1/accounts/acc-1/suspend', [])
            ->answer('POST', '/v1/accounts/acc-1/unsuspend', []);

        $this->assertTrue($module->suspend());
        $this->assertTrue($module->unsuspend());
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/suspend', ['reason' => 'unpaid']));
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/unsuspend'));
    }

    public function test_change_password_sends_it_and_keeps_it_on_the_service(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);
        $this->api->answer('POST', '/v1/accounts/acc-1/change-password', []);

        $this->assertTrue($module->change_password('New-Pass-9876'));
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/change-password', ['password' => 'New-Pass-9876']));
        $this->assertSame(['New-Pass-9876'], $module->savedPasswords);
    }

    public function test_an_empty_password_is_refused(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);

        $this->expectExceptionMessage('A new password is needed');
        $module->change_password('   ');
    }

    public function test_acting_on_a_service_with_no_account_says_so(): void
    {
        $module = $this->module();

        $this->expectExceptionMessage('no Panelica account yet');
        $module->suspend();
    }
}
