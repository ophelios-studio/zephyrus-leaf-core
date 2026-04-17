<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Content\MarkdownParser;
use Leaf\Content\ParsedMarkdown;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownParserTest extends TestCase
{
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MarkdownParser();
    }

    #[Test]
    public function parseReturnsParsedMarkdown(): void
    {
        $result = $this->parser->parse('# Hello World');

        $this->assertInstanceOf(ParsedMarkdown::class, $result);
        $this->assertStringContainsString('Hello World', $result->html);
    }

    #[Test]
    public function parseExtractsFrontMatter(): void
    {
        $markdown = <<<'MD'
---
title: My Page
order: 3
---

# My Page

Some content here.
MD;

        $result = $this->parser->parse($markdown);

        $this->assertSame('My Page', $result->meta('title'));
        $this->assertSame(3, $result->meta('order'));
        $this->assertStringContainsString('Some content here.', $result->html);
    }

    #[Test]
    public function parseGeneratesTableOfContents(): void
    {
        $markdown = <<<'MD'
# Title

## Getting Started

### Installation

## Usage

### Configuration
MD;

        $result = $this->parser->parse($markdown);

        $this->assertNotEmpty($result->toc);

        $tocTexts = array_column($result->toc, 'text');
        $this->assertContains('Getting Started', $tocTexts);
        $this->assertContains('Installation', $tocTexts);
        $this->assertContains('Usage', $tocTexts);
        $this->assertContains('Configuration', $tocTexts);
    }

    #[Test]
    public function parseTocIncludesLevels(): void
    {
        $markdown = <<<'MD'
## Level 2 Heading

### Level 3 Heading

#### Level 4 Heading
MD;

        $result = $this->parser->parse($markdown);

        $levels = array_column($result->toc, 'level');
        $this->assertContains(2, $levels);
        $this->assertContains(3, $levels);
        $this->assertContains(4, $levels);
    }

    #[Test]
    public function parseTocExcludesH1(): void
    {
        $markdown = '# Top Level Heading';
        $result = $this->parser->parse($markdown);

        $this->assertEmpty($result->toc);
    }

    #[Test]
    public function parseGeneratesHeadingPermalinks(): void
    {
        $markdown = '## My Section';
        $result = $this->parser->parse($markdown);

        $this->assertStringContainsString('heading-permalink', $result->html);
        $this->assertStringContainsString('my-section', $result->html);
    }

    #[Test]
    public function parseSupportsGfmTables(): void
    {
        $markdown = <<<'MD'
| Column A | Column B |
|----------|----------|
| Value 1  | Value 2  |
MD;

        $result = $this->parser->parse($markdown);

        $this->assertStringContainsString('<table>', $result->html);
        $this->assertStringContainsString('Column A', $result->html);
        $this->assertStringContainsString('Value 1', $result->html);
    }

    #[Test]
    public function parseHandlesEmptyFrontMatter(): void
    {
        $result = $this->parser->parse('Just some text without front matter.');

        $this->assertSame([], $result->frontMatter);
        $this->assertStringContainsString('Just some text', $result->html);
    }

    #[Test]
    public function extractFrontMatterReturnsMetaOnly(): void
    {
        $markdown = <<<'MD'
---
title: Quick Extract
order: 1
---

# Quick Extract

Body text.
MD;

        $meta = $this->parser->extractFrontMatter($markdown);

        $this->assertSame('Quick Extract', $meta['title']);
        $this->assertSame(1, $meta['order']);
    }

    #[Test]
    public function extractFrontMatterReturnsEmptyForNoFrontMatter(): void
    {
        $meta = $this->parser->extractFrontMatter('Just plain text.');

        $this->assertSame([], $meta);
    }

    #[Test]
    public function parseSupportsFencedCodeBlocks(): void
    {
        $markdown = <<<'MD'
```php
echo "hello";
```
MD;

        $result = $this->parser->parse($markdown);

        $this->assertStringContainsString('<code', $result->html);
        $this->assertStringContainsString('echo', $result->html);
    }
}
