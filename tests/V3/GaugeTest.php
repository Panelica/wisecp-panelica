<?php

declare(strict_types=1);

namespace Panelica\Wisecp\Tests\V3;

use Exception;
use Panelica\Wisecp\Tests\ModuleTestCase;

/**
 * Disk and traffic, in the shape WISECP 3 reads them.
 *
 * This host asks with getDisk() and getBandwidth(), one gauge per call, and it
 * wants the percentage worked out and the labels already formatted - the
 * opposite of WISECP 5, which wants raw numbers and formats them itself. It
 * also labels traffic in gigabytes while disk is in megabytes.
 */
final class GaugeTest extends ModuleTestCase
{
    private function withUsage(mixed $disk, mixed $bandwidth): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', [['id' => 'acc-1', 'username' => 'customer']]);

        $this->api->answer('GET', '/v1/resource-quota/users/acc-1', $disk);
        $this->api->answer('GET', '/v1/bandwidth/accounts/acc-1', $bandwidth);
    }

    public function test_disk_comes_back_formatted_with_its_percentage(): void
    {
        $this->withUsage(['disk_used_mb' => 512.0, 'disk_limit_mb' => 5120.0], []);

        $this->assertSame([
            'limit' => 5120.0,
            'used' => 512.0,
            'used-percent' => 10,
            'format-limit' => '5120 MB',
            'format-used' => '512 MB',
        ], $this->module->getDisk());
    }

    public function test_traffic_is_labelled_in_the_unit_this_host_expects(): void
    {
        $this->withUsage([], ['used_mb' => 2048.0, 'limit_mb' => 102400.0]);

        // The panel counts in megabytes and this gauge is a gigabyte one, so
        // the number is converted with the label rather than only relabelled.
        $this->assertSame([
            'limit' => 100.0,
            'used' => 2.0,
            'used-percent' => 2,
            'format-limit' => '100 GB',
            'format-used' => '2 GB',
        ], $this->module->getBandwidth());
    }

    public function test_no_limit_is_drawn_as_the_infinity_sign(): void
    {
        $this->withUsage(['disk_used_mb' => 7.5, 'disk_limit_mb' => 0], []);

        $gauge = $this->module->getDisk();

        $this->assertSame(0, $gauge['limit']);
        $this->assertSame('∞', $gauge['format-limit']);
        $this->assertSame(0, $gauge['used-percent'], 'a limitless account is never a full gauge');
        $this->assertSame('7.5 MB', $gauge['format-used']);
    }

    public function test_a_gauge_over_its_limit_stops_at_a_hundred(): void
    {
        $this->withUsage(['disk_used_mb' => 6000.0, 'disk_limit_mb' => 5120.0], []);

        $this->assertSame(100, $this->module->getDisk()['used-percent']);
    }

    public function test_a_gauge_the_panel_cannot_answer_is_no_gauge_rather_than_a_wrong_one(): void
    {
        $this->withUsage(new Exception('quota service is down'), ['used_mb' => 10.0, 'limit_mb' => 100.0]);

        // This host reads false as "this gauge has no value" and draws nothing;
        // zeroes would read as an empty disk, which is a different claim.
        $this->assertFalse($this->module->getDisk());
        $this->assertNotFalse($this->module->getBandwidth());
    }

    public function test_a_service_with_no_account_has_no_gauges(): void
    {
        $this->module();
        $this->api->answer('GET', '/v1/accounts', []);

        $this->assertFalse($this->module->getDisk());
        $this->assertFalse($this->module->getBandwidth());
    }
}
