<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Config\LeafConfig;
use Leaf\Content\ContentLoader;
use Leaf\Content\MarkdownParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers ContentLoader's locale-aware file resolution.
 *
 * Layout used across tests:
 *
 *   content/
 *     getting-started/
 *       intro.md           (English default, body = "Default intro")
 *       installation.md    (English only)
 *     fr/
 *       getting-started/
 *         intro.md         (French override, body = "French intro")
 *       concepts/
 *         overview.md      (French-only section)
 */
final class ContentLoaderLocaleTest extends TestCase
{
    private string $root;
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-locale-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/getting-started', 0o755, true);
        mkdir($this->root . '/fr/getting-started', 0o755, true);
        mkdir($this->root . '/fr/concepts', 0o755, true);

        file_put_contents($this->root . '/getting-started/intro.md', <<<MD
---
title: Introduction
order: 1
---

Default intro
MD);
        file_put_contents($this->root . '/getting-started/installation.md', <<<MD
---
title: Installation
order: 2
---

Default installation, English only.
MD);
        file_put_contents($this->root . '/fr/getting-started/intro.md', <<<MD
---
title: Introduction
order: 1
---

French intro
MD);
        file_put_contents($this->root . '/fr/concepts/overview.md', <<<MD
---
title: Vue d'ensemble
order: 1
---

Contenu seulement en français.
MD);

        $this->parser = new MarkdownParser();
    }

    protected function tearDown(): void
    {
        $this->recursiveRemove($this->root);
    }

    private function loader(LeafConfig $config): ContentLoader
    {
        return new ContentLoader($this->root, $this->parser, $config);
    }

    private function config(array $sections = []): LeafConfig
    {
        return LeafConfig::fromArray([
            'name' => 'Test',
            'sections' => $sections,
        ]);
    }

    #[Test]
    public function defaultLocaleReadsRootContent(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('en', 'en', ['en', 'fr']);

        $page = $loader->getPage('getting-started', 'intro');
        $this->assertNotNull($page);
        $this->assertStringContainsString('Default intro', $page->html);
    }

    #[Test]
    public function nonDefaultLocalePrefersLocaleFile(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('fr', 'en', ['en', 'fr']);

        $page = $loader->getPage('getting-started', 'intro');
        $this->assertNotNull($page);
        $this->assertStringContainsString('French intro', $page->html);
    }

    #[Test]
    public function nonDefaultLocaleFallsBackWhenTranslationMissing(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('fr', 'en', ['en', 'fr']);

        // installation.md does NOT exist under fr/, expect English fallback.
        $page = $loader->getPage('getting-started', 'installation');
        $this->assertNotNull($page);
        $this->assertStringContainsString('English only', $page->html);
    }

    #[Test]
    public function nonDefaultLocaleSurfacesLocaleOnlySection(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('fr', 'en', ['en', 'fr']);

        $page = $loader->getPage('concepts', 'overview');
        $this->assertNotNull($page, 'French-only section should be reachable');
        $this->assertStringContainsString('seulement en français', $page->html);
    }

    #[Test]
    public function defaultLocaleDoesNotSeeLocaleOnlySection(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('en', 'en', ['en', 'fr']);

        // concepts/overview.md exists only under fr/, not in the English build.
        $this->assertNull($loader->getPage('concepts', 'overview'));
    }

    #[Test]
    public function sidebarIsUnionedForNonDefaultLocale(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('fr', 'en', ['en', 'fr']);

        $sidebar = $loader->getSidebar();
        // Flatten section slugs
        $slugsBySection = [];
        foreach ($sidebar as $section) {
            $slugsBySection[$section['title']] = array_map(fn ($i) => $i['slug'], $section['items']);
        }
        // French reader sees getting-started (English + French) AND concepts (French-only).
        $this->assertArrayHasKey('Getting Started', $slugsBySection);
        $this->assertContains('intro', $slugsBySection['Getting Started']);
        $this->assertContains('installation', $slugsBySection['Getting Started']);
        $this->assertArrayHasKey('Concepts', $slugsBySection);
        $this->assertContains('overview', $slugsBySection['Concepts']);
    }

    #[Test]
    public function defaultLocaleDoesNotLeakLocaleDirsAsSections(): void
    {
        $loader = $this->loader($this->config());
        $loader->setLocale('en', 'en', ['en', 'fr']);

        foreach ($loader->getSidebar() as $section) {
            $this->assertNotSame('Fr', $section['title'], 'Locale dir leaked as section');
        }
    }

    #[Test]
    public function setLocaleInvalidatesSidebarCache(): void
    {
        $loader = $this->loader($this->config());

        $loader->setLocale('en', 'en', ['en', 'fr']);
        $englishSidebar = $loader->getSidebar();
        $englishSections = array_map(fn ($s) => $s['title'], $englishSidebar);
        $this->assertNotContains('Concepts', $englishSections);

        $loader->setLocale('fr', 'en', ['en', 'fr']);
        $frenchSidebar = $loader->getSidebar();
        $frenchSections = array_map(fn ($s) => $s['title'], $frenchSidebar);
        $this->assertContains('Concepts', $frenchSections);
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
