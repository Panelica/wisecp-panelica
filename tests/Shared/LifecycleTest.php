<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The order's whole life, on both WISECP generations: open the account, suspend
 * it, give it a new password, and refuse what cannot be done.
 *
 * Every test here runs twice - once with WISECP 5 standing in, once with
 * WISECP 3 - because create(), suspend(), unsuspend() and change_password() are
 * the four names both generations call, and the module has to behave the same
 * under both while obeying each one's own rules about storage and failure.
 */
final class LifecycleTest extends ModuleTestCase
{
    public function test_create_opens_the_account_and_its_domain(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', ['id' => 'dom-1']);

        $result = $this->create();

        $this->assertSame('customertest', $this->createdUsername($result));
        $this->assertNotSame('', $this->createdPassword($result));

        // The plan the package carries, the buyer's address, and a name the
        // panel may adjust rather than refuse.
        $created = $this->api->calls[1]['body'];
        $this->assertSame('11111111-1111-4111-8111-111111111111', $created['plan_id']);
        $this->assertSame('buyer@example.test', $created['email']);
        $this->assertTrue($created['auto_username']);
        $this->assertSame('USER', $created['role']);

        $this->assertTrue($this->api->made('POST', '/v1/domains', ['user_id' => 'acc-1', 'name' => 'customer.test']));
        $this->assertAccountRemembered('acc-1');
        $this->assertPasswordRemembered($this->createdPassword($result));
    }

    public function test_create_hands_back_the_ftp_details_the_service_page_shows(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', []);

        $result = $this->create();

        $this->assertSame('203.0.113.10', $result['ftp_info']['ip']);
        $this->assertSame('panel.example.test', $result['ftp_info']['host']);
        $this->assertSame('customertest', $result['ftp_info']['username']);
        $this->assertSame($this->createdPassword($result), $result['ftp_info']['password']);
        $this->assertSame(21, $result['ftp_info']['port']);
    }

    public function test_create_takes_the_username_and_password_the_order_carries(): void
    {
        $this->module(['username' => 'chosen', 'password' => 'Chosen-Pass-123']);
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-2', 'username' => 'chosen'])
            ->answer('POST', '/v1/domains', []);

        $result = $this->create();

        $this->assertSame('chosen', $this->api->calls[1]['body']['username']);
        $this->assertSame('Chosen-Pass-123', $this->createdPassword($result));
    }

    public function test_create_after_a_half_finished_order_does_not_open_a_second_account(): void
    {
        $this->module(['username' => 'chosen']);
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-old', 'username' => 'Chosen']])
            ->answer('GET', '/v1/accounts/acc-old/domains', [['name' => 'customer.test']]);

        $result = $this->create();

        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/accounts'), 'no duplicate account');
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/domains'), 'nor a duplicate domain');

        // The name the panel actually holds, not the one the order asked for:
        // the service has to carry the account's real spelling or every later
        // call looks up the wrong one.
        $this->assertSame('Chosen', $this->createdUsername($result));
    }

    public function test_create_without_a_plan_refuses_before_it_asks_the_panel(): void
    {
        $this->module(['plan_id' => '']);
        $this->api->answer('GET', '/v1/accounts', []);

        $this->assertFails(fn () => $this->create(), 'no Panelica plan');
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/accounts'));
    }

    public function test_suspend_and_unsuspend_reach_the_panel(): void
    {
        $this->module([], ['suspended_reason' => 'unpaid']);
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/suspend', [])
            ->answer('POST', '/v1/accounts/acc-1/unsuspend', []);

        $this->assertTrue($this->module->suspend());
        $this->assertTrue($this->module->unsuspend());
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/suspend', ['reason' => 'unpaid']));
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/unsuspend'));
    }

    public function test_a_suspension_with_no_reason_still_says_where_it_came_from(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/suspend', []);

        $this->assertTrue($this->module->suspend());
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/suspend', ['reason' => 'Suspended from WISECP']));
    }

    public function test_change_password_sends_it_and_keeps_it_on_the_service(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/change-password', []);

        $this->assertTrue($this->module->change_password('New-Pass-9876'));
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/change-password', ['new_password' => 'New-Pass-9876']));
        $this->assertPasswordRemembered('New-Pass-9876');
    }

    public function test_an_empty_password_is_refused(): void
    {
        $this->module();
        $this->givenAccount('acc-1');

        $this->assertFails(fn () => $this->module->change_password('   '), 'A new password is needed');
    }

    public function test_acting_on_a_service_with_no_account_says_so(): void
    {
        $this->module();
        // Nothing stored on the newer host, nothing to find on the older one.
        $this->api->answer('GET', '/v1/accounts', []);

        $this->assertFails(fn () => $this->module->suspend(), 'no Panelica account yet');
    }

    public function test_a_panel_that_refuses_the_suspension_is_reported_not_swallowed(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/suspend', new \Exception('the panel is busy (HTTP 500)'), 500);

        $this->assertFails(fn () => $this->module->suspend(), 'the panel is busy');
    }
}
