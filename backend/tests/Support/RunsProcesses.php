<?php

namespace Tests\Support;

use RuntimeException;

trait RunsProcesses
{
    /**
     * Runs a command from the project root with the current environment plus overrides.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @return array{int, string} exit code and combined output
     */
    protected function runProcess(array $command, array $env = []): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            array_merge(getenv(), ['LOG_CHANNEL' => 'stderr', 'NO_COLOR' => '1'], $env),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start '.implode(' ', $command));
        }

        $output = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
