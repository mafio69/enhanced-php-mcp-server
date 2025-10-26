<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\MemoryInfo;
use PHPUnit\Framework\TestCase;

class MemoryInfoTest extends TestCase
{
    public function testMemoryInfoCreation(): void
    {
        $current = '10.5 MB';
        $peak = '15.2 MB';
        $limit = '256M';

        $memoryInfo = new MemoryInfo(
            current: $current,
            peak: $peak,
            limit: $limit
        );

        $this->assertSame($current, $memoryInfo->current);
        $this->assertSame($peak, $memoryInfo->peak);
        $this->assertSame($limit, $memoryInfo->limit);
    }

    public function testMemoryInfoToArray(): void
    {
        $current = '10.5 MB';
        $peak = '15.2 MB';
        $limit = '256M';

        $memoryInfo = new MemoryInfo(
            current: $current,
            peak: $peak,
            limit: $limit
        );

        $array = $memoryInfo->toArray();

        $this->assertArrayHasKey('current', $array);
        $this->assertArrayHasKey('peak', $array);
        $this->assertArrayHasKey('limit', $array);

        $this->assertEquals($current, $array['current']);
        $this->assertEquals($peak, $array['peak']);
        $this->assertEquals($limit, $array['limit']);
    }

    public function testMemoryInfoWithDifferentFormats(): void
    {
        $memoryInfo = new MemoryInfo(
            current: '1.25 GB',
            peak: '2.5 GB',
            limit: '4G'
        );

        $array = $memoryInfo->toArray();

        $this->assertEquals('1.25 GB', $array['current']);
        $this->assertEquals('2.5 GB', $array['peak']);
        $this->assertEquals('4G', $array['limit']);
    }
}