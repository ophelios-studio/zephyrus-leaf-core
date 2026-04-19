<?php

declare(strict_types=1);

namespace Leaf\Content;

/**
 * Builds a JSON search index from all documentation pages.
 *
 * Locale-aware: when a non-default locale is set via setLocale(), the index
 * includes locale-scoped files from `content/<locale>/<section>/<slug>.md`,
 * falling back to `content/<section>/<slug>.md` for untranslated pages. Each
 * locale should produce its own `search.json` placed under the locale's
 * output directory.
 */
final class SearchIndexBuilder
{
    private string $currentLocale = '';
    private string $defaultLocale = '';
    /** @var list<string> */
    private array $supportedLocales = [];

    public function __construct(
        private readonly string $contentDirectory,
        private readonly MarkdownParser $parser,
        private readonly string $baseUrl = '',
    ) {
    }

    /**
     * @param list<string> $supportedLocales
     */
    public function setLocale(string $currentLocale, string $defaultLocale = '', array $supportedLocales = []): void
    {
        $this->currentLocale = $currentLocale;
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
    }

    /**
     * @return list<array{title: string, section: string, url: string, excerpt: string, headings: list<string>}>
     */
    public function build(): array
    {
        $index = [];
        if (!is_dir($this->contentDirectory)) {
            return $index;
        }

        foreach ($this->collectSectionFiles() as $section => $slugs) {
            foreach ($slugs as $slug => $filePath) {
                $content = file_get_contents($filePath);
                if ($content === false) {
                    continue;
                }
                $parsed = $this->parser->parse($content);
                $title = $parsed->meta('title', ucwords(str_replace('-', ' ', $slug)));

                $plaintext = strip_tags($parsed->html);
                $plaintext = preg_replace('/\s+/', ' ', trim($plaintext));

                $excerpt = mb_strlen($plaintext) > 300
                    ? mb_substr($plaintext, 0, 300) . '...'
                    : $plaintext;

                $headings = array_map(fn (array $h) => $h['text'], $parsed->toc);

                $index[] = [
                    'title' => $title,
                    'section' => $section,
                    'url' => rtrim($this->baseUrl, '/') . "/{$section}/{$slug}",
                    'excerpt' => $excerpt,
                    'headings' => $headings,
                ];
            }
        }
        return $index;
    }

    /**
     * Union of default + locale-scoped markdown files, locale wins.
     *
     * @return array<string, array<string, string>>
     */
    private function collectSectionFiles(): array
    {
        $result = [];
        $this->scanInto($this->contentDirectory, $result, excludeNames: $this->supportedLocales);
        if ($this->currentLocale !== ''
            && $this->defaultLocale !== ''
            && $this->currentLocale !== $this->defaultLocale
        ) {
            $localeDir = $this->contentDirectory . '/' . $this->currentLocale;
            if (is_dir($localeDir)) {
                $this->scanInto($localeDir, $result, excludeNames: []);
            }
        }
        return $result;
    }

    /**
     * @param array<string, array<string, string>> $result
     * @param list<string> $excludeNames
     */
    private function scanInto(string $root, array &$result, array $excludeNames = []): void
    {
        $entries = scandir($root) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (in_array($entry, $excludeNames, true)) {
                continue;
            }
            $sectionDir = $root . '/' . $entry;
            if (!is_dir($sectionDir)) {
                continue;
            }
            foreach (glob($sectionDir . '/*.md') ?: [] as $file) {
                $slug = basename($file, '.md');
                $result[$entry][$slug] = $file;
            }
        }
    }
}
