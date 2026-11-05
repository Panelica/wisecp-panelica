<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * Two faults that only a live panel showed, and that no faked test could have
 * found, because both are about what the real endpoints answer.
 *
 * Measured against a running Panelica: creating the account succeeded and
 * creating its domain was refused with "Field validation for 'Name' failed on
 * the required tag" - the field is name, not domain_name. The module then
 * reported a failure, the account's id never reached WISECP, and the
 * cancellation that followed found nothing to delete and reported success. An
 * account left open on the panel that nothing in WISECP could reach again,
 * which is the same shape as the delete fault the module was reported for.
 */
final class HalfDoneOrderTest extends ModuleTestCase
{
    public function test_the_domain_is_asked_for_by_the_name_the_endpoint_uses(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => $this->expectedUsername()])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        $this->assertTrue($this->api->made('POST', '/v1/domains', ['user_id' => 'acc-1', 'name' => 'customer.test']));
    }

    public function test_an_order_that_fails_on_the_domain_says_so_and_names_the_account(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => $this->expectedUsername()])
            ->answer('POST', '/v1/domains', new Exception('Invalid request (HTTP 400)'), 400);

        // Reported as a failure, because the site is not set up and the
        // operator has to know - but the message says the account exists and
        // what it is called, instead of only "invalid request".
        $this->assertFails(fn () => $this->create(), $this->expectedUsername());
        $this->assertStringContainsString('customer.test', $this->module->error);
        $this->assertStringContainsString('again', strtolower($this->module->error), 'it says what to do about it');
    }

    public function test_the_account_is_kept_the_moment_it_exists_not_when_the_order_finishes(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => $this->expectedUsername()])
            ->answer('POST', '/v1/domains', new Exception('Invalid request (HTTP 400)'), 400);

        try {
            $this->create();
        } catch (Exception) {
            // The failure is the point of the test above; here it is what was
            // kept despite it.
        }

        if ($this->isLegacy()) {
            // Nowhere to keep it on this host, so what must hold is that the
            // account was opened under the name a second attempt will look for.
            $this->assertSame($this->expectedUsername(), $this->api->calls[1]['body']['username']);

            return;
        }

        $this->assertSame('acc-1', $this->module->options['panelica_account_id']);
        $this->assertSame(1, $this->module->savedOptions, 'stored before the domain, not after it');
    }

    public function test_a_domain_the_account_already_has_is_not_added_twice(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => $this->expectedUsername()]])
            ->answer('GET', '/v1/accounts/acc-1/domains', [['name' => 'Customer.Test']]);

        $this->create();

        // Asking first is what makes the retry safe; creating it again would
        // turn a recoverable order into one that can never finish.
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/domains'));
    }

    public function test_a_second_attempt_reuses_the_account_and_tries_the_domain_again(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => $this->expectedUsername()]])
            ->answer('GET', '/v1/accounts/acc-1/domains', [])
            ->answer('POST', '/v1/domains', ['id' => 'dom-1']);

        $result = $this->create();

        // No second account, and the website the customer paid for.
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/accounts'));
        $this->assertTrue($this->api->made('POST', '/v1/domains', ['user_id' => 'acc-1', 'name' => 'customer.test']));
        $this->assertSame($this->expectedUsername(), $this->createdUsername($result));
    }

    public function test_a_customers_second_order_is_not_refused_over_their_own_address(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', new Exception('Failed to create: apiErrors.users.emailAlreadyExists'), 400)
            ->answer('POST', '/v1/domains', []);

        // The panel keeps one account per address, and WISECP hands the same
        // buyer's address to every service they order, so measured on a live
        // panel the second one could not be provisioned at all. A tagged
        // address delivers to the same inbox.
        $this->api->answerAgain('POST', '/v1/accounts', ['id' => 'acc-2', 'username' => $this->expectedUsername()]);

        $result = $this->create();

        $this->assertSame('buyer@example.test', $this->api->calls[1]['body']['email'], 'the first attempt is the address as given');
        $this->assertSame('buyer+wisecp42@example.test', $this->api->calls[2]['body']['email']);
        $this->assertSame($this->expectedUsername(), $this->createdUsername($result));
    }

    public function test_a_tag_already_on_the_address_is_replaced_rather_than_stacked(): void
    {
        $this->module([], ['user_email' => 'buyer+old@example.test']);
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', new Exception('Failed to create: apiErrors.users.emailAlreadyExists'), 400)
            ->answer('POST', '/v1/domains', []);
        $this->api->answerAgain('POST', '/v1/accounts', ['id' => 'acc-2', 'username' => $this->expectedUsername()]);

        $this->create();

        $this->assertSame('buyer+wisecp42@example.test', $this->api->calls[2]['body']['email']);
    }

    public function test_a_refusal_that_is_not_about_the_address_is_not_retried(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', new Exception('that plan does not exist here'), 400);

        // The account list is asked once to be sure, and then the refusal
        // stands: retrying a wrong plan with a different address would only
        // hide what is wrong.
        $this->assertFails(fn () => $this->create(), 'that plan does not exist here');
        $this->assertSame(1, $this->api->timesCalled('POST', '/v1/accounts'));
    }
}
