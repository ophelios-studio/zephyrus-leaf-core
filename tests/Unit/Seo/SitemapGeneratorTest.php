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

    #[Test]
    public function theNotFoundPageIsNeverListed(): void
    {
        $generator = new SitemapGenerator('https://example.com', $this->outputDir);

        $generator->generate(['/', '/404', '/blog']);
        $single = (string) file_get_contents($this->outputDir . '/sitemap.xml');
        $generator->generateFromLocalePages(['en' => ['/', '/404'], 'fr' => ['/', '/404']], 'en');
        $multi = (string) file_get_contents($this->outputDir . '/sitemap.xml');

        self::assertStringNotContainsString('404', $single);
        self::assertStringNotContainsString('404', $multi);
        self::assertStringContainsString('<loc>https://example.com/blog/</loc>', $single);
    }

    #[Test]
    public function aPageBuiltInOneLocaleOnlyIsListedWithItsOwnAlternatesOnly(): void
    {
        $generator = new SitemapGenerator('https://example.com', $this->outputDir);

        $generator->generateFromLocalePages([
            'en' => ['/', '/shared'],
            'fr' => ['/', '/shared', '/fr-only'],
        ], 'en');

        $entries = $this->entries();
        self::assertSame(['https://example.com/', 'https://example.com/shared/', 'https://example.com/fr/', 'https://example.com/fr/shared/', 'https://example.com/fr/fr-only/'], array_keys($entries));
        self::assertSame(
            ['fr' => 'https://example.com/fr/fr-only/'],
            $entries['https://example.com/fr/fr-only/'],
        );
        self::assertSame(
            ['en' => 'https://example.com/shared/', 'fr' => 'https://example.com/fr/shared/', 'x-default' => 'https://example.com/shared/'],
            $entries['https://example.com/shared/'],
        );
    }

    #[Test]
    public function theLocaleListFormStillListsEveryPageInEveryLocale(): void
    {
        (new SitemapGenerator('https://example.com', $this->outputDir))
            ->generateMultiLocale(['/', '/blog'], ['en', 'fr'], 'en');

        $entries = $this->entries();
        self::assertCount(4, $entries);
        self::assertSame(
            ['en' => 'https://example.com/blog/', 'fr' => 'https://example.com/fr/blog/', 'x-default' => 'https://example.com/blog/'],
            $entries['https://example.com/fr/blog/'],
        );
    }

    /**
     * @return array<string, array<string, string>> loc => hreflang => href
     */
    private function entries(): array
    {
        $xml = simplexml_load_file($this->outputDir . '/sitemap.xml');
        self::assertNotFalse($xml);
        $entries = [];
        foreach ($xml->url as $url) {
            $alternates = [];
            foreach ($url->children('http://www.w3.org/1999/xhtml') as $link) {
                $attributes = $link->attributes();
                $alternates[(string) $attributes['hreflang']] = (string) $attributes['href'];
            }
            $entries[(string) $url->loc] = $alternates;
        }

        return $entries;
    }
}
