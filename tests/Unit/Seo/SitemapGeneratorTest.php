<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Seo;

use Leaf\Seo\SitemapGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SitemapGeneratorTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/leaf-test-sitemap-' . uniqid();
        mkdir($this->outputDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->outputDir);
    }

    #[Test]
    public function theSitemapUrlHasASingleSlashWhateverTheProductionUrlEndsWith(): void
    {
        $withSlash = new SitemapGenerator('https://example.com/', $this->outputDir);
        $withoutSlash = new SitemapGenerator('https://example.com', $this->outputDir);

        self::assertSame('https://example.com/sitemap.xml', $withSlash->sitemapUrl());
        self::assertSame('https://example.com/sitemap.xml', $withoutSlash->sitemapUrl());
    }

    #[Test]
    public function pageUrlsHaveASingleSlashWhenTheProductionUrlEndsWithASlash(): void
    {
        (new SitemapGenerator('https://example.com/', $this->outputDir))->generate(['/', '/blog']);

        $xml = (string) file_get_contents($this->outputDir . '/sitemap.xml');
        self::assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.com/blog/</loc>', $xml);
        self::assertStringNotContainsString('com//', $xml);
    }
}
