<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V5;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The parts of WISECP 5's contract that WISECP 3 does not share.
 *
 * Each of these is measured from WISECP's own developer documentation, not
 * guessed: how create()'s return is read, how the package form is saved, and
 * how a failure travels. Getting any of them wrong is silent - the module runs,
 * and something is quietly not stored or not shown.
 */
final class HostContractTest extends ModuleTestCase
{
    /** @return array<string,mixed> */
    private function created(): array
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', []);

        return $this->create();
    }

    public function test_creates_result_speaks_this_hosts_language_of_instructions(): void
    {
        $result = $this->created();

        // On this host every key of the array is an instruction: config and
        // login are merged under those names, anything else lands on the option
        // root. A flat array of our own names would store nothing WISECP reads.
        $this->assertSame('customertest', $result['config']['user']);
        $this->assertSame('acc-1', $result['config']['id']);
        $this->assertSame('customertest', $result['login']['username']);
        $this->assertArrayNotHasKey('username', $result, 'a stray root key would be written to the options as is');
        $this->assertSame('https://panel.example.test:8443', $result['login']['url']);
        $this->assertSame('acc-1', $result['panelica_account_id']);
        $this->assertSame(21, $result['ftp_info']['port']);
    }

    public function test_the_panel_password_is_not_stored_in_clear(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => 'customertest'])
            ->answer('POST', '/v1/domains', []);

        $result = $this->create();
        $password = $result['login']['password'];

        // This host offers encode_str() and says plainly never to store a panel
        // password in clear, so the stored identity under "config" is sealed.
        $this->assertStringStartsWith('sealed:', $result['config']['password']);
        $this->assertStringNotContainsString($password, $result['config']['password']);

        // "login" is the other half of the same rule: it is what the client
        // area shows the customer, so sealing it would print the ciphertext
        // where their password belongs.
        $this->assertSame($password, $this->api->calls[1]['body']['password']);
        $this->assertSame($password, $result['ftp_info']['password']);
    }

    public function test_the_package_form_is_saved_in_place_because_this_host_passes_it_by_reference(): void
    {
        $this->module();

        $values = ['plan_id' => '  plan-b  ', 'account_role' => 'reseller'];
        $this->module->save_product_configuration($values);

        // The host takes the values by reference and expects nothing back:
        // returning them instead would discard the normalisation without a
        // word, and the package would keep the untrimmed value.
        $this->assertSame('plan-b', $values['plan_id']);
        $this->assertSame('RESELLER', $values['account_role']);
    }

    public function test_an_unknown_role_falls_back_to_a_customer_account(): void
    {
        $this->module();

        $values = ['plan_id' => 'plan-a', 'account_role' => 'root'];
        $this->module->save_product_configuration($values);

        $this->assertSame('USER', $values['account_role']);
    }

    public function test_a_package_with_no_plan_is_refused(): void
    {
        $this->module();

        $values = ['plan_id' => '   '];
        $this->assertFails(fn () => $this->module->save_product_configuration($values), 'no Panelica plan');
    }

    public function test_a_failure_arrives_as_an_exception_because_that_is_what_this_host_catches(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('DELETE', '/v1/accounts/acc-1', new Exception('the panel is busy (HTTP 500)'), 500);

        // Its own documentation calls assigning to $this->error "a leftover of
        // the previous generation". The message is still written there, so a
        // caller that reads it finds it, but it is the exception that travels.
        try {
            $this->module->cancel();
            $this->fail('this host reports a failure by throwing');
        } catch (Exception $e) {
            $this->assertSame('the panel is busy (HTTP 500)', $e->getMessage());
        }
    }

    public function test_the_single_sign_on_hands_back_a_url_for_the_host_to_follow(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/sso-login', ['url' => 'https://panel.example.test:8443/sso?token=abc']);

        // This host asks for the URL and sends the customer itself, where
        // WISECP 3 expects the module to do the redirect.
        $this->assertSame('https://panel.example.test:8443/sso?token=abc', $this->module->sso_panel_login());
    }

    public function test_a_single_sign_on_the_panel_answers_with_only_a_token_still_works(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('POST', '/v1/accounts/acc-1/sso-login', ['token' => 'tok 1+2']);

        $this->assertSame('https://panel.example.test:8443/sso?token=tok+1%2B2', $this->module->sso_panel_login());
    }

    public function test_there_is_no_operator_panel_login_because_the_api_has_no_such_door(): void
    {
        $this->module();

        // The External API's one-time login is per account; there is nothing
        // for the panel's own operator. A verb with no endpoint behind it is a
        // button that fails in front of whoever presses it.
        $this->assertFalse(method_exists($this->module, 'sso_root_panel_login'));
    }

    public function test_a_renewal_needs_nothing_on_the_panel_but_still_answers(): void
    {
        $this->module();

        // A name the core cannot find is not a no-op: the queue records
        // "module method not found" and marks the renewal failed.
        $this->assertTrue($this->module->renew());
        $this->assertSame([], $this->api->calls);
    }
}
