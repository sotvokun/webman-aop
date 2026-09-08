<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Sotvokun\Webman\Aop\support\Config;
use Webman\Bootstrap as WebmanBootstrap;
use Workerman\Worker;

use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function trim;
use function unlink;

/** Clears generated Ray.Aop proxy classes once per Webman restart. */
final class Bootstrap implements WebmanBootstrap
{
    public static function start(?Worker $worker): void
    {
        if ($worker === null) {
            return;
        }

        $directory = Config::getClassPath();
        $generation = self::generation();
        if ($generation === '') {
            throw new RuntimeException('Unable to determine the current Webman process generation.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create AOP cache directory: {$directory}");
        }

        $lock = fopen("{$directory}.lock", 'c');
        if ($lock === false) {
            throw new RuntimeException("Unable to open AOP cache lock: {$directory}.lock");
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Unable to lock AOP cache: {$directory}.lock");
            }

            $marker = "{$directory}/.generation";
            $previousGeneration = is_file($marker) ? trim((string) file_get_contents($marker)) : '';
            if ($previousGeneration !== $generation) {
                self::clear($directory);
                if (file_put_contents($marker, $generation) === false) {
                    throw new RuntimeException("Unable to write AOP cache generation marker: {$marker}");
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function generation(): string
    {
        $pidFile = config('server.pid_file', runtime_path('webman.pid'));
        if (!is_file($pidFile)) {
            return '';
        }

        return trim((string) file_get_contents($pidFile));
    }

    private static function clear(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                if (!rmdir($file->getPathname())) {
                    throw new RuntimeException("Unable to remove AOP cache directory: {$file->getPathname()}");
                }
                continue;
            }

            if (!unlink($file->getPathname())) {
                throw new RuntimeException("Unable to remove AOP cache file: {$file->getPathname()}");
            }
        }
    }
}
