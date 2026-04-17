<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Watches directories for file changes using modification timestamps.
 *
 * Computes an MD5 hash of all file mtimes in the watched directories.
 * When the hash changes, files have been added, removed, or modified.
 */
final class FileWatcher
{
    /** @var list<string> */
    private array $directories;

    /**
     * @param list<string> $directories Absolute paths to watch.
     */
    public function __construct(array $directories)
    {
        $this->directories = $directories;
    }

    /**
     * Compute a hash representing the current state of all watched files.
     */
    public function hash(): string
    {
        $mtimes = '';

        foreach ($this->directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $mtimes .= $file->getMTime();
                }
            }
        }

        return md5($mtimes);
    }
}
