<?php

namespace Tests\Feature\Infra;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\RunsShell;

class ProductionEntrypointGuardsTest extends TestCase
{
    use RunsShell;

    /** @return array<string, string|false> */
    private function validEnvironment(): array
    {
        return [
            'FOODORDER_ENV_PROD' => '1',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => '/data/database.sqlite',
        ];
    }

    /** @param array<string, string|false> $overrides */
    private function check(array $overrides = []): Process
    {
        return $this->shell(['bash', $this->projectPath('docker/prod/entrypoint.sh'), '--check-env'], $overrides + $this->validEnvironment());
    }

    public function test_a_valid_production_environment_is_accepted(): void
    {
        $process = $this->check();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    /** @return array<string, array{array<string, string|false>, string}> */
    public static function rejectedEnvironments(): array
    {
        return [
            'local environment' => [['APP_ENV' => 'local'], 'APP_ENV'],
            'testing environment' => [['APP_ENV' => 'testing'], 'APP_ENV'],
            'missing app env' => [['APP_ENV' => false], 'APP_ENV'],
            'debug enabled' => [['APP_DEBUG' => 'true'], 'APP_DEBUG'],
            'debug numeric' => [['APP_DEBUG' => '1'], 'APP_DEBUG'],
            'debug missing' => [['APP_DEBUG' => false], 'APP_DEBUG'],
            'missing marker' => [['FOODORDER_ENV_PROD' => false], 'FOODORDER_ENV_PROD'],
            'empty marker' => [['FOODORDER_ENV_PROD' => ''], 'FOODORDER_ENV_PROD'],
            'marker zero' => [['FOODORDER_ENV_PROD' => '0'], 'FOODORDER_ENV_PROD'],
            'marker true' => [['FOODORDER_ENV_PROD' => 'true'], 'FOODORDER_ENV_PROD'],
            'marker with spaces' => [['FOODORDER_ENV_PROD' => ' 1'], 'FOODORDER_ENV_PROD'],
            'missing key' => [['APP_KEY' => false], 'APP_KEY'],
            'empty key' => [['APP_KEY' => ''], 'APP_KEY'],
            'key without prefix' => [['APP_KEY' => 'not-a-base64-key'], 'APP_KEY'],
            'key too short' => [['APP_KEY' => 'base64:'.base64_encode(random_bytes(16))], 'APP_KEY'],
            'key too long' => [['APP_KEY' => 'base64:'.base64_encode(random_bytes(48))], 'APP_KEY'],
            'key not base64' => [['APP_KEY' => 'base64:!!!!'], 'APP_KEY'],
            'other database driver' => [['DB_CONNECTION' => 'mysql'], 'DB_CONNECTION'],
            'missing driver' => [['DB_CONNECTION' => false], 'DB_CONNECTION'],
            'relative database path' => [['DB_DATABASE' => 'database/database.sqlite'], 'DB_DATABASE'],
            'database outside the data volume' => [['DB_DATABASE' => '/app/database/database.sqlite'], 'DB_DATABASE'],
            'in-memory database' => [['DB_DATABASE' => ':memory:'], 'DB_DATABASE'],
        ];
    }

    /** @param array<string, string|false> $overrides */
    #[DataProvider('rejectedEnvironments')]
    public function test_incompatible_environments_are_rejected(array $overrides, string $variable): void
    {
        $process = $this->check($overrides);

        $this->assertNotSame(0, $process->getExitCode(), 'The entrypoint must reject this environment.');
        $this->assertStringContainsString($variable, $process->getErrorOutput());
        $this->assertStringNotContainsString('base64:', $process->getErrorOutput(), 'The key must never be echoed.');
    }

    public function test_the_script_is_valid_bash_and_has_no_destructive_commands(): void
    {
        $this->assertSame(0, $this->shell(['bash', '-n', $this->projectPath('docker/prod/entrypoint.sh')])->getExitCode());

        $content = file_get_contents($this->projectPath('docker/prod/entrypoint.sh'));
        foreach (['migrate:fresh', 'migrate:reset', 'db:wipe', 'key:generate', 'composer update', 'rm -rf'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $content);
        }
        $this->assertStringContainsString('migrate --force', $content);
        $this->assertStringContainsString('db:seed --force', $content);
    }
}
