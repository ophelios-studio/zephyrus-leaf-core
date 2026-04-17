<?php

declare(strict_types=1);

namespace Leaf\Seo;

final class RobotsGenerator
{
    public function __construct(
        private readonly string $outputDirectory,
    ) {
    }

    /**
     * @param list<string> $disallow Paths to disallow
     */
    public function generate(?string $sitemapUrl = null, array $disallow = []): void
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        if ($sitemapUrl !== null) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $sitemapUrl;
        }

        $lines[] = '';
        file_put_contents($this->outputDirectory . '/robots.txt', implode("\n", $lines));
    }
}
