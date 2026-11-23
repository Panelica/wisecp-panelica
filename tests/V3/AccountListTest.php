<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * The server's account list, which WISECP 3 reads with listAccounts() to let an
 * operator import accounts that already exist on the panel.
 *
 * WHAT THE PANEL REALLY ANSWERS (measured 23 Nov 2026 against the External API
 * source, internal/api/external_api_handler.go ListAccounts): the rows are the
 * panel's user records - id, username, email, full_name, phone, role, status,
 * created_at and so on. There is no domain on them, and there never was: a
 * domain is a separate record that points at a user, so one account can own
 * none, one or many. The older version of this file invented `primary_domain`
 * on the account and the module read it, which is why the list rendered with an
 * empty Domain column on a real install and the operator imported services with
 * no domain (reported by an operator running WISECP 3.1.9.8.2).
 *
 * The list therefore has to ask for the domains as well - the endpoint exists
 * and the key that reads accounts carries `domains:read` for exactly this.
 */
final class AccountListTest extends ModuleTestCase
{
    /** The shape ListAccounts really returns: user records, no domain. */
    private const ACCOUNTS = [
        ['id' => 'acc-1', 'username' => 'first', 'email' => 'a@example.test', 'role' => 'USER', 'status' => 'active', 'created_at' => '2026-01-02T03:04:05.000000Z', 'plan' => ['name' => 'Starter']],
        ['id' => 'acc-2', 'username' => 'second', 'email' => 'b@example.test', 'role' => 'USER', 'status' => 'suspended', 'created_at' => '2026-02-03T04:05:06Z', 'plan' => ['name' => 'Business']],
    ];

    /** The shape ListDomains really returns: domain_name plus the owner. */
    private const DOMAINS = [
        ['id' => 'dom-2', 'domain_name' => 'second.test', 'user_id' => 'acc-2', 'created_at' => '2026-02-03T05:00:00Z'],
        ['id' => 'dom-1', 'domain_name' => 'first.test', 'user_id' => 'acc-1', 'created_at' => '2026-01-02T04:00:00Z'],
    ];

    public function test_each_account_is_listed_with_the_domain_that_belongs_to_it(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', self::ACCOUNTS);
        $this->api->answer('GET', '/v1/domains', self::DOMAINS);

        $this->assertSame([
            ['cdate' => '2026-01-02 03:04:05', 'domain' => 'first.test', 'username' => 'first', 'plan' => 'Starter', 'suspended' => false],
            ['cdate' => '2026-02-03 04:05:06', 'domain' => 'second.test', 'username' => 'second', 'plan' => 'Business', 'suspended' => true],
        ], $this->module->listAccounts());
    }

    /**
     * An account can own several domains. The oldest one is the one the account
     * was opened with, so that is the one the list shows - and it must be the
     * same one on every run, not whatever order the panel happened to answer in.
     */
    public function test_an_account_with_several_domains_shows_the_oldest_one(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [self::ACCOUNTS[0]]);
        $this->api->answer('GET', '/v1/domains', [
            ['id' => 'd-new', 'domain_name' => 'added-later.test', 'user_id' => 'acc-1', 'created_at' => '2026-06-01T00:00:00Z'],
            ['id' => 'd-old', 'domain_name' => 'opened-with.test', 'user_id' => 'acc-1', 'created_at' => '2026-01-02T04:00:00Z'],
        ]);

        $rows = $this->module->listAccounts();
        $this->assertSame('opened-with.test', $rows[0]['domain']);
    }

    /** A brand new account has no domain yet. That is a blank cell, not a failure. */
    public function test_an_account_with_no_domain_is_listed_with_an_empty_domain(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [self::ACCOUNTS[0]]);
        $this->api->answer('GET', '/v1/domains', []);

        $rows = $this->module->listAccounts();
        $this->assertSame('', $rows[0]['domain']);
        $this->assertSame('first', $rows[0]['username']);
    }

    /**
     * The domains are an extra: the operator's import screen is about accounts.
     * If that call fails - the panel is busy, the key was issued without
     * `domains:read`, an older panel does not have the endpoint - the screen
     * must still list the accounts instead of showing an error page.
     */
    public function test_a_failing_domain_lookup_still_lists_the_accounts(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', self::ACCOUNTS);
        $this->api->answer('GET', '/v1/domains', new Exception('forbidden: scope domains:read missing (HTTP 403)'), 403);

        $rows = $this->module->listAccounts();
        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['domain']);
        $this->assertSame('first', $rows[0]['username']);
    }

    public function test_a_panel_with_no_accounts_lists_nothing_rather_than_failing(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', []);
        $this->api->answer('GET', '/v1/domains', []);

        $this->assertSame([], $this->module->listAccounts());
    }

    /** The accounts themselves are the screen. Losing them IS the failure. */
    public function test_a_panel_that_cannot_be_reached_is_reported(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', new Exception('invalid signature (HTTP 401)'), 401);

        $this->assertFails(fn () => $this->module->listAccounts(), 'invalid signature');
    }
}
