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

    /** Absolute URL of the generated sitemap.xml, without a doubled slash. */
    public function sitemapUrl(): string
    {
        return $this->baseUrl . '/sitemap.xml';
    }

    /**
     * @param list<string> $pages Relative paths (e.g. ["/", "/blog", "/blog/my-post"])
     */
    public function generate(array $pages): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->listable($pages) as $path) {
            $url = $this->baseUrl . ($path === '/' ? '/' : rtrim($path, '/') . '/');
            $xml .= '  <url><loc>' . $this->escape($url) . '</loc></url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";
        file_put_contents($this->outputDirectory . '/sitemap.xml', $xml);
    }

    /**
     * Lists every page in every locale.
     *
     * @param list<string> $pages Locale-agnostic paths
     * @param list<string> $locales Locale codes (e.g. ["en", "fr", "ar"])
     * @param string $defaultLocale Used for x-default hreflang
     */
    public function generateMultiLocale(array $pages, array $locales, string $defaultLocale): void
    {
        $this->generateFromLocalePages(array_fill_keys($locales, $pages), $defaultLocale);
    }

    /**
     * Lists each page once per locale that built it, with hreflang alternates to those locales only.
     *
     * @param array<string, list<string>> $pagesByLocale Locale code => locale-agnostic paths built in that locale
     * @param string $defaultLocale Used for x-default hreflang
     */
    public function generateFromLocalePages(array $pagesByLocale, string $defaultLocale): void
    {
        $listed = array_map($this->listable(...), $pagesByLocale);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($listed as $locale => $pages) {
            foreach ($pages as $path) {
                $suffix = $path === '/' ? '/' : rtrim($path, '/') . '/';
                $loc = $this->localeUrl((string) $locale, $suffix, $defaultLocale);

                $xml .= "  <url>\n";
                $xml .= '    <loc>' . $this->escape($loc) . '</loc>' . "\n";

                foreach ($listed as $altLocale => $altPages) {
                    if (!in_array($path, $altPages, true)) {
                        continue;
                    }
                    $altHref = $this->localeUrl((string) $altLocale, $suffix, $defaultLocale);
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $this->escape((string) $altLocale) . '" href="' . $this->escape($altHref) . '"/>' . "\n";
                }

                if (in_array($path, $listed[$defaultLocale] ?? [], true)) {
                    $defaultHref = $this->localeUrl($defaultLocale, $suffix, $defaultLocale);
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->escape($defaultHref) . '"/>' . "\n";
                }
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>' . "\n";
        file_put_contents($this->outputDirectory . '/sitemap.xml', $xml);
    }

    /**
     * @param list<string> $pages
     * @return list<string>
     */
    private function listable(array $pages): array
    {
        return array_values(array_filter($pages, static fn (string $path): bool => rtrim($path, '/') !== '/404'));
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
