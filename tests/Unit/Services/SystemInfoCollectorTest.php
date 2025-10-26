<?php

namespace Tests\Unit\Services;

use App\Services\SystemInfoCollector;
use App\ValueObjects\SystemInfo;
use PHPUnit\Framework\TestCase;

class SystemInfoCollectorTest extends TestCase
{
    private SystemInfoCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SystemInfoCollector();
    }

    public function testCollectReturnsSystemInfoObject(): void
    {
        $result = $this->collector->collect();

        $this->assertInstanceOf(SystemInfo::class, $result);
    }

    public function testCollectContainsRequiredSections(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $this->assertArrayHasKey('platform', $data);
        $this->assertArrayHasKey('php', $data);
        $this->assertArrayHasKey('server', $data);
        $this->assertArrayHasKey('resources', $data);
        $this->assertArrayHasKey('security', $data);
    }

    public function testPlatformInfo(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $platform = $data['platform'];

        $this->assertArrayHasKey('platform', $platform);
        $this->assertArrayHasKey('platform_description', $platform);
        $this->assertArrayHasKey('hostname', $platform);
        $this->assertArrayHasKey('timestamp', $platform);
        $this->assertArrayHasKey('timezone', $platform);

        $this->assertEquals(PHP_OS, $platform['platform']);
        $this->assertEquals(date_default_timezone_get(), $platform['timezone']);
    }

    public function testPhpInfo(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $php = $data['php'];

        $this->assertArrayHasKey('version', $php);
        $this->assertArrayHasKey('version_id', $php);
        $this->assertArrayHasKey('sapi', $php);
        $this->assertArrayHasKey('memory_limit', $php);
        $this->assertArrayHasKey('extensions', $php);

        $this->assertEquals(PHP_VERSION, $php['version']);
        $this->assertEquals(PHP_VERSION_ID, $php['version_id']);
        $this->assertEquals(PHP_SAPI, $php['sapi']);
        $this->assertIsArray($php['extensions']);
    }

    public function testResourcesInfo(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $resources = $data['resources'];

        $this->assertArrayHasKey('memory', $resources);
        $this->assertArrayHasKey('disk', $resources);
        $this->assertArrayHasKey('load_average', $resources);
        $this->assertArrayHasKey('processes', $resources);

        $memory = $resources['memory'];
        $this->assertArrayHasKey('current', $memory);
        $this->assertArrayHasKey('peak', $memory);
        $this->assertArrayHasKey('limit', $memory);

        $disk = $resources['disk'];
        $this->assertArrayHasKey('total', $disk);
        $this->assertArrayHasKey('used', $disk);
        $this->assertArrayHasKey('free', $disk);
        $this->assertArrayHasKey('percentage_used', $disk);
        $this->assertIsFloat($disk['percentage_used']);
    }

    public function testSecurityInfo(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $security = $data['security'];

        $this->assertArrayHasKey('session_status', $security);
        $this->assertArrayHasKey('session_save_path', $security);
        $this->assertArrayHasKey('open_basedir', $security);
        $this->assertArrayHasKey('file_uploads', $security);
        $this->assertArrayHasKey('allow_url_fopen', $security);
        $this->assertArrayHasKey('disable_functions', $security);
    }

    public function testExtensionsArrayContainsImportantExtensions(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $extensions = $data['php']['extensions'];
        $importantExtensions = ['curl', 'json', 'mbstring', 'openssl'];

        foreach ($importantExtensions as $ext) {
            $this->assertArrayHasKey($ext, $extensions, "Extension '{$ext}' should be in the extensions array");
            $this->assertIsBool($extensions[$ext], "Extension status should be boolean");
        }
    }

    public function testSystemInfoToArray(): void
    {
        $result = $this->collector->collect();
        $data = $result->toArray();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }
}