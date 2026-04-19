<?php

declare(strict_types=1);

namespace Leaf\Content;

use Leaf\Config\LeafConfig;

/**
 * Loads documentation content from markdown files and provides navigation
 * structure (sidebar, prev/next links).
 *
 * Multi-locale content resolution:
 *
 *   content/                         <- default-locale content (always read)
 *     getting-started/intro.md
 *   content/<locale>/                <- non-default locale overrides
 *     getting-started/intro.md
 *
 * When the loader's currentLocale differs from its defaultLocale, file
 * lookups first probe `content/<currentLocale>/<section>/<slug>.md` and
 * fall back to `content/<section>/<slug>.md` when no translation exists.
 * Sections can be locale-specific too: `content/fr/concepts/` that has no
 * counterpart at `content/concepts/` appears only in the French sidebar.
 *
 * Call setLocale() between builds to switch the resolver; the sidebar and
 * flat-page cache is invalidated automatically.
 */
final class ContentLoader
{
    /** @var list<array{section: string, slug: string, title: string, url: string}> */
    private array $flatPages = [];

    /** @var array<string, array{title: string, items: list<array{title: string, url: string, section: string, slug: string}>}> */
    private array $sidebar = [];

    private bool $loaded = false;

    private string $currentLocale = '';
    private string $defaultLocale = '';
    /** @var list<string> */
    private array $supportedLocales = [];

    public function __construct(
        private readonly string $contentDirectory,
        private readonly MarkdownParser $parser,
        private readonly LeafConfig $config,
    ) {
    }

    /**
     * Set the locale context for subsequent file lookups and sidebar scans.
     * Pass the list of supported locales so their dirs under `content/` can
     * be treated as locale scopes (not regular sections).
     *
     * @param list<string> $supportedLocales
     */
    public function setLocale(string $currentLocale, string $defaultLocale = '', array $supportedLocales = []): void
    {
        $this->currentLocale = $currentLocale;
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
        $this->loaded = false;
        $this->flatPages = [];
        $this->sidebar = [];
    }

    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * @return list<array{title: string, items: list<array{title: string, url: string}>}>
     */
    public function getSidebar(): array
    {
        $this->ensureLoaded();
        return array_values($this->sidebar);
    }

    public function getPage(string $section, string $slug): ?ParsedMarkdown
    {
        $path = $this->resolveFilePath($section, $slug);
        if ($path === null) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return $this->parser->parse($content);
    }

    /**
     * @return array{title: string, url: string}|null
     */
    public function getPreviousPage(string $section, string $slug): ?array
    {
        $this->ensureLoaded();
        $url = $this->baseUrl() . "/{$section}/{$slug}";

        foreach ($this->flatPages as $i => $page) {
            if ($page['url'] === $url && $i > 0) {
                return [
                    'title' => $this->flatPages[$i - 1]['title'],
                    'url' => $this->flatPages[$i - 1]['url'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{title: string, url: string}|null
     */
    public function getNextPage(string $section, string $slug): ?array
    {
        $this->ensureLoaded();
        $url = $this->baseUrl() . "/{$section}/{$slug}";

        foreach ($this->flatPages as $i => $page) {
            if ($page['url'] === $url && isset($this->flatPages[$i + 1])) {
                return [
                    'title' => $this->flatPages[$i + 1]['title'],
                    'url' => $this->flatPages[$i + 1]['url'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{section: string, slug: string, title: string, url: string}>
     */
    public function getAllPages(): array
    {
        $this->ensureLoaded();
        return $this->flatPages;
    }

    public function getFirstPageUrl(): string
    {
        $this->ensureLoaded();
        return $this->flatPages[0]['url'] ?? $this->baseUrl() . '/getting-started/introduction';
    }

    private function baseUrl(): string
    {
        return rtrim($this->config->baseUrl, '/');
    }

    /**
     * Resolve a section+slug pair to an on-disk file, preferring the current
     * locale's override when non-default.
     */
    private function resolveFilePath(string $section, string $slug): ?string
    {
        if ($this->isNonDefaultLocale()) {
            $localePath = sprintf(
                '%s/%s/%s/%s.md',
                $this->contentDirectory,
                $this->currentLocale,
                $section,
                $slug,
            );
            if (is_file($localePath)) {
                return $localePath;
            }
        }
        $defaultPath = $this->contentDirectory . '/' . $section . '/' . $slug . '.md';
        return is_file($defaultPath) ? $defaultPath : null;
    }

    private function isNonDefaultLocale(): bool
    {
        return $this->currentLocale !== ''
            && $this->defaultLocale !== ''
            && $this->currentLocale !== $this->defaultLocale;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->flatPages = [];
        $this->sidebar = [];

        if (!is_dir($this->contentDirectory)) {
            $this->loaded = true;
            return;
        }

        $sectionFiles = $this->collectSectionFiles();
        $configSections = $this->config->sections;

        // Honor configured section order when present; otherwise alphabetical.
        if ($configSections !== []) {
            $orderedSections = array_keys($configSections);
        } else {
            $orderedSections = array_keys($sectionFiles);
            sort($orderedSections);
        }

        foreach ($orderedSections as $section) {
            if (!isset($sectionFiles[$section])) {
                continue;
            }
            $pages = [];
            foreach ($sectionFiles[$section] as $slug => $filePath) {
                $frontMatter = $this->parser->extractFrontMatter(file_get_contents($filePath));
                $pages[] = [
                    'section' => $section,
                    'slug' => $slug,
                    'title' => $frontMatter['title'] ?? $this->slugToTitle($slug),
                    'order' => $frontMatter['order'] ?? 99,
                    'url' => $this->baseUrl() . "/{$section}/{$slug}",
                ];
            }
            usort($pages, fn (array $a, array $b) => $a['order'] <=> $b['order']);

            $sectionLabel = $configSections[$section] ?? $this->slugToTitle($section);
            $sidebarItems = array_map(fn (array $page) => [
                'title' => $page['title'],
                'url' => $page['url'],
                'section' => $page['section'],
                'slug' => $page['slug'],
            ], $pages);

            $this->sidebar[$section] = [
                'title' => $sectionLabel,
                'items' => $sidebarItems,
            ];

            foreach ($pages as $page) {
                $this->flatPages[] = [
                    'section' => $page['section'],
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'url' => $page['url'],
                ];
            }
        }

        $this->loaded = true;
    }

    /**
     * Walk both the default content tree and the current locale's tree,
     * returning the union as section => [slug => absolute file path] where
     * the locale's file wins on collision.
     *
     * @return array<string, array<string, string>>
     */
    private function collectSectionFiles(): array
    {
        $result = [];
        $this->scanInto($this->contentDirectory, $result, excludeNames: $this->localeDirNames());
        if ($this->isNonDefaultLocale()) {
            $localeDir = $this->contentDirectory . '/' . $this->currentLocale;
            if (is_dir($localeDir)) {
                $this->scanInto($localeDir, $result, excludeNames: []);
            }
        }
        return $result;
    }

    /**
     * Populate $result[$section][$slug] = $filePath by scanning $root.
     * Entries whose name is in $excludeNames at the top level are skipped.
     * Later calls overwrite earlier entries for the same section/slug.
     *
     * @param array<string, array<string, string>> $result
     * @param list<string> $excludeNames
     */
    private function scanInto(string $root, array &$result, array $excludeNames = []): void
    {
        if (!is_dir($root)) {
            return;
        }
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

    /**
     * Names under content/ that should be treated as locale scopes rather
     * than sections (i.e. every supported locale).
     *
     * @return list<string>
     */
    private function localeDirNames(): array
    {
        return $this->supportedLocales;
    }

    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
