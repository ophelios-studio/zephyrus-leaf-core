<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Server;

use Leaf\FileWatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileWatcherTest extends TestCase
{
    private string $watchDir;

    protected function setUp(): void
    {
        $this->watchDir = sys_get_temp_dir() . '/leaf-test-watcher-' . uniqid();
        mkdir($this->watchDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->watchDir);
    }

    #[Test]
    public function hashReturnsString(): void
    {
        file_put_contents($this->watchDir . '/test.txt', 'hello');

        $watcher = new FileWatcher([$this->watchDir]);

        $hash = $watcher->hash();
        $this->assertIsString($hash);
        $this->assertSame(32, strlen($hash)); // MD5 length
    }

    #[Test]
    public function hashChangesWhenFileIsModified(): void
    {
        file_put_contents($this->watchDir . '/test.txt', 'hello');

        $watcher = new FileWatcher([$this->watchDir]);
        $hash1 = $watcher->hash();

        // Ensure mtime changes (some filesystems have 1-second resolution).
        sleep(1);
        file_put_contents($this->watchDir . '/test.txt', 'world');

        $hash2 = $watcher->hash();
        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function hashChangesWhenFileIsAdded(): void
    {
        file_put_contents($this->watchDir . '/file1.txt', 'first');

        $watcher = new FileWatcher([$this->watchDir]);
        $hash1 = $watcher->hash();

        sleep(1);
        file_put_contents($this->watchDir . '/file2.txt', 'second');

        $hash2 = $watcher->hash();
        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function hashIsDeterministic(): void
    {
        file_put_contents($this->watchDir . '/test.txt', 'hello');

        $watcher = new FileWatcher([$this->watchDir]);

        $hash1 = $watcher->hash();
        $hash2 = $watcher->hash();
        $this->assertSame($hash1, $hash2);
    }

    #[Test]
    public function hashHandlesNonExistentDirectories(): void
    {
        $watcher = new FileWatcher(['/nonexistent/directory']);

        $hash = $watcher->hash();
        $this->assertIsString($hash);
        $this->assertSame(32, strlen($hash));
    }

    #[Test]
    public function hashHandlesEmptyDirectoryList(): void
    {
        $watcher = new FileWatcher([]);

        $hash = $watcher->hash();
        $this->assertIsString($hash);
    }

    #[Test]
    public function hashHandlesMultipleDirectories(): void
    {
        $dir2 = sys_get_temp_dir() . '/leaf-test-watcher2-' . uniqid();
        mkdir($dir2, 0755, true);

        file_put_contents($this->watchDir . '/a.txt', 'a');
        file_put_contents($dir2 . '/b.txt', 'b');

        $watcher = new FileWatcher([$this->watchDir, $dir2]);

        $hash = $watcher->hash();
        $this->assertIsString($hash);
        $this->assertSame(32, strlen($hash));

        $this->removeDirectory($dir2);
    }

    #[Test]
    public function hashScansSubdirectories(): void
    {
        mkdir($this->watchDir . '/sub', 0755, true);
        file_put_contents($this->watchDir . '/sub/nested.txt', 'nested');

        $watcher = new FileWatcher([$this->watchDir]);
        $hash1 = $watcher->hash();

        sleep(1);
        file_put_contents($this->watchDir . '/sub/nested.txt', 'updated');

        $hash2 = $watcher->hash();
        $this->assertNotSame($hash1, $hash2);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
