<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests;

use NextcloudSaaS\Tests\Stub\TestableNextcloudAPI;
use PHPUnit\Framework\TestCase;

/**
 * @covers \NextcloudSaaS\NextcloudAPI
 */
final class NextcloudAPITest extends TestCase
{
    public function test_constructor_trims_trailing_slash_from_base_url(): void
    {
        $api = new TestableNextcloudAPI('https://cloud.example.com/', 'admin', 'secret');
        $api->setMockResponse($this->ocsSuccess());

        $result = $api->testConnection();

        $this->assertTrue($result['success']);
    }

    public function test_testConnection_success_returns_version(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess([
            'version' => ['string' => '29.0.1'],
        ]));

        $result = $api->testConnection();

        $this->assertTrue($result['success']);
        $this->assertSame('29.0.1', $result['version']);
        $this->assertStringContainsString('29.0.1', $result['message']);
    }

    public function test_testConnection_failure_on_ocs_error(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsFailure(997, 'Invalid credentials'));

        $result = $api->testConnection();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid credentials', $result['message']);
    }

    public function test_testConnection_failure_on_curl_exception(): void
    {
        $api = $this->api();
        $api->setMockException(new \Exception('Erro cURL ao comunicar com Nextcloud: timeout'));

        $result = $api->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timeout', $result['message']);
    }

    public function test_createUser_success(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess());

        $result = $api->createUser('acme', 's3cret!', 'Acme Corp', 'ops@acme.test', '10 GB');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sucesso', $result['message']);
    }

    public function test_createUser_failure_on_ocs_error(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsFailure(102, 'User already exists'));

        $result = $api->createUser('acme', 's3cret!');

        $this->assertFalse($result['success']);
        $this->assertSame('User already exists', $result['message']);
    }

    public function test_getUser_success(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess([
            'id' => 'acme',
            'email' => 'ops@acme.test',
        ]));

        $result = $api->getUser('acme');

        $this->assertTrue($result['success']);
        $this->assertSame('acme', $result['data']['id']);
    }

    public function test_getUser_failure_when_user_missing(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsFailure(998, 'The requested account could not be found'));

        $result = $api->getUser('missing');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('could not be found', $result['message']);
    }

    public function test_changeUserPassword_success(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess());

        $result = $api->changeUserPassword('acme', 'new-pass-123');

        $this->assertTrue($result['success']);
    }

    public function test_setUserQuota_delegates_to_editUser(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess());

        $result = $api->setUserQuota('acme', '25 GB');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('atualizado', $result['message']);
    }

    public function test_getUserStorageInfo_maps_quota_fields(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsSuccess([
            'quota' => [
                'quota'    => 10737418240,
                'used'     => 1073741824,
                'free'     => 9663676416,
                'relative' => 10.0,
            ],
        ]));

        $result = $api->getUserStorageInfo('acme');

        $this->assertTrue($result['success']);
        $this->assertSame(10737418240, $result['data']['quota']);
        $this->assertSame(1073741824, $result['data']['used']);
        $this->assertSame(9663676416, $result['data']['free']);
        $this->assertSame(10.0, $result['data']['relative']);
    }

    public function test_getUserStorageInfo_propagates_getUser_failure(): void
    {
        $api = $this->api();
        $api->setMockResponse($this->ocsFailure(998, 'User not found'));

        $result = $api->getUserStorageInfo('ghost');

        $this->assertFalse($result['success']);
        $this->assertSame('User not found', $result['message']);
    }

    private function api(): TestableNextcloudAPI
    {
        return new TestableNextcloudAPI('https://cloud.example.com', 'admin', 'secret');
    }

    private function ocsSuccess(array $data = []): array
    {
        return [
            'ocs' => [
                'meta' => [
                    'statuscode' => 100,
                    'message'    => 'OK',
                ],
                'data' => $data,
            ],
        ];
    }

    private function ocsFailure(int $statusCode, string $message): array
    {
        return [
            'ocs' => [
                'meta' => [
                    'statuscode' => $statusCode,
                    'message'    => $message,
                ],
                'data' => [],
            ],
        ];
    }
}
