<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V5;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * What the customer sees on the service page, on WISECP 5.
 *
 * This is the fault the module was first reported for: the customer could not
 * see the account they had bought. The page is built from dashboard_data(), and
 * the first version answered in clientArea_home() and client_area_data(), which
 * neither generation calls - so the page had nothing to draw and nothing
 * anywhere said why.
 */
final class DashboardTest extends ModuleTestCase
{
    /** @return array<string,mixed> */
    private function page(): array
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/accounts/acc-1', ['username' => 'customertest', 'status' => 'active', 'plan' => ['name' => 'Starter']])
            ->answer('GET', '/v1/resource-quota/users/acc-1', ['disk_used_mb' => 512.0, 'disk_limit_mb' => 5120.0])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', ['used_mb' => 1024.0, 'limit_mb' => 102400.0]);

        return $this->module->dashboard_data();
    }

    public function test_the_page_shows_the_account_the_customer_bought(): void
    {
        $info = $this->page()['server_info'];

        $this->assertSame('customer.test', $info['domain']['value']);
        $this->assertSame('customertest', $info['username']['value']);
        $this->assertSame('Starter', $info['plan']['value']);
        $this->assertSame('ns1.example.test, ns2.example.test', $info['nameservers']['value']);
        $this->assertSame('https://panel.example.test:8443', $info['panel']['value']);

        // The two the customer copies out most.
        $this->assertTrue($info['domain']['copyable']);
        $this->assertTrue($info['username']['copyable']);
    }

    public function test_the_status_is_shown_in_the_customers_language_with_a_colour(): void
    {
        $status = $this->page()['server_info']['status'];

        $this->assertSame('Active', $status['value']);
        $this->assertSame('success', $status['badge_color']);
    }

    public function test_a_suspended_account_is_marked_as_one(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/accounts/acc-1', ['username' => 'u', 'status' => 'suspended'])
            ->answer('GET', '/v1/resource-quota/users/acc-1', [])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', []);

        $status = $this->module->dashboard_data()['server_info']['status'];

        $this->assertSame('Suspended', $status['value']);
        $this->assertSame('danger', $status['badge_color']);
    }

    public function test_disk_and_traffic_are_shown_as_gauges_this_host_can_draw(): void
    {
        $gauges = $this->page()['gauges'];

        // Raw numbers and a unit, no percentage: the theme works out the ratio
        // and the colour tier itself, and ignores one that is sent.
        $this->assertSame(['used' => 512.0, 'total' => 5120.0, 'unit' => 'MB'], $gauges['disk']);
        $this->assertSame(['used' => 1024.0, 'total' => 102400.0, 'unit' => 'MB'], $gauges['bandwidth']);
    }

    public function test_a_plan_with_no_ceiling_is_shown_as_unlimited(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/accounts/acc-1', ['username' => 'u', 'status' => 'active'])
            ->answer('GET', '/v1/resource-quota/users/acc-1', ['disk_used_mb' => 7.0, 'disk_limit_mb' => 0])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', []);

        // A total of zero would otherwise draw a full gauge on an account that
        // has no limit at all.
        $this->assertSame(['used' => 7.0, 'total' => 'unlimited', 'unit' => 'MB'], $this->module->dashboard_data()['gauges']['disk']);
    }

    public function test_a_gauge_the_panel_cannot_answer_is_left_off_rather_than_taking_the_page_down(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/accounts/acc-1', ['username' => 'u', 'status' => 'active'])
            ->answer('GET', '/v1/resource-quota/users/acc-1', new Exception('quota service is down'), 503)
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', ['used_mb' => 10.0, 'limit_mb' => 100.0]);

        $page = $this->module->dashboard_data();

        $this->assertArrayNotHasKey('disk', $page['gauges']);
        $this->assertArrayHasKey('bandwidth', $page['gauges']);
        $this->assertSame('u', $page['server_info']['username']['value']);
    }

    public function test_the_usage_cron_reads_the_same_numbers(): void
    {
        $this->module();
        $this->givenAccount('acc-1');
        $this->api->answer('GET', '/v1/resource-quota/users/acc-1', ['disk_used_mb' => 512.0, 'disk_limit_mb' => 5120.0])
            ->answer('GET', '/v1/bandwidth/accounts/acc-1', ['used_mb' => 1024.0, 'limit_mb' => 102400.0]);

        $this->assertSame(['disk' => 512.0, 'bandwidth' => 1024.0], $this->module->metrics_usage());
    }
}
