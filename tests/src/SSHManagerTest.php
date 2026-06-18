<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests;

use NextcloudSaaS\SSHManager;
use NextcloudSaaS\Tests\Stub\TestableSSHManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \NextcloudSaaS\SSHManager
 */
final class SSHManagerTest extends TestCase
{
    public function test_constructor_normalizes_invalid_port_to_22(): void
    {
        $manager = new SSHManager('10.0.0.1', 'defensys', 'secret', '', 0);
        $this->assertSame(22, $this->readPort($manager));
    }

    public function test_fromWhmcsParams_uses_server_ip_and_port_fallback(): void
    {
        $manager = SSHManager::fromWhmcsParams([
            'serverip'       => '203.0.113.10',
            'serverhostname' => 'nc.example.com',
            'serverusername' => 'defensys',
            'serverpassword' => 'secret',
            'serverport'     => 0,
        ]);

        $host = $this->readPrivate($manager, 'host');
        $this->assertSame('203.0.113.10', $host);
        $this->assertSame(22, $this->readPort($manager));
    }

    public function test_instanceExists_true_when_dir_credentials_and_container_present(): void
    {
        $manager = new TestableSSHManager('10.0.0.1', 'defensys', 'secret');
        $manager->setMockOutput("DIR_OK\nCRED_OK\nENV_OK\nCTR_OK\n");

        $result = $manager->instanceExists('acme');

        $this->assertTrue($result['exists']);
        $this->assertTrue($result['has_credentials']);
        $this->assertTrue($result['has_container']);
        $this->assertFalse($result['partial']);
        $this->assertSame('/opt/nextcloud-customers/acme', $result['path']);
    }

    public function test_instanceExists_partial_when_directory_without_credentials(): void
    {
        $manager = new TestableSSHManager('10.0.0.1', 'defensys', 'secret');
        $manager->setMockOutput("DIR_OK\nCRED_MISS\nENV_OK\nCTR_MISS\n");

        $result = $manager->instanceExists('partial-client');

        $this->assertFalse($result['exists']);
        $this->assertTrue($result['partial']);
        $this->assertFalse($result['has_credentials']);
    }

    public function test_instanceExists_false_when_directory_missing(): void
    {
        $manager = new TestableSSHManager('10.0.0.1', 'defensys', 'secret');
        $manager->setMockOutput("DIR_MISS\nCRED_MISS\nENV_MISS\nCTR_MISS\n");

        $result = $manager->instanceExists('missing');

        $this->assertFalse($result['exists']);
        $this->assertFalse($result['partial']);
    }

    private function readPort(SSHManager $manager): int
    {
        return (int) $this->readPrivate($manager, 'port');
    }

    private function readPrivate(SSHManager $manager, string $property): mixed
    {
        $ref = new ReflectionProperty(SSHManager::class, $property);
        $ref->setAccessible(true);
        return $ref->getValue($manager);
    }
}
