<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * Deleting in WISECP must delete on the panel.
 *
 * This is the fault that started the work: the delete lived in a method neither
 * generation calls, so an account cancelled in WISECP stayed open on the panel
 * and nothing anywhere said so. The verb differs by generation - terminate() on
 * WISECP 3, cancel() on WISECP 5 - which is exactly why both are carried, and
 * why this test runs on both.
 */
final class DeleteTest extends ModuleTestCase
{
    public function test_deleting_the_service_deletes_the_account_on_the_panel(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('DELETE', '/v1/accounts/acc-1', []);

        $this->assertTrue($this->delete());
        $this->assertTrue($this->api->made('DELETE', '/v1/accounts/acc-1'));
    }

    public function test_an_account_already_gone_counts_as_deleted(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('DELETE', '/v1/accounts/acc-1', new Exception('account not found (HTTP 404)'), 404);

        // Otherwise a cancellation that was half done once can never finish.
        $this->assertTrue($this->delete());
    }

    public function test_a_panel_that_refuses_for_another_reason_is_not_swallowed(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('DELETE', '/v1/accounts/acc-1', new Exception('the panel is busy (HTTP 500)'), 500);

        $this->assertFails(fn () => $this->delete(), 'the panel is busy');
    }

    public function test_a_service_that_never_had_an_account_is_deleted_without_a_call(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', []);

        $this->assertTrue($this->delete());
        $this->assertSame(0, $this->api->timesCalled('DELETE', '/v1/accounts/acc-1'));
    }
}
