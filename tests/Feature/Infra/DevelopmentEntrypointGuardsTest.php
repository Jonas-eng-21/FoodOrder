<?php

namespace Tests\Feature\Infra;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\RunsShell;

/** The development entrypoint must keep refusing production settings (checked before it touches anything). */
class DevelopmentEntrypointGuardsTest extends TestCase
{
    use RunsShell;

    /** @param array<string, string|false> $env */
    private function runEntrypoint(array $env): Process
    {
        return $this->shell(['bash', $this->projectPath('docker/dev/entrypoint.sh'), 'true'], $env);
    }

    public function test_development_refuses_the_production_marker(): void
    {
        $process = $this->runEntrypoint(['FOODORDER_ENV_PROD' => '1', 'APP_ENV' => 'local']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('FOODORDER_ENV_PROD', $process->getErrorOutput());
    }

    public function test_development_refuses_a_non_local_environment(): void
    {
        foreach (['production', 'testing', 'staging', ''] as $env) {
            $process = $this->runEntrypoint(['FOODORDER_ENV_PROD' => false, 'APP_ENV' => $env]);

            $this->assertSame(1, $process->getExitCode(), "APP_ENV={$env}");
            $this->assertStringContainsString('APP_ENV', $process->getErrorOutput());
        }
    }

    public function test_development_refuses_a_production_style_setup_without_marker(): void
    {
        $process = $this->runEntrypoint(['FOODORDER_ENV_PROD' => false, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false']);

        $this->assertSame(1, $process->getExitCode());
    }
}
