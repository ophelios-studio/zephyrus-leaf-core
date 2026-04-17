<?php

declare(strict_types=1);

namespace Leaf\Config;

use Zephyrus\Core\Config\ConfigSection;

/**
 * Typed configuration for the "leaf" section in config.yml.
 *
 * leaf:
 *   name: "My Project"
 *   version: "1.0.0"
 *   description: "A short description."
 *   github_url: "https://github.com/user/repo"
 *   content_path: "content"
 *   sections:
 *     getting-started: "Getting Started"
 *     guides: "Guides"
 *   output_path: "dist"
 *   base_url: ""
 *   author: "Your Name"
 *   author_url: "https://yoursite.com"
 *   license: "MIT"
 */
final class LeafConfig extends ConfigSection
{
    public readonly string $name;
    public readonly string $version;
    public readonly string $description;
    public readonly string $githubUrl;
    public readonly string $contentPath;
    public readonly string $outputPath;
    public readonly string $baseUrl;
    public readonly string $author;
    public readonly string $authorUrl;
    public readonly string $license;
    public readonly string $productionUrl;

    /** @var array<string, string> slug => label, ordered */
    public readonly array $sections;

    public static function fromArray(array $values): static
    {
        $instance = new static($values);
        $instance->name = $instance->getString('name', 'My Project');
        $instance->version = $instance->getString('version', '0.1.0');
        $instance->description = $instance->getString('description', '');
        $instance->githubUrl = $instance->getString('githubUrl', '');
        $instance->contentPath = $instance->getString('contentPath', 'content');
        $instance->outputPath = $instance->getString('outputPath', 'dist');
        $instance->baseUrl = $instance->getString('baseUrl', '');
        $instance->author = $instance->getString('author', '');
        $instance->authorUrl = $instance->getString('authorUrl', '');
        $instance->license = $instance->getString('license', 'MIT');
        $instance->productionUrl = $instance->getString('productionUrl', '');
        $instance->sections = $instance->parseSections();
        return $instance;
    }

    /**
     * Parse the sections config, preserving YAML insertion order.
     *
     * @return array<string, string>
     */
    private function parseSections(): array
    {
        $raw = $this->get('sections');
        if (!is_array($raw)) {
            return [];
        }

        // The sections may come through as camelCase-normalized keys due to
        // ConfigSection::normalizeKeys. Since section slugs use hyphens
        // (e.g. "getting-started"), we need the raw values. Access the raw
        // config values directly.
        $rawValues = $this->values['sections'] ?? $raw;
        if (!is_array($rawValues)) {
            return [];
        }

        $sections = [];
        foreach ($rawValues as $slug => $label) {
            $sections[(string) $slug] = (string) $label;
        }

        return $sections;
    }
}
