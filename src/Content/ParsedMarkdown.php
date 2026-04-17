<?php

declare(strict_types=1);

namespace Leaf\Content;

/**
 * Value object representing a parsed markdown document.
 */
final readonly class ParsedMarkdown
{
    /**
     * @param array<string, mixed> $frontMatter
     * @param list<array{id: string, text: string, level: int}> $toc
     */
    public function __construct(
        public string $html,
        public array $frontMatter,
        public array $toc,
    ) {
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->frontMatter[$key] ?? $default;
    }
}
