<?php

namespace Tests\Feature\Infra;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\RunsShell;

/**
 * Exercises scripts/prod/dcp.sh inside a throwaway project with a stub "docker" executable,
 * so no real Docker command (and no real stack) is ever involved.
 */
class ProductionScriptTest extends TestCase
{
    use RunsShell;

    private string $root;

    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = realpath(sys_get_temp_dir()).'/foodorder-dcp-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/scripts/prod', 0777, true);
        mkdir($this->root.'/bin');
        copy($this->projectPath('scripts/prod/dcp.sh'), $this->root.'/scripts/prod/dcp.sh');
        file_put_contents($this->root.'/compose.prod.yaml', "name: foodorder-prod\n");
        $this->writeEnvFile();
        $this->log = $this->root.'/docker-calls.log';
        file_put_contents($this->root.'/bin/docker', <<<'SH'
#!/usr/bin/env bash
{
    printf 'ARGS:'; printf '[%s]' "$@"; printf '\n'
    printf 'COMPOSE_FILE=%s COMPOSE_PROJECT_NAME=%s COMPOSE_PROFILES=%s\n' "${COMPOSE_FILE-unset}" "${COMPOSE_PROJECT_NAME-unset}" "${COMPOSE_PROFILES-unset}"
} >> "${DCP_TEST_LOG}"
case " $* " in
    *" key:generate "*) echo "base64:$(head -c 32 /dev/zero | base64)" ;;
esac
exit 0
SH);
        chmod($this->root.'/bin/docker', 0755);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }

    /** @param array<string, string> $overrides */
    private function writeEnvFile(array $overrides = []): void
    {
        $values = $overrides + [
            'APP_NAME' => 'FoodOrder', 'APP_ENV' => 'production', 'APP_KEY' => '', 'APP_DEBUG' => 'false',
            'FOODORDER_ENV_PROD' => '1', 'PROD_PORT' => '8081', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => '/data/database.sqlite',
        ];
        file_put_contents($this->root.'/.env.prod', implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($values), $values))."\n");
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string|false>  $env
     */
    private function dcp(array $arguments, array $env = []): Process
    {
        return $this->shell(
            ['bash', $this->root.'/scripts/prod/dcp.sh', ...$arguments],
            $env + ['PATH' => $this->root.'/bin:'.getenv('PATH'), 'DCP_TEST_LOG' => $this->log, 'COMPOSE_FILE' => false, 'COMPOSE_PROJECT_NAME' => false, 'COMPOSE_PROFILES' => false] + self::clearedConfiguration(),
            sys_get_temp_dir(),
        );
    }

    /**
     * The test container itself runs the development stack, so its application variables are cleared
     * to mimic a clean operator shell.
     *
     * @return array<string, false>
     */
    private static function clearedConfiguration(): array
    {
        return array_fill_keys(['FOODORDER_ENV_PROD', 'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL', 'APP_NAME', 'DB_CONNECTION', 'DB_DATABASE', 'PROD_PORT', 'LOG_CHANNEL', 'SESSION_DRIVER', 'CACHE_STORE'], false);
    }

    /** @return list<string> */
    private function calls(): array
    {
        return file_exists($this->log) ? array_values(array_filter(array_map('trim', file($this->log)), fn ($l) => str_starts_with($l, 'ARGS:'))) : [];
    }

    private function expected(string ...$arguments): string
    {
        $base = ['compose', '--project-name', 'foodorder-prod', '--project-directory', $this->root, '--env-file', $this->root.'/.env.prod', '-f', $this->root.'/compose.prod.yaml'];

        return 'ARGS:'.implode('', array_map(fn ($a) => "[{$a}]", [...$base, ...$arguments]));
    }

    /** @return array<string, array{list<string>}> */
    public static function allowedCommands(): array
    {
        return [
            'config' => [['config']],
            'config quiet' => [['config', '--quiet']],
            'up detached build' => [['up', '-d', '--build']],
            'up services' => [['up', '-d', 'app', 'web']],
            'ps' => [['ps']],
            'ps all' => [['ps', '-a']],
            'logs' => [['logs']],
            'logs follow app' => [['logs', '-f', 'app']],
            'logs tail' => [['logs', '--tail', '100', 'web']],
            'logs tail inline' => [['logs', '--tail=50']],
            'restart' => [['restart']],
            'restart app' => [['restart', 'app']],
            'stop' => [['stop']],
            'start' => [['start']],
            'down' => [['down']],
            'down with timeout' => [['down', '-t', '10']],
            'down with long timeout' => [['down', '--timeout', '10']],
            'build' => [['build']],
            'build no cache' => [['build', '--no-cache', 'app']],
            'images' => [['images']],
        ];
    }

    /** @param list<string> $arguments */
    #[DataProvider('allowedCommands')]
    public function test_allowed_commands_are_forwarded_with_explicit_project_file_and_environment(array $arguments): void
    {
        $process = $this->dcp($arguments);

        // `config` without --quiet is forwarded with --no-env-resolution so secrets are never printed.
        $forwarded = $arguments === ['config'] ? ['config', '--no-env-resolution'] : $arguments;

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $calls = $this->calls();
        $this->assertNotEmpty($calls);
        $this->assertSame($this->expected(...$forwarded), end($calls));
    }

    public function test_start_commands_run_a_configuration_preflight_first(): void
    {
        $this->dcp(['up', '-d', '--build']);

        $calls = $this->calls();
        $this->assertCount(2, $calls);
        $this->assertSame($this->expected('config', '--quiet'), $calls[0]);
    }

    public function test_the_script_works_from_any_directory(): void
    {
        $process = $this->shell(['bash', $this->root.'/scripts/prod/dcp.sh', 'ps'], ['PATH' => $this->root.'/bin:'.getenv('PATH'), 'DCP_TEST_LOG' => $this->log] + self::clearedConfiguration(), '/');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $calls = $this->calls();
        $this->assertSame($this->expected('ps'), end($calls));
    }

    /** @return array<string, array{list<string>}> */
    public static function blockedCommands(): array
    {
        return [
            'down -v' => [['down', '-v']],
            'down --volumes' => [['down', '--volumes']],
            'down --volumes=true' => [['down', '--volumes=true']],
            'down -vt combined' => [['down', '-vt', '10']],
            'down -tv combined' => [['down', '-tv']],
            'down after other flags' => [['down', '-t', '10', '-v']],
            'down --rmi all' => [['down', '--rmi', 'all']],
            'down --rmi=local' => [['down', '--rmi=local']],
            'down --remove-orphans' => [['down', '--remove-orphans']],
            'rm -v' => [['rm', '-v']],
            'rm' => [['rm', '-f']],
            'up -V' => [['up', '-V']],
            'up --renew-anon-volumes' => [['up', '--renew-anon-volumes']],
            'up -dV combined' => [['up', '-dV']],
            'up -Vd combined' => [['up', '-Vd']],
            'up -d --build -V' => [['up', '-d', '--build', '-V']],
            'config --volumes' => [['config', '--volumes']],
            'override file' => [['-f', 'other.yaml', 'up']],
            'override file long' => [['--file', 'other.yaml', 'up']],
            'override file inline' => [['--file=other.yaml', 'up']],
            'override project' => [['-p', 'foodorder', 'up']],
            'override project long' => [['--project-name', 'foodorder', 'up']],
            'override env file' => [['--env-file', '/dev/null', 'up']],
            'override project directory' => [['--project-directory', '/tmp', 'up']],
            'override profile' => [['--profile', 'x', 'up']],
            'exec' => [['exec', 'app', 'sh']],
            'run' => [['run', '--rm', 'app', 'sh']],
            'cp' => [['cp', 'app:/data/database.sqlite', '.']],
            'kill' => [['kill']],
            'unknown subcommand' => [['frobnicate']],
            'empty' => [[]],
            'system prune' => [['system', 'prune']],
            'volume rm' => [['volume', 'rm', 'foodorder-prod_sqlite_data']],
            'unknown service' => [['up', '-d', 'foodorder']],
            'unknown flag' => [['up', '--abort-on-container-exit-and-more']],
            'logs unknown flag' => [['logs', '--follow-and-delete']],
            'tail with injection' => [['logs', '--tail', '10;rm']],
            'subshell argument' => [['logs', '$(id)']],
        ];
    }

    /** @param list<string> $arguments */
    #[DataProvider('blockedCommands')]
    public function test_destructive_and_arbitrary_commands_are_rejected_before_docker_runs(array $arguments): void
    {
        $process = $this->dcp($arguments);

        $this->assertNotSame(0, $process->getExitCode(), implode(' ', $arguments));
        $this->assertNotSame('', $process->getErrorOutput());
        $this->assertSame([], $this->calls(), 'Docker must not be invoked for a rejected command.');
    }

    public function test_inherited_compose_selectors_cannot_redirect_the_stack(): void
    {
        $process = $this->dcp(['ps'], ['COMPOSE_FILE' => '/tmp/other.yaml', 'COMPOSE_PROJECT_NAME' => 'foodorder', 'COMPOSE_PROFILES' => 'dev']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('COMPOSE_FILE=unset COMPOSE_PROJECT_NAME=unset COMPOSE_PROFILES=unset', implode("\n", file($this->log)));
    }

    public function test_exported_configuration_that_conflicts_with_the_env_file_is_rejected(): void
    {
        foreach (['APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'FOODORDER_ENV_PROD' => '0', 'APP_KEY' => 'base64:other'] as $name => $value) {
            $process = $this->dcp(['ps'], [$name => $value]);

            $this->assertNotSame(0, $process->getExitCode(), $name);
            $this->assertStringContainsString($name, $process->getErrorOutput());
        }
        $this->assertSame([], $this->calls());
    }

    public function test_a_missing_or_invalid_production_env_file_is_rejected(): void
    {
        unlink($this->root.'/.env.prod');
        $this->assertNotSame(0, $this->dcp(['ps'])->getExitCode());

        foreach ([['FOODORDER_ENV_PROD' => '0'], ['APP_ENV' => 'local'], ['APP_DEBUG' => 'true'], ['DB_CONNECTION' => 'mysql']] as $override) {
            $this->writeEnvFile($override);
            $process = $this->dcp(['ps']);

            $this->assertNotSame(0, $process->getExitCode(), json_encode($override));
        }
        $this->assertSame([], $this->calls());
    }

    public function test_a_missing_compose_file_is_rejected(): void
    {
        unlink($this->root.'/compose.prod.yaml');

        $this->assertNotSame(0, $this->dcp(['ps'])->getExitCode());
        $this->assertSame([], $this->calls());
    }

    public function test_init_key_fills_an_empty_app_key_without_printing_it(): void
    {
        $process = $this->dcp(['init-key']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', file_get_contents($this->root.'/.env.prod'));
        $this->assertStringNotContainsString('base64:', $process->getOutput().$process->getErrorOutput());
    }

    public function test_init_key_never_replaces_an_existing_key(): void
    {
        $this->writeEnvFile(['APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $before = file_get_contents($this->root.'/.env.prod');

        $process = $this->dcp(['init-key']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame($before, file_get_contents($this->root.'/.env.prod'));
        $this->assertSame([], $this->calls());
    }

    public function test_the_script_is_valid_bash_executable_and_free_of_eval(): void
    {
        $path = $this->projectPath('scripts/prod/dcp.sh');

        $this->assertSame(0, $this->shell(['bash', '-n', $path])->getExitCode());
        $this->assertTrue(is_executable($path), 'dcp.sh must be executable.');
        $this->assertDoesNotMatchRegularExpression('/\beval\b/', file_get_contents($path));
        $this->assertStringContainsString('set -euo pipefail', file_get_contents($path));
    }
}
