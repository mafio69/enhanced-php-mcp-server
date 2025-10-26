<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\SystemInfo;
use PHPUnit\Framework\TestCase;

class SystemInfoTest extends TestCase
{
    public function testSystemInfoCreation(): void
    {
        $platform = ['platform' => 'Linux'];
        $php = ['version' => '8.1.0'];
        $server = ['software' => 'Test Server'];
        $resources = ['memory' => ['current' => '10 MB']];
        $security = ['file_uploads' => '1'];

        $systemInfo = new SystemInfo(
            platform: $platform,
            php: $php,
            server: $server,
            resources: $resources,
            security: $security
        );

        $this->assertSame($platform, $systemInfo->platform);
        $this->assertSame($php, $systemInfo->php);
        $this->assertSame($server, $systemInfo->server);
        $this->assertSame($resources, $systemInfo->resources);
        $this->assertSame($security, $systemInfo->security);
    }

    public function testSystemInfoToArray(): void
    {
        $platform = ['platform' => 'Linux'];
        $php = ['version' => '8.1.0'];
        $server = ['software' => 'Test Server'];
        $memoryInfo = new \App\ValueObjects\MemoryInfo('10 MB', '15 MB', '256M');
        $diskInfo = new \App\ValueObjects\DiskInfo('100 GB', '50 GB', '50 GB', 50.0);
        $resources = [
            'memory' => $memoryInfo,
            'disk' => $diskInfo,
        ];
        $security = ['file_uploads' => '1'];

        $systemInfo = new SystemInfo(
            platform: $platform,
            php: $php,
            server: $server,
            resources: $resources,
            security: $security
        );

        $array = $systemInfo->toArray();

        $this->assertArrayHasKey('platform', $array);
        $this->assertArrayHasKey('php', $array);
        $this->assertArrayHasKey('server', $array);
        $this->assertArrayHasKey('resources', $array);
        $this->assertArrayHasKey('security', $array);

        $this->assertEquals($platform, $array['platform']);
        $this->assertEquals($php, $array['php']);
        $this->assertEquals($server, $array['server']);
        $this->assertIsArray($array['resources']['memory']);
        $this->assertIsArray($array['resources']['disk']);
        $this->assertEquals($security, $array['security']);
    }
}