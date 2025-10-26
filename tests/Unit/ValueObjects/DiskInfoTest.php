<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\DiskInfo;
use PHPUnit\Framework\TestCase;

class DiskInfoTest extends TestCase
{
    public function testDiskInfoCreation(): void
    {
        $total = '500 GB';
        $used = '250 GB';
        $free = '250 GB';
        $percentageUsed = 50.0;

        $diskInfo = new DiskInfo(
            total: $total,
            used: $used,
            free: $free,
            percentage_used: $percentageUsed
        );

        $this->assertSame($total, $diskInfo->total);
        $this->assertSame($used, $diskInfo->used);
        $this->assertSame($free, $diskInfo->free);
        $this->assertSame($percentageUsed, $diskInfo->percentage_used);
    }

    public function testDiskInfoToArray(): void
    {
        $total = '500 GB';
        $used = '250 GB';
        $free = '250 GB';
        $percentageUsed = 50.0;

        $diskInfo = new DiskInfo(
            total: $total,
            used: $used,
            free: $free,
            percentage_used: $percentageUsed
        );

        $array = $diskInfo->toArray();

        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('used', $array);
        $this->assertArrayHasKey('free', $array);
        $this->assertArrayHasKey('percentage_used', $array);

        $this->assertEquals($total, $array['total']);
        $this->assertEquals($used, $array['used']);
        $this->assertEquals($free, $array['free']);
        $this->assertEquals($percentageUsed, $array['percentage_used']);
    }

    public function testDiskInfoWithPartialUsage(): void
    {
        $diskInfo = new DiskInfo(
            total: '1 TB',
            used: '750 GB',
            free: '250 GB',
            percentage_used: 75.0
        );

        $array = $diskInfo->toArray();

        $this->assertEquals(75.0, $array['percentage_used']);
        $this->assertEquals('1 TB', $array['total']);
        $this->assertEquals('750 GB', $array['used']);
        $this->assertEquals('250 GB', $array['free']);
    }

    public function testDiskInfoWithZeroUsage(): void
    {
        $diskInfo = new DiskInfo(
            total: '100 GB',
            used: '0 B',
            free: '100 GB',
            percentage_used: 0.0
        );

        $array = $diskInfo->toArray();

        $this->assertEquals(0.0, $array['percentage_used']);
        $this->assertEquals('0 B', $array['used']);
        $this->assertEquals('100 GB', $array['free']);
    }
}