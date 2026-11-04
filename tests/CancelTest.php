<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests;

use Exception;

/**
 * The second thing the module was asked for: deleting in WISECP must delete on
 * the panel. It never did, because the delete lived in a method WISECP does not
 * call - the core calls cancel().
 */
final class CancelTest extends ModuleTestCase
{
    public function test_cancel_deletes_the_account_on_the_panel(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);
        $this->api->answer('DELETE', '/v1/accounts/acc-1', []);

        $this->assertTrue($module->cancel());
        $this->assertTrue($this->api->made('DELETE', '/v1/accounts/acc-1'));
    }

    public function test_an_account_already_gone_counts_as_cancelled(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);
        $this->api->answer('DELETE', '/v1/accounts/acc-1', new Exception('account not found (HTTP 404)'), 404);

        // Otherwise a cancellation that was half done once can never finish.
        $this->assertTrue($module->cancel());
    }

    public function test_a_panel_that_refuses_for_another_reason_is_not_swallowed(): void
    {
        $module = $this->module(['panelica_account_id' => 'acc-1']);
        $this->api->answer('DELETE', '/v1/accounts/acc-1', new Exception('the panel is busy (HTTP 500)'), 500);

        $this->expectExceptionMessage('the panel is busy');
        $module->cancel();
    }

    public function test_a_service_that_never_had_an_account_is_cancelled_without_a_call(): void
    {
        $module = $this->module();

        $this->assertTrue($module->cancel());
        $this->assertSame([], $this->api->calls);
    }
}
