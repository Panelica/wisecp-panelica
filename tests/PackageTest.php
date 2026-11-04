<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use Exception;

/**
 * The third and fourth things the module was asked for: a customer moving
 * between packages, and an operator choosing the plan a package hands out.
 */
final class PackageTest extends ModuleTestCase
{
    public function test_the_product_form_offers_the_panels_plans(): void
    {
        $module = $this->module();
        $this->api->answer('GET', '/v1/plans', [
            ['id' => 'plan-1', 'name' => 'Starter'],
            ['id' => 'plan-2', 'name' => 'Business'],
        ]);

        $fields = $module->product_configuration(['plan_id' => 'plan-2']);

        $this->assertSame('dropdown', $fields['plan_id']['type']);
        $this->assertSame(['plan-1' => 'Starter', 'plan-2' => 'Business'], $fields['plan_id']['options']);
        $this->assertSame('plan-2', $fields['plan_id']['value'], 'the plan already chosen stays chosen');
        $this->assertSame('dropdown', $fields['account_role']['type']);
    }

    public function test_a_panel_that_cannot_be_reached_leaves_the_form_usable(): void
    {
        $module = $this->module();
        $this->api->answer('GET', '/v1/plans', new Exception('could not reach the panel'), 0);

        $fields = $module->product_configuration(['plan_id' => 'plan-9']);

        // A text box with the saved value beats a form that cannot be opened.
        $this->assertSame('text', $fields['plan_id']['type']);
        $this->assertSame('plan-9', $fields['plan_id']['value']);
        $this->assertStringContainsString('could not reach the panel', $fields['plan_id']['description']);
    }

    public function test_saving_keeps_the_plan_and_the_role(): void
    {
        $module = $this->module();

        $saved = $module->save_product_configuration(['plan_id' => ' plan-1 ', 'account_role' => 'reseller']);

        $this->assertSame(['plan_id' => 'plan-1', 'account_role' => 'RESELLER'], $saved);
    }

    public function test_saving_a_package_with_no_plan_is_refused(): void
    {
        $module = $this->module();

        // Caught here, in front of the operator, rather than at the first order.
        $this->expectExceptionMessage('no Panelica plan');
        $module->save_product_configuration(['plan_id' => '']);
    }

    public function test_an_unknown_role_falls_back_to_a_customer_account(): void
    {
        $module = $this->module();

        $this->assertSame('USER', $module->save_product_configuration(['plan_id' => 'p', 'account_role' => 'root'])['account_role']);
    }

    public function test_upgrading_moves_the_account_to_the_new_packages_plan(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);
        $this->api->answer('PATCH', '/v1/accounts/acc-1', []);

        $this->assertTrue($module->upgrade(['module_data' => ['plan_id' => 'plan-2']]));
        $this->assertTrue($this->api->made('PATCH', '/v1/accounts/acc-1', ['plan_id' => 'plan-2']));
    }

    public function test_upgrading_to_a_package_with_no_plan_is_refused_rather_than_done_quietly(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);

        $this->expectExceptionMessage('no Panelica plan');
        $module->upgrade(['module_data' => []]);
    }

    public function test_the_connection_test_asks_something_that_needs_the_signature(): void
    {
        $module = $this->module();
        $this->api->answer('GET', '/v1/plans', []);

        $this->assertTrue($module->test_connect());
        $this->assertTrue($this->api->made('GET', '/v1/plans'));
    }

    public function test_a_wrong_key_fails_the_connection_test(): void
    {
        $module = $this->module();
        $this->api->answer('GET', '/v1/plans', new Exception('invalid signature (HTTP 401)'), 401);

        $this->expectExceptionMessage('invalid signature');
        $module->test_connect();
    }
}
