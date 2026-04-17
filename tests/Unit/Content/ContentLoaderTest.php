<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Config\LeafConfig;
use Leaf\Content\ContentLoader;
use Leaf\Content\MarkdownParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentLoaderTest extends TestCase
{
    private string $fixtureDir;
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/leaf-test-content-' . uniqid();
        mkdir($this->fixtureDir . '/getting-started', 0755, true);
        mkdir($this->fixtureDir . '/guides', 0755, true);

        file_put_contents($this->fixtureDir . '/getting-started/introduction.md', <<<'MD'
---
title: Introduction
order: 1
---

# Introduction

Welcome to the docs.
MD);

        file_put_contents($this->fixtureDir . '/getting-started/installation.md', <<<'MD'
---
title: Installation
order: 2
---

# Installation

Install via composer.
MD);

        file_put_contents($this->fixtureDir . '/guides/deployment.md', <<<'MD'
---
title: Deployment
order: 1
---

# Deployment

Deploy your app.
MD);

        $this->parser = new MarkdownParser();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    #[Test]
    public function getSidebarReturnsOrderedSections(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => [
                'getting-started' => 'Getting Started',
                'guides' => 'Guides',
            ],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);
        $sidebar = $loader->getSidebar();

        $this->assertCount(2, $sidebar);
        $this->assertSame('Getting Started', $sidebar[0]['title']);
        $this->assertSame('Guides', $sidebar[1]['title']);
    }

    #[Test]
    public function getSidebarPagesAreOrderedByFrontMatter(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);
        $sidebar = $loader->getSidebar();

        $items = $sidebar[0]['items'];
        $this->assertSame('Introduction', $items[0]['title']);
        $this->assertSame('Installation', $items[1]['title']);
    }

    #[Test]
    public function getPageReturnsContent(): void
    {
        $config = LeafConfig::fromArray([]);
        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $page = $loader->getPage('getting-started', 'introduction');

        $this->assertNotNull($page);
        $this->assertStringContainsString('Welcome to the docs', $page->html);
        $this->assertSame('Introduction', $page->meta('title'));
    }

    #[Test]
    public function getPageReturnsNullForMissing(): void
    {
        $config = LeafConfig::fromArray([]);
        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $page = $loader->getPage('getting-started', 'nonexistent');

        $this->assertNull($page);
    }

    #[Test]
    public function getPreviousPageReturnsNullForFirstPage(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $prev = $loader->getPreviousPage('getting-started', 'introduction');
        $this->assertNull($prev);
    }

    #[Test]
    public function getPreviousPageReturnsPrevPage(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $prev = $loader->getPreviousPage('getting-started', 'installation');
        $this->assertNotNull($prev);
        $this->assertSame('Introduction', $prev['title']);
    }

    #[Test]
    public function getNextPageReturnsNextPage(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $next = $loader->getNextPage('getting-started', 'introduction');
        $this->assertNotNull($next);
        $this->assertSame('Installation', $next['title']);
    }

    #[Test]
    public function getNextPageCrossesSections(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $next = $loader->getNextPage('getting-started', 'installation');
        $this->assertNotNull($next);
        $this->assertSame('Deployment', $next['title']);
    }

    #[Test]
    public function getNextPageReturnsNullForLastPage(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $next = $loader->getNextPage('guides', 'deployment');
        $this->assertNull($next);
    }

    #[Test]
    public function getAllPagesReturnsFlatList(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started', 'guides' => 'Guides'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);
        $pages = $loader->getAllPages();

        $this->assertCount(3, $pages);
        $this->assertSame('Introduction', $pages[0]['title']);
        $this->assertSame('Installation', $pages[1]['title']);
        $this->assertSame('Deployment', $pages[2]['title']);
    }

    #[Test]
    public function getFirstPageUrlReturnsFirstPage(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => ['getting-started' => 'Getting Started'],
        ]);

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);

        $this->assertSame('/getting-started/introduction', $loader->getFirstPageUrl());
    }

    #[Test]
    public function getFirstPageUrlReturnsFallbackWhenEmpty(): void
    {
        $emptyDir = sys_get_temp_dir() . '/leaf-test-empty-' . uniqid();
        mkdir($emptyDir, 0755, true);

        $config = LeafConfig::fromArray([]);
        $loader = new ContentLoader($emptyDir, $this->parser, $config);

        $this->assertSame('/getting-started/introduction', $loader->getFirstPageUrl());

        rmdir($emptyDir);
    }

    #[Test]
    public function handlesNonExistentContentDirectory(): void
    {
        $config = LeafConfig::fromArray([]);
        $loader = new ContentLoader('/nonexistent/path', $this->parser, $config);

        $this->assertSame([], $loader->getSidebar());
        $this->assertSame([], $loader->getAllPages());
    }

    #[Test]
    public function autoDiscoversSectionsWhenNotConfigured(): void
    {
        $config = LeafConfig::fromArray([]); // No sections configured

        $loader = new ContentLoader($this->fixtureDir, $this->parser, $config);
        $sidebar = $loader->getSidebar();

        $this->assertCount(2, $sidebar);
        // Alphabetical order: getting-started, guides
        $titles = array_column($sidebar, 'title');
        $this->assertContains('Getting Started', $titles);
        $this->assertContains('Guides', $titles);
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
