<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The package form and the package change, on both generations.
 *
 * The form is config_options() on WISECP 3 and product_configuration() on
 * WISECP 5; moving a service to another package is apply_updowngrade() there
 * and upgrade() here. Same two jobs, four names.
 */
final class PackageTest extends ModuleTestCase
{
    public function test_the_package_form_offers_the_panels_plans(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', [
            ['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter'],
            ['id' => 'bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb', 'name' => 'Business'],
        ]);

        $form = $this->packageForm(['plan_id' => 'bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb']);

        $this->assertSame('dropdown', $form['plan_id']['type']);
        $this->assertSame(['aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa' => 'Starter', 'bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb' => 'Business'], $form['plan_id']['options']);
        $this->assertSame('bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb', $form['plan_id']['value']);
        $this->assertSame(['USER' => 'Customer', 'RESELLER' => 'Reseller'], $form['account_role']['options']);
    }

    public function test_a_panel_that_cannot_be_reached_leaves_the_form_usable(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', new Exception('could not reach the panel'), 0);

        $form = $this->packageForm(['plan_id' => 'plan-typed-by-hand']);

        // A dropdown with nothing in it cannot be configured at all; a text box
        // can, and it says why it is one.
        $this->assertSame('text', $form['plan_id']['type']);
        $this->assertSame('plan-typed-by-hand', $form['plan_id']['value']);
        $this->assertStringContainsString('could not reach the panel', $form['plan_id']['description']);
    }

    public function test_the_form_asks_for_widths_the_way_its_own_host_measures_them(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', []);

        $width = $this->packageForm()['plan_id']['wrap_width'];

        // WISECP 3 measures a field's width in percent; WISECP 5 names a
        // Bootstrap column. Handing one host the other's units draws a broken
        // form, so each gets its own.
        if ($this->isLegacy()) {
            $this->assertSame(100, $width);
        } else {
            $this->assertSame('col-lg-6', $width);
        }
    }

    public function test_a_plan_given_by_name_is_resolved_to_its_id(): void
    {
        $this->module(['plan_id' => 'Business']);
        $this->api->answer('GET', '/v1/plans', [
            ['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter'],
            ['id' => 'bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb', 'name' => 'Business'],
        ])->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => $this->expectedUsername()])
            ->answer('GET', '/v1/accounts/acc-1/domains', [])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        // WISECP's own cPanel and KeyHelp modules store the plan's name, not
        // its id, so a package configured the way their operators are used to
        // has to work rather than fail as an unknown id.
        $this->assertSame('bbbbbbbb-1111-4111-8111-bbbbbbbbbbbb', $this->api->bodyOf('POST', '/v1/accounts')['plan_id']);
    }

    public function test_a_plan_name_the_panel_does_not_have_is_refused_by_name(): void
    {
        $this->module(['plan_id' => 'Platinum']);
        $this->api->answer('GET', '/v1/plans', [['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter']])
            ->answer('GET', '/v1/accounts', []);

        $this->assertFails(fn () => $this->create(), 'Platinum');
        $this->assertSame(0, $this->api->timesCalled('POST', '/v1/accounts'));
    }

    public function test_the_plan_is_read_under_the_key_the_stock_modules_use(): void
    {
        // They call it "plan"; this module calls it "plan_id". Both are read,
        // so a product configured either way provisions.
        $this->module(['plan_id' => '', 'plan' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa']);
        $this->api->answer('GET', '/v1/accounts', [])
            ->answer('POST', '/v1/accounts', ['id' => 'acc-1', 'username' => $this->expectedUsername()])
            ->answer('GET', '/v1/accounts/acc-1/domains', [])
            ->answer('POST', '/v1/domains', []);

        $this->create();

        $this->assertSame('aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', $this->api->bodyOf('POST', '/v1/accounts')['plan_id']);
    }

    public function test_upgrading_moves_the_account_to_the_new_packages_plan(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('PATCH', '/v1/accounts/acc-1', []);

        $this->assertTrue($this->moveToPackage(['module_data' => ['plan_id' => 'b1111111-1111-4111-8111-b11111111111']]));
        $this->assertTrue($this->api->made('PATCH', '/v1/accounts/acc-1', ['plan_id' => 'b1111111-1111-4111-8111-b11111111111']));
    }

    public function test_upgrading_to_a_package_with_no_plan_is_refused_rather_than_done_quietly(): void
    {
        $this->module();
        $this->givenAccount('acc-1');

        // A package with no plan would otherwise leave the customer paying for
        // one thing and holding another.
        $this->assertFails(fn () => $this->moveToPackage(['module_data' => []]), 'no Panelica plan');
        $this->assertSame(0, $this->api->timesCalled('PATCH', '/v1/accounts/acc-1'));
    }

    public function test_the_connection_test_asks_something_that_needs_the_signature(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', [['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter']]);

        $this->assertTrue($this->testConnection());
        $this->assertTrue($this->api->made('GET', '/v1/plans'));
    }

    public function test_a_connection_that_only_worked_unverified_says_so_in_the_log(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', [['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter']]);
        $this->api->certificateUnverified = true;

        // It connected, so this is not a failure - but a panel that could not
        // be proven to be the right one must not pass silently.
        $this->assertTrue($this->testConnection());

        $logged = implode(' ', array_column($this->logs(), 'response'));
        $this->assertStringContainsString('not verified', $logged);
        $this->assertStringContainsString('panel.example.test', $logged);
    }

    public function test_a_verified_connection_says_nothing_extra(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', [['id' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', 'name' => 'Starter']]);

        $this->assertTrue($this->testConnection());
        $this->assertStringNotContainsString('not verified', implode(' ', array_column($this->logs(), 'response')));
    }

    public function test_a_wrong_key_fails_the_connection_test(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/plans', new Exception('invalid signature (HTTP 401)'), 401);

        $this->assertFails(fn () => $this->testConnection(), 'invalid signature');
    }
}
