<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * WISECP 3's contract, which is not WISECP 5's.
 *
 * This is the generation the customer who reported the module actually runs
 * (his install stamps its assets ?v=3.1.9.8.2), so none of this is theory. The
 * host here has no save_options() and no save_login_password(); calling either
 * would be a fatal error on his server. Its save_log() is static with six
 * arguments. It does not catch exceptions, so a module that throws shows a
 * blank page. And it never calls the newer names at all.
 */
final class HostContractTest extends ModuleTestCase
{
    public function test_the_module_never_reaches_for_a_helper_this_host_does_not_have(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', []);

        // The guard is real, not decorative: these two do not exist here.
        $this->assertFalse(method_exists($this->module, 'save_options'));
        $this->assertFalse(method_exists($this->module, 'save_login_password'));

        $result = $this->create();

        $this->assertSame(['username', 'password', 'ftp_info'], array_keys($result));
    }

    public function test_the_account_is_found_again_from_the_username_because_there_is_nowhere_to_keep_an_id(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [
            ['id' => 'acc-other', 'username' => 'someone-else'],
            ['id' => 'acc-1', 'username' => 'customer'],
        ])->answer('POST', '/v1/accounts/acc-1/suspend', []);

        $this->assertTrue($this->module->suspend());

        // The service carries only its username, so the module asks the panel
        // which account that is, and acts on that one - not on the first in
        // the list.
        $this->assertTrue($this->api->made('POST', '/v1/accounts/acc-1/suspend', ['reason' => 'Suspended from WISECP']));
    }

    public function test_the_panel_is_asked_only_once_even_when_the_id_is_needed_twice(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('POST', '/v1/accounts/acc-1/suspend', [])
            ->answer('POST', '/v1/accounts/acc-1/unsuspend', []);

        $this->module->suspend();
        $this->module->unsuspend();

        $this->assertSame(1, $this->api->timesCalled('GET', '/v1/accounts'), 'the lookup is remembered for the call');
    }

    public function test_a_username_that_is_on_no_account_says_so_rather_than_acting_on_the_wrong_one(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-x', 'username' => 'somebody-else']]);

        $this->assertFails(fn () => $this->module->suspend(), 'no Panelica account yet');
    }

    public function test_a_failure_is_a_false_return_with_the_message_where_this_host_reads_it(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('DELETE', '/v1/accounts/acc-1', new Exception('the panel is busy (HTTP 500)'), 500);

        // This host's caller does not catch. An exception here would reach the
        // operator as a blank page with nothing in any log.
        $this->assertFalse($this->module->terminate());
        $this->assertSame('the panel is busy (HTTP 500)', $this->module->error);
    }

    public function test_the_failure_is_written_to_this_hosts_static_log(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('DELETE', '/v1/accounts/acc-1', new Exception('the panel is busy'), 500);

        $this->module->terminate();

        $logs = $this->logs();
        $this->assertContains('terminate', array_column($logs, 'action'), 'save_log() here is static and takes six arguments');
        $this->assertStringContainsString('the panel is busy', implode(' ', array_column($logs, 'response')));
    }

    public function test_the_hosts_own_username_generator_decides_the_name(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customer'])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        // generate_username() is this host's own, and where it exists it
        // decides, so every account on the install is named the same way
        // whichever module opened it. The stub shortens to eight characters.
        $this->assertSame('customer', $this->api->calls[1]['body']['username']);
    }

    public function test_the_buyers_address_and_name_come_from_where_this_host_keeps_them(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customer'])
            ->answer('GET', '/v1/accounts/acc-1/domains', [])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        // The buyer is on its own property here, not on the service. Reading
        // the wrong place does not fail: the account is opened with
        // hostmaster@<domain> and the customer never hears from the panel.
        $this->assertSame('buyer@example.test', $this->api->calls[1]['body']['email']);
        $this->assertSame('A Buyer', $this->api->calls[1]['body']['full_name'], 'name and surname, as this host splits them');
    }

    public function test_the_package_answers_are_read_from_where_this_host_keeps_them(): void
    {
        $this->module(['plan_id' => 'c1111111-1111-4111-8111-c11111111111']);
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customer'])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        // What the package form saved becomes the product's module_data and
        // reaches the order as creation_info, one level down from the order
        // options - which is where WISECP's own cPanel module reads its plan.
        $this->assertSame('c1111111-1111-4111-8111-c11111111111', $this->api->calls[1]['body']['plan_id']);
    }
}
