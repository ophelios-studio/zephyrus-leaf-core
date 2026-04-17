<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Config;

use Leaf\Config\LeafConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeafConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = LeafConfig::fromArray([]);

        $this->assertSame('My Project', $config->name);
        $this->assertSame('0.1.0', $config->version);
        $this->assertSame('', $config->description);
        $this->assertSame('', $config->githubUrl);
        $this->assertSame('content', $config->contentPath);
        $this->assertSame('dist', $config->outputPath);
        $this->assertSame('', $config->baseUrl);
        $this->assertSame('', $config->author);
        $this->assertSame('', $config->authorUrl);
        $this->assertSame('MIT', $config->license);
        $this->assertSame([], $config->sections);
    }

    #[Test]
    public function customValues(): void
    {
        $config = LeafConfig::fromArray([
            'name' => 'Zephyrus Docs',
            'version' => '2.0.0',
            'description' => 'Framework documentation.',
            'github_url' => 'https://github.com/dadajuice/zephyrus2',
            'content_path' => 'docs',
            'output_path' => 'build',
            'base_url' => '/docs',
            'author' => 'David Tucker',
            'author_url' => 'https://example.com',
            'license' => 'Apache-2.0',
            'sections' => [
                'getting-started' => 'Getting Started',
                'guides' => 'Guides',
            ],
        ]);

        $this->assertSame('Zephyrus Docs', $config->name);
        $this->assertSame('2.0.0', $config->version);
        $this->assertSame('Framework documentation.', $config->description);
        $this->assertSame('https://github.com/dadajuice/zephyrus2', $config->githubUrl);
        $this->assertSame('docs', $config->contentPath);
        $this->assertSame('build', $config->outputPath);
        $this->assertSame('/docs', $config->baseUrl);
        $this->assertSame('David Tucker', $config->author);
        $this->assertSame('https://example.com', $config->authorUrl);
        $this->assertSame('Apache-2.0', $config->license);
    }

    #[Test]
    public function sectionsPreserveInsertionOrder(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => [
                'getting-started' => 'Getting Started',
                'core-concepts' => 'Core Concepts',
                'advanced' => 'Advanced',
            ],
        ]);

        $keys = array_keys($config->sections);
        $this->assertSame(['getting-started', 'core-concepts', 'advanced'], $keys);
        $this->assertSame('Getting Started', $config->sections['getting-started']);
        $this->assertSame('Core Concepts', $config->sections['core-concepts']);
        $this->assertSame('Advanced', $config->sections['advanced']);
    }

    #[Test]
    public function sectionsHandlesNonArrayGracefully(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => 'invalid',
        ]);

        $this->assertSame([], $config->sections);
    }

    #[Test]
    public function sectionsHandlesEmptyArray(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => [],
        ]);

        $this->assertSame([], $config->sections);
    }
}
