<?php

declare(strict_types=1);

namespace Tests\Unit;

use Leaf\PageDiscovery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageDiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-pages-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemove($this->root);
    }

    #[Test]
    public function returnsEmptyListWhenDirectoryMissing(): void
    {
        $this->assertSame([], PageDiscovery::discover($this->root));
    }

    #[Test]
    public function returnsEmptyListWhenPagesDirEmpty(): void
    {
        mkdir($this->root . '/pages', 0o755);
        $this->assertSame([], PageDiscovery::discover($this->root));
    }

    #[Test]
    public function discoversLatteFilesAsUrlPaths(): void
    {
        mkdir($this->root . '/pages', 0o755);
        touch($this->root . '/pages/about.latte');
        touch($this->root . '/pages/contact.latte');
        touch($this->root . '/pages/pricing.latte');

        $this->assertSame(
            ['/about', '/contact', '/pricing'],
            PageDiscovery::discover($this->root),
        );
    }

    #[Test]
    public function outputIsSortedDeterministically(): void
    {
        mkdir($this->root . '/pages', 0o755);
        // Create in reverse order to prove we sort the result.
        touch($this->root . '/pages/zeta.latte');
        touch($this->root . '/pages/alpha.latte');
        touch($this->root . '/pages/mu.latte');

        $this->assertSame(
            ['/alpha', '/mu', '/zeta'],
            PageDiscovery::discover($this->root),
        );
    }

    #[Test]
    public function ignoresNonLatteFiles(): void
    {
        mkdir($this->root . '/pages', 0o755);
        touch($this->root . '/pages/about.latte');
        touch($this->root . '/pages/readme.md');
        touch($this->root . '/pages/image.png');

        $this->assertSame(['/about'], PageDiscovery::discover($this->root));
    }

    #[Test]
    public function ignoresDotfiles(): void
    {
        mkdir($this->root . '/pages', 0o755);
        touch($this->root . '/pages/about.latte');
        touch($this->root . '/pages/.hidden.latte');

        $this->assertSame(['/about'], PageDiscovery::discover($this->root));
    }

    #[Test]
    public function ignoresSubdirectories(): void
    {
        mkdir($this->root . '/pages/partial', 0o755, true);
        touch($this->root . '/pages/partial/nested.latte');
        touch($this->root . '/pages/about.latte');

        $this->assertSame(['/about'], PageDiscovery::discover($this->root));
    }

    #[Test]
    public function acceptsTrailingSlashInViewsDir(): void
    {
        mkdir($this->root . '/pages', 0o755);
        touch($this->root . '/pages/about.latte');

        $this->assertSame(['/about'], PageDiscovery::discover($this->root . '/'));
    }

    private function recursiveRemove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->recursiveRemove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
