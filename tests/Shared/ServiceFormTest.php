<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * Changing one customer's package from the admin panel, and having the panel
 * actually change.
 *
 * This was the fourth thing asked for: "the customer's current hosting package
 * should be changeable and editable from the WISECP admin panel, and the change
 * should be carried to the Panelica server". The form is
 * adminArea_service_fields() on WISECP 3 and service_configuration() on
 * WISECP 5 - two more names for one job, which is why both are carried.
 */
final class ServiceFormTest extends ModuleTestCase
{
    public function test_the_form_offers_the_plans_and_names_the_account(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/plans', [['id' => 'plan-a', 'name' => 'Starter'], ['id' => 'plan-uuid-1', 'name' => 'Business']]);

        $form = $this->serviceForm();

        $this->assertSame(['plan-a' => 'Starter', 'plan-uuid-1' => 'Business'], $form['plan_id']['options']);
        $this->assertSame('plan-uuid-1', $form['plan_id']['value'], 'the plan this service is on now');

        // The role is decided when the account is opened; what an operator
        // changes here is the plan.
        $this->assertArrayNotHasKey('account_role', $form);
    }

    public function test_saving_a_new_plan_changes_it_on_the_panel(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('PATCH', '/v1/accounts/acc-1', []);

        $this->assertNotFalse($this->saveServiceForm('plan-big'));
        $this->assertTrue($this->api->made('PATCH', '/v1/accounts/acc-1', ['plan_id' => 'plan-big']));
    }

    public function test_saving_without_touching_the_plan_sends_nothing(): void
    {
        $this->module();
        $this->givenAccount('acc-1');

        // On a plan with different limits a plan change is a real operation,
        // so an operator who saves the page to edit something else must not
        // set one off.
        $this->assertNotFalse($this->saveServiceForm('plan-uuid-1'));
        $this->assertSame(0, $this->api->timesCalled('PATCH', '/v1/accounts/acc-1'));
    }

    public function test_an_empty_plan_is_refused(): void
    {
        $this->module();
        $this->givenAccount('acc-1');

        $this->assertFails(fn () => $this->saveServiceForm(''), 'no Panelica plan');
        $this->assertSame(0, $this->api->timesCalled('PATCH', '/v1/accounts/acc-1'));
    }

    public function test_a_panel_that_refuses_stops_the_save(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('PATCH', '/v1/accounts/acc-1', new Exception('that plan is not on this server'), 422);

        // Otherwise WISECP records a plan the account is not on: the invoice
        // and the account disagree and nothing says so.
        $this->assertFails(fn () => $this->saveServiceForm('plan-elsewhere'), 'that plan is not on this server');
    }
}
