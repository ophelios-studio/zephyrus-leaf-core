<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Content\MarkdownParser;
use Leaf\Content\SearchIndexBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchIndexBuilderTest extends TestCase
{
    private string $fixtureDir;
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/leaf-test-search-' . uniqid();
        mkdir($this->fixtureDir . '/getting-started', 0755, true);

        file_put_contents($this->fixtureDir . '/getting-started/introduction.md', <<<'MD'
---
title: Introduction
order: 1
---

# Introduction

Welcome to the documentation. This is the first page.

## Getting Started

Follow these steps to get started.
MD);

        file_put_contents($this->fixtureDir . '/getting-started/installation.md', <<<'MD'
---
title: Installation
order: 2
---

# Installation

Run composer require to install.
MD);

        $this->parser = new MarkdownParser();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    #[Test]
    public function buildReturnsIndexEntries(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $this->assertCount(2, $index);
    }

    #[Test]
    public function entryContainsExpectedFields(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $introIndex = array_search('Introduction', $titles, true);

        $this->assertNotFalse($introIndex);
        $entry = $index[$introIndex];

        $this->assertSame('Introduction', $entry['title']);
        $this->assertSame('getting-started', $entry['section']);
        $this->assertSame('/getting-started/introduction', $entry['url']);
        $this->assertNotEmpty($entry['excerpt']);
        $this->assertIsArray($entry['headings']);
    }

    #[Test]
    public function excerptIsTruncated(): void
    {
        $longContent = "---\ntitle: Long\norder: 1\n---\n\n# Long\n\n" . str_repeat('Lorem ipsum dolor sit amet. ', 50);
        file_put_contents($this->fixtureDir . '/getting-started/long.md', $longContent);

        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $longIndex = array_search('Long', $titles, true);
        $entry = $index[$longIndex];

        $this->assertLessThanOrEqual(303, mb_strlen($entry['excerpt'])); // 300 + "..."
    }

    #[Test]
    public function headingsAreExtracted(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $introIndex = array_search('Introduction', $titles, true);
        $entry = $index[$introIndex];

        $this->assertContains('Getting Started', $entry['headings']);
    }

    #[Test]
    public function returnsEmptyForNonExistentDirectory(): void
    {
        $builder = new SearchIndexBuilder('/nonexistent/path', $this->parser);
        $index = $builder->build();

        $this->assertSame([], $index);
    }

    #[Test]
    public function returnsEmptyForEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/leaf-test-empty-search-' . uniqid();
        mkdir($emptyDir, 0755, true);

        $builder = new SearchIndexBuilder($emptyDir, $this->parser);
        $index = $builder->build();

        $this->assertSame([], $index);

        rmdir($emptyDir);
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
