<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\Shared;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The FTP login's password, on both WISECP generations.
 *
 * BUG-02, reported 23 November 2026: changing the password changed the PANEL
 * account's password and nothing else, while the host went on showing that
 * password under its FTP heading - so the customer read a password that did not
 * open their FTP account. The module's design is one secret for both, because
 * create() opens the FTP login with the same password on purpose; this is the
 * half that was missing.
 *
 * The rules around it matter as much as the change itself: the panel password
 * has already changed by the time the FTP login is asked about, so a refusal
 * there cannot fail the whole operation, and a login the operator made
 * themselves is not this module's to touch.
 */
final class FtpTest extends ModuleTestCase
{
    public function test_changing_the_password_moves_the_ftp_password_too(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', [
                ['id' => 'ftp-9', 'user_id' => 'acc-1', 'ftp_username' => $this->serviceUser],
            ])
            ->answer('POST', '/v1/ftp-accounts/ftp-9/change-password', ['status' => 'success']);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));

        $this->assertTrue(
            $this->api->made('POST', '/v1/ftp-accounts/ftp-9/change-password', ['password' => 'New-Pass-123']),
            'the FTP login kept its old password while the customer was shown the new one'
        );
    }

    /**
     * A refusal from the FTP side must not report the change as failed: the
     * panel password HAS changed, and a customer told "failed" would go on
     * trying the old one and find themselves locked out.
     */
    public function test_a_refused_ftp_password_does_not_fail_the_change(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', [
                ['id' => 'ftp-9', 'user_id' => 'acc-1', 'ftp_username' => $this->serviceUser],
            ])
            ->answer('POST', '/v1/ftp-accounts/ftp-9/change-password', new Exception('refused (HTTP 422)'), 422);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));
    }

    /** An account with no FTP login has nothing to move, and that is not an error. */
    public function test_a_password_change_without_an_ftp_login_is_quiet(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', []);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));
        $this->assertFalse($this->api->made('POST', '/v1/ftp-accounts/ftp-9/change-password'));
    }

    /**
     * Several logins the operator made themselves, none named after the
     * account: changing a password nobody asked about is worse than leaving one
     * out of step, so none is touched.
     */
    public function test_several_operator_logins_are_left_alone(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', [
                ['id' => 'ftp-1', 'user_id' => 'acc-1', 'ftp_username' => 'deploy'],
                ['id' => 'ftp-2', 'user_id' => 'acc-1', 'ftp_username' => 'backups'],
            ]);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));
        $this->assertFalse($this->api->made('POST', '/v1/ftp-accounts/ftp-1/change-password'));
        $this->assertFalse($this->api->made('POST', '/v1/ftp-accounts/ftp-2/change-password'));
    }

    /**
     * The list endpoint takes no owner filter - a Root key is answered with
     * every FTP login on the server - so the owner is matched in the module.
     * Another account's login is not this one's to change.
     */
    public function test_another_accounts_ftp_login_is_not_touched(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', [
                ['id' => 'ftp-other', 'user_id' => 'acc-2', 'ftp_username' => $this->serviceUser],
            ]);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));
        $this->assertFalse($this->api->made('POST', '/v1/ftp-accounts/ftp-other/change-password'));
    }

    /** A panel that cannot answer about FTP logins still changes the password. */
    public function test_an_unreadable_ftp_list_does_not_fail_the_change(): void
    {
        $this->module();
        $this->givenAccount();
        $this->api
            ->answer('POST', '/v1/accounts/acc-1/change-password', ['status' => 'success'])
            ->answer('GET', '/v1/ftp-accounts', new Exception('key has no ftp:read (HTTP 403)'), 403);

        $this->assertNotFalse($this->module->change_password('New-Pass-123'));
    }
}
