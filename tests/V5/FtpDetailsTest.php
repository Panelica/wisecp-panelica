<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V5;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The FTP details on the service page the module builds itself.
 *
 * BUG-01, reported 23 November 2026: a hosting account opened BY HAND in the
 * panel and then imported into WISECP showed the customer no FTP details at
 * all. The FTP card the HOST draws is built from what create() returned, and an
 * imported service never had a create() - the details appeared only after a
 * password change, which is what put them into the host's record.
 *
 * The module's own page does not depend on that record: it reads the account's
 * FTP login from the panel. Read-only on purpose - a page being looked at
 * creates nothing on the panel.
 *
 * No password is ever shown. The panel keeps only a hash of an FTP password, so
 * the module does not know it, and printing the PANEL password in an FTP field
 * is the fault reported as BUG-02 rather than a way round this one.
 */
final class FtpDetailsTest extends ModuleTestCase
{
    /**
     * The service page, with the account the page is about already answered.
     * The FTP rows are what these tests are for; the rest is scenery.
     *
     * @return array<string,mixed>
     */
    private function serverInfo(): array
    {
        $this->api
            ->answer('GET', '/v1/accounts/acc-1', ['username' => $this->serviceUser, 'status' => 'active', 'plan' => ['name' => 'Starter']])
            ->answer('GET', '/v1/accounts/acc-1/disk-usage', ['used_mb' => 1.0, 'quota_mb' => 10.0])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', ['bytes_used' => 0, 'bytes_total' => 0]);

        $info = $this->module->dashboard_data()['server_info'] ?? [];

        return is_array($info) ? $info : [];
    }

    public function test_an_imported_service_shows_its_ftp_login(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', [
            ['id' => 'ftp-9', 'user_id' => 'acc-1', 'ftp_username' => 'importeduser'],
        ]);

        $info = $this->serverInfo();

        $this->assertSame('importeduser', $info['ftp_username']['value'] ?? null);
        $this->assertSame('21', $info['ftp_port']['value'] ?? null);
        $this->assertArrayHasKey('ftp_host', $info);
    }

    /** The row the customer has to copy is marked copyable, like the username. */
    public function test_the_ftp_login_is_copyable(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', [
            ['id' => 'ftp-9', 'user_id' => 'acc-1', 'ftp_username' => 'importeduser'],
        ]);

        $this->assertTrue((bool) ($this->serverInfo()['ftp_username']['copyable'] ?? false));
    }

    public function test_no_ftp_password_is_shown(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', [
            [
                'id' => 'ftp-9',
                'user_id' => 'acc-1',
                'ftp_username' => 'importeduser',
                'ftp_password_hash' => 'nothing-readable',
            ],
        ]);

        $shown = json_encode($this->serverInfo());

        $this->assertIsString($shown);
        $this->assertStringNotContainsString('nothing-readable', $shown);
        $this->assertStringNotContainsString('password', $shown);
    }

    /** An account with no FTP login shows no FTP rows rather than empty ones. */
    public function test_a_service_without_an_ftp_login_shows_no_ftp_rows(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', []);

        $info = $this->serverInfo();

        $this->assertArrayNotHasKey('ftp_username', $info);
        $this->assertArrayNotHasKey('ftp_host', $info);
        $this->assertArrayNotHasKey('ftp_port', $info);
    }

    /**
     * Looking at the page creates nothing. Opening an FTP login for an account
     * that has none is an operator's decision and happens on the service form,
     * not because somebody opened a page.
     */
    public function test_looking_at_the_page_opens_no_ftp_login(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', []);

        $this->serverInfo();

        $this->assertFalse($this->api->made('POST', '/v1/ftp-accounts'));
    }

    /**
     * A panel that cannot answer about FTP logins does not take the page down
     * with it: the page is about the hosting account and the FTP rows are an
     * extra on it.
     */
    public function test_an_unreadable_ftp_list_still_draws_the_page(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', new Exception('key has no ftp:read (HTTP 403)'), 403);

        $info = $this->serverInfo();

        $this->assertSame($this->serviceUser, $info['username']['value'] ?? null);
        $this->assertArrayNotHasKey('ftp_username', $info);
    }

    /** Only this account's login, although the list answers with every one. */
    public function test_another_accounts_login_is_not_shown(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', [
            ['id' => 'ftp-x', 'user_id' => 'acc-2', 'ftp_username' => 'someone-else'],
        ]);

        $this->assertArrayNotHasKey('ftp_username', $this->serverInfo());
    }

    /**
     * An account with several logins shows the oldest - the one it was opened
     * with - so the answer order cannot decide what the customer reads.
     */
    public function test_the_oldest_login_is_the_one_shown(): void
    {
        $this->module();
        $this->givenAccount('acc-1', ['status' => 'active']);
        $this->api->answer('GET', '/v1/ftp-accounts', [
            ['id' => 'ftp-2', 'user_id' => 'acc-1', 'ftp_username' => 'later', 'created_at' => '2026-05-02T00:00:00Z'],
            ['id' => 'ftp-1', 'user_id' => 'acc-1', 'ftp_username' => 'first', 'created_at' => '2026-05-01T00:00:00Z'],
        ]);

        $this->assertSame('first', $this->serverInfo()['ftp_username']['value'] ?? null);
    }
}
