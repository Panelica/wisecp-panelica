<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The FTP block the host stores, filled for a service that never had one.
 *
 * The other half of BUG-01 (23 November 2026). The module's own service page
 * now reads the FTP login live (see V5\FtpDetailsTest), but WISECP also draws
 * an FTP card of its own from a block it stores on the service, and that block
 * was only ever written by create(). An imported service therefore had an empty
 * one for good.
 *
 * It is filled on the service form, which is the screen an operator opens after
 * an import. Only when it is empty: a block already stored, or one the operator
 * typed over, is never touched. And this is the one place allowed to OPEN an FTP
 * login for an account that has none, because an operator asked for the save -
 * the customer's page stays read-only.
 */
final class FtpInfoFillTest extends ModuleTestCase
{
    protected function generation(): int
    {
        return 3;
    }

    private const PLAN = '11111111-1111-4111-8111-111111111111';

    public function test_an_empty_block_is_filled_from_the_panel(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api->answer('GET', '/v1/ftp-accounts', [
            ['id' => 'ftp-9', 'user_id' => 'acc-1', 'ftp_username' => 'importeduser'],
        ]);

        $saved = $this->saveServiceForm(self::PLAN);

        $this->assertIsArray($saved);
        $this->assertSame('importeduser', $saved['ftp_info']['username'] ?? null);
        $this->assertSame(21, $saved['ftp_info']['port'] ?? null);
        // The panel keeps only a hash of an FTP password, so there is none to
        // store. An empty field is left out rather than printed blank, and the
        // PANEL password is never put here (that is BUG-02).
        $this->assertArrayNotHasKey('password', $saved['ftp_info']);
    }

    /**
     * An account opened by hand has no FTP login at all - opening an account
     * creates none - so one is opened here, and then its password IS known and
     * is stored with it.
     */
    public function test_an_account_without_a_login_gets_one_opened(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('GET', '/v1/ftp-accounts', [])
            ->answer('POST', '/v1/ftp-accounts', ['id' => 'ftp-new', 'ftp_username' => $this->serviceUser]);

        $saved = $this->saveServiceForm(self::PLAN);

        $this->assertIsArray($saved);
        $this->assertTrue($this->api->made('POST', '/v1/ftp-accounts'));
        $this->assertSame($this->serviceUser, $saved['ftp_info']['username'] ?? null);
        $this->assertNotSame('', (string) ($saved['ftp_info']['password'] ?? ''));
    }

    /** A block the operator typed is theirs; the panel is not asked at all. */
    public function test_a_block_already_there_is_left_alone(): void
    {
        $this->module();
        $this->givenAccount();

        $saved = $this->module->save_adminArea_service_fields([
            'old' => ['creation_info' => ['plan_id' => self::PLAN], 'config' => ['user' => $this->serviceUser], 'ftp_info' => [], 'options' => []],
            'new' => [
                'creation_info' => ['plan_id' => self::PLAN],
                'config' => ['user' => $this->serviceUser],
                'ftp_info' => ['username' => 'typed-by-hand', 'host' => 'ftp.example.test', 'port' => 21],
                'options' => [],
            ],
        ]);

        $this->assertIsArray($saved);
        $this->assertSame('typed-by-hand', $saved['ftp_info']['username']);
        $this->assertFalse($this->api->made('GET', '/v1/ftp-accounts'));
    }

    /**
     * The save is about the service's plan. A panel that cannot answer about
     * FTP logins, or refuses to open one, must not fail it - the block stays
     * empty, which is the state the service was already in.
     */
    public function test_an_unreadable_ftp_list_does_not_fail_the_save(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api->answer('GET', '/v1/ftp-accounts', new Exception('key has no ftp:read (HTTP 403)'), 403);

        $saved = $this->saveServiceForm(self::PLAN);

        $this->assertIsArray($saved);
        $this->assertSame([], $saved['ftp_info']);
        $this->assertSame(self::PLAN, $saved['creation_info']['plan_id']);
    }

    public function test_a_refused_new_login_does_not_fail_the_save(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('GET', '/v1/ftp-accounts', [])
            ->answer('POST', '/v1/ftp-accounts', new Exception('plan allows no FTP accounts (HTTP 422)'), 422);

        $saved = $this->saveServiceForm(self::PLAN);

        $this->assertIsArray($saved);
        $this->assertSame([], $saved['ftp_info']);
    }
}
