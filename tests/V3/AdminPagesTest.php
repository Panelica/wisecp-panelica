<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The pages WISECP 3 renders by convention, not by asking.
 *
 * This is the fault the operator reported twice: on the new hosting package
 * screen the server could be picked and then nothing appeared below it. The
 * reason was not the fields - it was that this generation does not read
 * config_options() itself. It includes pages/create-account-form-elements.php,
 * and that page is what asks the module and hands the fields to the base class
 * to draw. The module had no such page, so nothing was called and nothing was
 * logged.
 *
 * Measured on a live 3.1.9.8.2 install: all eighteen stock server modules carry
 * create-account-form-elements.php, order-detail.php, activation-html.php and
 * activation-text.php. An earlier commit here deleted four of those as
 * "unreachable", on the strength of a documentation line that only describes
 * client-area pages. These tests exist so that cannot happen again.
 */
final class AdminPagesTest extends ModuleTestCase
{
    /** @param array<string,mixed> $vars */
    private function render(string $page, array $vars = []): string
    {
        return $this->module->get_page($page, $vars);
    }

    public function test_every_page_this_generation_renders_is_present(): void
    {
        $dir = dirname(__DIR__, 2) . '/coremio/modules/Servers/Panelica/pages';

        foreach ([
            'create-account-form-elements.php',
            'order-detail.php',
            'activation-html.php',
            'activation-text.php',
            'clientArea-home.php',
        ] as $page) {
            $this->assertFileExists($dir . '/' . $page);
        }
    }

    public function test_the_new_package_screen_offers_the_panels_plans(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', [
            ['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter'],
            ['id' => 'bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb', 'name' => 'Business'],
        ]);

        $html = $this->render('create-account-form-elements', ['product' => []]);

        // A select the operator can actually use, named the way this screen
        // saves it.
        $this->assertStringContainsString('name="module_data[plan_id]"', $html);
        $this->assertStringContainsString('Starter', $html);
        $this->assertStringContainsString('Business', $html);
        $this->assertStringContainsString('aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', $html);
    }

    public function test_the_new_package_screen_still_draws_when_the_panel_is_unreachable(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', new \Exception('could not reach the panel'), 0);

        $html = $this->render('create-account-form-elements', ['product' => []]);

        // A text box and the reason, rather than an empty screen: the operator
        // can still configure the package by hand.
        $this->assertStringContainsString('name="module_data[plan_id]"', $html);
        $this->assertStringContainsString('could not reach the panel', $html);
    }

    public function test_the_order_screen_shows_the_account_and_the_plan(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/plans', [['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter']]);

        $html = $this->render('order-detail', ['order' => [
            'id' => 42,
            'options' => ['config' => ['user' => 'customer'], 'creation_info' => ['plan_id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa']],
        ]]);

        $this->assertStringContainsString('name="config[user]"', $html);
        $this->assertStringContainsString('value="customer"', $html);
        $this->assertStringContainsString('name="creation_info[plan_id]"', $html);
        $this->assertStringContainsString('Open the panel', $html, 'the button that reaches the panel from the order');
    }

    public function test_the_order_screen_does_not_print_the_panel_password(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/plans', []);

        $html = $this->render('order-detail', ['order' => [
            'options' => ['config' => ['user' => 'customer', 'password' => 'Secret-Pass-123']],
        ]]);

        // The stock modules put it on this screen in clear. There is nothing to
        // gain from it: WISECP's own action sets a new password through the
        // panel.
        $this->assertStringNotContainsString('Secret-Pass-123', $html);
        $this->assertStringNotContainsString('config[password]', $html);
    }

    public function test_the_welcome_mail_carries_what_the_customer_needs(): void
    {
        $this->module();

        $vars = [
            'options' => [
                'domain' => 'customer.test',
                'ftp_info' => ['host' => 'panel.example.test', 'username' => 'customertest', 'password' => 'Pass-9876', 'port' => 21],
            ],
            'server' => ['hostname' => 'panel.example.test', 'port' => 8443, 'ns1' => 'ns1.example.test', 'ns2' => 'ns2.example.test'],
        ];

        foreach (['activation-text', 'activation-html'] as $page) {
            $mail = $this->render($page, $vars);

            $this->assertStringContainsString('https://panel.example.test:8443', $mail, $page);
            $this->assertStringContainsString('customertest', $mail, $page);
            $this->assertStringContainsString('Pass-9876', $mail, $page);
            $this->assertStringContainsString('customer.test', $mail, $page);
            $this->assertStringContainsString('ns1.example.test, ns2.example.test', $mail, $page);
        }
    }

    public function test_the_welcome_mail_leaves_out_what_is_not_there(): void
    {
        $this->module();

        $mail = $this->render('activation-text', [
            'options' => ['ftp_info' => ['username' => 'u', 'password' => 'p']],
            'server' => ['hostname' => 'panel.example.test', 'port' => 8443],
        ]);

        // No domain and no nameservers on this service: the mail says nothing
        // about them rather than printing empty labels.
        $this->assertStringNotContainsString('webmail.', $mail);
        $this->assertStringNotContainsString('Nameservers:', $mail);
    }
}
