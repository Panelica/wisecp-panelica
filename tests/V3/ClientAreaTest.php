<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;
use Utility;

/**
 * What the customer sees on the service page in WISECP 3 - the first thing the
 * module was reported for.
 *
 * This host does not build the page from data the way WISECP 5 does. It calls
 * clientArea(), which must hand back finished markup: the buttons, then a
 * template out of pages/. A module that answers in dashboard_data() here is
 * never called at all, and the customer gets an empty panel with no error
 * anywhere - which is exactly what was happening.
 */
final class ClientAreaTest extends ModuleTestCase
{
    private function page(): string
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('GET', '/v1/accounts/acc-1', ['username' => 'customer', 'status' => 'active', 'plan' => ['name' => 'Starter']])
            ->answer('GET', '/v1/accounts/acc-1/disk-usage', ['used_mb' => 512.0, 'quota_mb' => 5120.0])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', ['bytes_used' => 1024 * 1048576, 'bytes_total' => 0])
            ->answer('GET', '/v1/resource-quota/users/acc-1', ['bandwidth_quota_bytes' => 0]);

        return $this->module->clientArea();
    }

    public function test_the_page_shows_the_account_the_customer_bought(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('customer.test', $page);
        $this->assertStringContainsString('customer', $page);
        $this->assertStringContainsString('Starter', $page);
        $this->assertStringContainsString('ns1.example.test, ns2.example.test', $page);
        $this->assertStringContainsString('https://panel.example.test:8443', $page);
    }

    public function test_the_page_shows_what_is_used_and_what_has_no_limit(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('512 MB / 5120 MB', $page);
        $this->assertStringContainsString('1024 MB / ∞', $page);
    }

    public function test_the_page_is_markup_this_hosts_theme_can_render(): void
    {
        $page = $this->page();

        // The theme wraps whatever is returned, so the page brings the markup
        // its themes already style and nothing that would escape its layout.
        $this->assertStringContainsString('<table width="100%" border="0" cellpadding="5">', $page);
        $this->assertStringNotContainsString('<html', $page);
        $this->assertStringNotContainsString('<script', $page);
    }

    public function test_what_the_customer_typed_cannot_become_markup(): void
    {
        $this->module([], ['domain' => '<script>alert(1)</script>.test']);
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('GET', '/v1/accounts/acc-1', ['username' => '<b>x</b>', 'status' => 'active'])
            ->answer('GET', '/v1/accounts/acc-1/disk-usage', [])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', []);

        $page = $this->module->clientArea();

        $this->assertStringNotContainsString('<script>', $page);
        $this->assertStringNotContainsString('<b>x</b>', $page);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $page);
    }

    public function test_a_panel_that_cannot_be_reached_still_draws_the_page_and_says_why(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('GET', '/v1/accounts/acc-1', new Exception('the panel is unreachable'), 0);

        $page = $this->module->clientArea();

        // Throwing here would be a blank page: this host does not catch.
        $this->assertStringContainsString('the panel is unreachable', $page);
        $this->assertStringContainsString('customer.test', $page);
    }

    public function test_the_page_offers_the_panel_and_webmail(): void
    {
        $this->module();
        $buttons = $this->module->clientArea_buttons();

        $this->assertSame(['SingleSignOn', 'webMail'], array_keys($buttons));
        $this->assertSame('function', $buttons['SingleSignOn']['type']);
        $this->assertTrue($buttons['webMail']['target_blank']);
    }

    public function test_a_page_other_than_the_first_offers_the_way_back(): void
    {
        $this->module();
        $this->module->page = 'something-else';

        $buttons = $this->module->clientArea_buttons();

        $this->assertSame(['home'], array_keys($buttons));
        $this->assertSame('Turn back', $buttons['home']['text'], "the host's own wording, already translated");
        $this->assertSame('page-loader', $buttons['home']['type']);
    }

    public function test_the_panel_button_sends_the_customer_to_a_one_time_link(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('POST', '/v1/accounts/acc-1/sso-login', ['url' => 'https://panel.example.test:8443/sso?token=abc']);

        $this->assertTrue($this->module->use_clientArea_SingleSignOn());
        $this->assertSame(['https://panel.example.test:8443/sso?token=abc'], Utility::$redirects);
    }

    public function test_the_panel_button_says_why_rather_than_sending_the_customer_nowhere(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']])
            ->answer('POST', '/v1/accounts/acc-1/sso-login', new Exception('single sign on is off'), 403);

        ob_start();
        $result = $this->module->use_clientArea_SingleSignOn();
        $shown = (string) ob_get_clean();

        // A button answers the browser itself here, so the reason is printed
        // for the customer instead of thrown at a caller that is not listening.
        $this->assertFalse($result);
        $this->assertStringContainsString('single sign on is off', $shown);
        $this->assertSame([], Utility::$redirects);
    }

    public function test_the_webmail_button_goes_to_the_customers_own_domain(): void
    {
        $this->module();

        $this->assertTrue($this->module->use_clientArea_webMail());
        $this->assertSame(['https://webmail.customer.test'], Utility::$redirects);
    }

    public function test_there_is_no_operator_panel_login_because_the_api_has_no_such_door(): void
    {
        $this->module();

        // Panelica's External API offers a one-time login for an account and
        // nothing for the panel's own operator. A declared button with no
        // endpoint behind it fails in front of whoever presses it, so the name
        // this host would call is deliberately absent.
        $this->assertFalse(method_exists($this->module, 'use_adminArea_root_SingleSignOn'));
        $this->assertFalse(method_exists($this->module, 'sso_root_panel_login'));
    }
}
