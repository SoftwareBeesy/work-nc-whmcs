<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests\Stub;

use NextcloudSaaS\SSHManager;

/**
 * SSHManager test double — injects canned executeCommand output without network.
 */
final class TestableSSHManager extends SSHManager
{
    private string $mockOutput = '';

    public function setMockOutput(string $output): void
    {
        $this->mockOutput = $output;
    }

    public function executeCommand($command, $timeout = 0): array
    {
        return [
            'success'   => true,
            'output'    => $this->mockOutput,
            'error'     => '',
            'exit_code' => 0,
        ];
    }
}
