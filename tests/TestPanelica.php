<?php

declare(strict_types=1);

namespace WISECP\Modules\Servers;

use Panelica\Wisecp\Tests\FakeApi;

/**
 * The module with the API faked.
 *
 * define_server_info() is where the real client is built, so the tests override
 * exactly that one method and change nothing else: every other line under test
 * is the line that ships.
 */
final class TestPanelica extends Panelica
{
    public FakeApi $fake;

    /** @param array<string,mixed> $server */
    protected function define_server_info($server = []): void
    {
        $this->api = $this->fake ??= new FakeApi;
    }
}
