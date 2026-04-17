<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Content\ParsedMarkdown;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParsedMarkdownTest extends TestCase
{
    #[Test]
    public function propertiesAreAccessible(): void
    {
        $parsed = new ParsedMarkdown(
            html: '<h1>Hello</h1>',
            frontMatter: ['title' => 'Test', 'order' => 1],
            toc: [['id' => 'hello', 'text' => 'Hello', 'level' => 2]],
        );

        $this->assertSame('<h1>Hello</h1>', $parsed->html);
        $this->assertSame(['title' => 'Test', 'order' => 1], $parsed->frontMatter);
        $this->assertCount(1, $parsed->toc);
        $this->assertSame('hello', $parsed->toc[0]['id']);
    }

    #[Test]
    public function metaReturnsValueWhenExists(): void
    {
        $parsed = new ParsedMarkdown(
            html: '',
            frontMatter: ['title' => 'My Page', 'order' => 5],
            toc: [],
        );

        $this->assertSame('My Page', $parsed->meta('title'));
        $this->assertSame(5, $parsed->meta('order'));
    }

    #[Test]
    public function metaReturnsDefaultWhenMissing(): void
    {
        $parsed = new ParsedMarkdown(
            html: '',
            frontMatter: [],
            toc: [],
        );

        $this->assertNull($parsed->meta('title'));
        $this->assertSame('Fallback', $parsed->meta('title', 'Fallback'));
    }

    #[Test]
    public function immutability(): void
    {
        $parsed = new ParsedMarkdown(
            html: '<p>Test</p>',
            frontMatter: ['title' => 'Immutable'],
            toc: [],
        );

        $reflection = new \ReflectionClass($parsed);
        $this->assertTrue($reflection->isReadOnly());
    }
}
