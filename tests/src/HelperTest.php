<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests;

use NextcloudSaaS\Helper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \NextcloudSaaS\Helper
 */
final class HelperTest extends TestCase
{
    public function test_getRequiredDomains_returns_only_main_domain_in_shared_architecture(): void
    {
        $domain = 'cliente.example.com';
        $result = Helper::getRequiredDomains($domain);

        $this->assertIsArray($result);
        $this->assertCount(
            1,
            $result,
            'Na arquitetura compartilhada v3.0.0 cada cliente exige apenas 1 registro DNS.'
        );
        $this->assertSame($domain, $result['nextcloud']);
    }

    public function test_getCollaboraDomain_returns_global_hostname_independent_of_client(): void
    {
        $hostnameA = Helper::getCollaboraDomain('clienteA.example.com');
        $hostnameB = Helper::getCollaboraDomain('clienteB.outra.com.br');

        $this->assertNotEmpty($hostnameA);
        $this->assertSame(
            $hostnameA,
            $hostnameB,
            'A v3.0.0 deve devolver o MESMO hostname global de Collabora independentemente do dominio do cliente.'
        );
        $this->assertStringNotContainsString(
            'clienteA',
            $hostnameA,
            'O hostname global nao pode conter o subdominio do cliente.'
        );
    }

    public function test_getSignalingDomain_returns_global_hostname_independent_of_client(): void
    {
        $hostnameA = Helper::getSignalingDomain('clienteA.example.com');
        $hostnameB = Helper::getSignalingDomain('clienteB.outra.com.br');

        $this->assertNotEmpty($hostnameA);
        $this->assertSame(
            $hostnameA,
            $hostnameB,
            'A v3.0.0 deve devolver o MESMO hostname global de Signaling independentemente do dominio do cliente.'
        );
        $this->assertStringNotContainsString(
            'clienteA',
            $hostnameA,
            'O hostname global nao pode conter o subdominio do cliente.'
        );
    }

    public function test_getSharedHostnames_returns_three_public_endpoints(): void
    {
        $hostnames = Helper::getSharedHostnames();

        // São exatamente os 3 hostnames públicos expostos pelo Traefik.
        foreach (['collabora', 'signaling', 'turn'] as $key) {
            $this->assertArrayHasKey(
                $key,
                $hostnames,
                "getSharedHostnames() deve incluir o endpoint público $key (manager v11.x)."
            );
            $this->assertNotEmpty($hostnames[$key]);
        }
    }

    public function test_SHARED_CONTAINERS_lists_eight_global_services(): void
    {
        $expected = [
            'shared-db', 'shared-redis', 'shared-collabora', 'shared-turn',
            'shared-nats', 'shared-janus', 'shared-signaling', 'shared-recording',
        ];
        $this->assertSame(
            $expected,
            Helper::SHARED_CONTAINERS,
            'A lista SHARED_CONTAINERS deve listar os 8 servicos globais shared-* introduzidos pelo manager v11.x.'
        );
    }

    public function test_CONTAINER_SUFFIXES_lists_three_per_client(): void
    {
        $this->assertSame(
            ['app', 'cron', 'harp'],
            Helper::CONTAINER_SUFFIXES,
            'Cada cliente deve ter exatamente 3 containers dedicados (app, cron, harp).'
        );
    }

    public function test_isValidDomain_accepts_valid_hostnames(): void
    {
        $this->assertTrue(Helper::isValidDomain('cloud.example.com'));
        $this->assertTrue(Helper::isValidDomain('next-jaguar.defensys.seg.br'));
    }

    public function test_isValidDomain_rejects_invalid_hostnames(): void
    {
        $this->assertFalse(Helper::isValidDomain(''));
        $this->assertFalse(Helper::isValidDomain('not a domain'));
        $this->assertFalse(Helper::isValidDomain('-bad.example.com'));
    }

    public function test_getDomain_uses_params_domain_and_normalizes(): void
    {
        $domain = Helper::getDomain(['domain' => 'HTTPS://WWW.Cloud.Example.COM/']);
        $this->assertSame('cloud.example.com', $domain);
    }

    public function test_getDomain_falls_back_to_customfields(): void
    {
        $params = [
            'customfields' => [
                'Domínio da Instância' => 'nc.cliente.com.br',
            ],
        ];
        $this->assertSame('nc.cliente.com.br', Helper::getDomain($params));
    }

    public function test_getDomain_returns_empty_when_missing(): void
    {
        $this->assertSame('', Helper::getDomain([]));
    }

    public function test_parseCredentials_extracts_nextcloud_section(): void
    {
        $content = <<<TXT
Nextcloud:
  URL: https://cloud.example.com
  Usuário: admin
  Senha: SecretPass123
TXT;
        $creds = Helper::parseCredentials($content);
        $this->assertSame('https://cloud.example.com', $creds['nextcloud_url']);
        $this->assertSame('admin', $creds['nextcloud_user']);
        $this->assertSame('SecretPass123', $creds['nextcloud_pass']);
    }

    public function test_parseManageOutput_extracts_key_value_pairs(): void
    {
        $output = "URL: https://example.com\nSTATUS=running\n# comment\n";
        $parsed = Helper::parseManageOutput($output);
        $this->assertSame('https://example.com', $parsed['url']);
        $this->assertSame('running', $parsed['STATUS']);
    }

    public function test_getContainerNames_builds_three_container_names(): void
    {
        $names = Helper::getContainerNames('acme');
        $this->assertSame([
            'app' => 'acme-app',
            'cron' => 'acme-cron',
            'harp' => 'acme-harp',
        ], $names);
    }

    public function test_formatQuota_formats_bytes_and_unlimited(): void
    {
        $this->assertSame('Ilimitado', Helper::formatQuota(0));
        $this->assertSame('1 GB', Helper::formatQuota(1024 * 1024 * 1024));
    }

    public function test_formatQuotaForNextcloud_appends_gb_to_numeric_quota(): void
    {
        $this->assertSame('50 GB', Helper::formatQuotaForNextcloud('50'));
    }

    public function test_formatQuotaForNextcloud_preserves_unit_suffix(): void
    {
        $this->assertSame('10 GB', Helper::formatQuotaForNextcloud('10 GB'));
    }

    public function test_formatQuotaForNextcloud_maps_unlimited_aliases_to_none(): void
    {
        $this->assertSame('none', Helper::formatQuotaForNextcloud('ilimitado'));
        $this->assertSame('none', Helper::formatQuotaForNextcloud('unlimited'));
    }

    public function test_formatQuotaForNextcloud_treats_zero_as_numeric_gb(): void
    {
        // is_numeric('0') runs before unlimited aliases in Helper implementation.
        $this->assertSame('0 GB', Helper::formatQuotaForNextcloud('0'));
    }

    public function test_getServerConfig_maps_whmcs_params(): void
    {
        $cfg = Helper::getServerConfig([
            'serverhostname' => 'nc.host',
            'serverip'       => '198.51.100.2',
            'serverusername' => 'admin',
            'serverpassword' => 'pw',
            'serverport'     => 2222,
        ]);

        $this->assertSame('198.51.100.2', $cfg['ip']);
        $this->assertSame('admin', $cfg['username']);
        $this->assertSame(2222, $cfg['port']);
    }

    public function test_getProductConfig_applies_defaults(): void
    {
        $cfg = Helper::getProductConfig([]);
        $this->assertSame('10', $cfg['disk_quota_gb']);
        $this->assertSame('5', $cfg['max_users']);
        $this->assertSame('on', $cfg['enable_collabora']);
    }
}
