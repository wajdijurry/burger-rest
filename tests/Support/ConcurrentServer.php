<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * Spawns a real `php artisan serve` process against the test database so
 * Concurrency tests exercise genuinely independent OS processes and
 * database connections racing each other - not PHPUnit's single in-process
 * call stack, and not a transaction any one of them could see into.
 *
 * `APP_ENV=testing` makes the spawned process load .env.testing, which
 * points at the same postgres_test database as phpunit.xml, with
 * PHP_CLI_SERVER_WORKERS set high enough that PHP's built-in server
 * actually handles concurrent requests in parallel instead of queueing
 * them one at a time.
 */
trait ConcurrentServer
{
    private ?Process $concurrentServerProcess = null;

    protected function startConcurrentServer(): string
    {
        $port = random_int(8100, 8999);

        $this->concurrentServerProcess = new Process(
            command: ['php', 'artisan', 'serve', '--host=127.0.0.1', "--port={$port}"],
            cwd: base_path(),
            env: ['APP_ENV' => 'testing', 'PHP_CLI_SERVER_WORKERS' => '8'],
        );
        $this->concurrentServerProcess->start();

        $baseUrl = "http://127.0.0.1:{$port}";
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            if (! $this->concurrentServerProcess->isRunning()) {
                $this->fail('Concurrent test server exited early: '.$this->concurrentServerProcess->getErrorOutput());
            }

            $socket = @fsockopen('127.0.0.1', $port, timeout: 1);
            if ($socket) {
                fclose($socket);

                return $baseUrl;
            }

            usleep(100_000);
        }

        $this->fail('Concurrent test server did not become ready in time.');
    }

    protected function stopConcurrentServer(): void
    {
        $this->concurrentServerProcess?->stop(3);
        $this->concurrentServerProcess = null;
    }
}
