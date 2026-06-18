<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests\Stub;

use NextcloudSaaS\NextcloudAPI;

/**
 * NextcloudAPI test double — injects canned OCS JSON without cURL.
 */
final class TestableNextcloudAPI extends NextcloudAPI
{
    private ?array $mockResponse = null;

    private ?\Exception $mockException = null;

    public function setMockResponse(array $response): void
    {
        $this->mockResponse = $response;
        $this->mockException = null;
    }

    public function setMockException(\Exception $exception): void
    {
        $this->mockException = $exception;
        $this->mockResponse = null;
    }

    protected function request($method, $endpoint, $data = [])
    {
        if ($this->mockException !== null) {
            throw $this->mockException;
        }

        if ($this->mockResponse === null) {
            throw new \Exception('Mock response not configured');
        }

        return $this->mockResponse;
    }
}
