<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/** Runs shell scripts with a fully controlled environment (false removes a variable). */
trait RunsShell
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    protected function shell(array $command, array $env = [], ?string $cwd = null): Process
    {
        $process = new Process($command, $cwd ?? dirname(__DIR__, 2), $env);
        $process->run();

        return $process;
    }

    protected function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).'/'.$relative;
    }
}
