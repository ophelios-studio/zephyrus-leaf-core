<?php

declare(strict_types=1);

namespace Leaf\Seo;

final class SitemapGenerator
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $outputDirectory,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param list<string> $pages Relative paths (e.g. ["/", "/blog", "/blog/my-post"])
     */
    public function generate(array $pages): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $path) {
            $url = $this->baseUrl . ($path === '/' ? '/' : rtrim($path, '/') . '/');
            $xml .= '  <url><loc>' . $this->escape($url) . '</loc></url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";
        file_put_contents($this->outputDirectory . '/sitemap.xml', $xml);
    }

    /**
     * @param list<string> $pages Locale-agnostic paths
     * @param list<string> $locales Locale codes (e.g. ["en", "fr", "ar"])
     * @param string $defaultLocale Used for x-default hreflang
     */
    public function generateMultiLocale(array $pages, array $locales, string $defaultLocale): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($locales as $locale) {
            foreach ($pages as $path) {
                $suffix = $path === '/' ? '/' : rtrim($path, '/') . '/';
                $loc = $this->localeUrl($locale, $suffix, $defaultLocale);

                $xml .= "  <url>\n";
                $xml .= '    <loc>' . $this->escape($loc) . '</loc>' . "\n";

                foreach ($locales as $altLocale) {
                    $altHref = $this->localeUrl($altLocale, $suffix, $defaultLocale);
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $altLocale . '" href="' . $this->escape($altHref) . '"/>' . "\n";
                }

                $defaultHref = $this->localeUrl($defaultLocale, $suffix, $defaultLocale);
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->escape($defaultHref) . '"/>' . "\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>' . "\n";
        file_put_contents($this->outputDirectory . '/sitemap.xml', $xml);
    }

    private function localeUrl(string $locale, string $suffix, string $defaultLocale): string
    {
        if ($locale === $defaultLocale) {
            return $this->baseUrl . $suffix;
        }
        return $this->baseUrl . '/' . $locale . $suffix;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
