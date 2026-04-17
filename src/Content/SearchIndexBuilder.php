<?php

declare(strict_types=1);

namespace Leaf\Content;

/**
 * Builds a JSON search index from all documentation pages.
 */
final class SearchIndexBuilder
{
    public function __construct(
        private readonly string $contentDirectory,
        private readonly MarkdownParser $parser,
        private readonly string $baseUrl = '',
    ) {
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

        $sections = array_filter(
            scandir($this->contentDirectory) ?: [],
            fn ($f) => $f !== '.' && $f !== '..' && is_dir($this->contentDirectory . '/' . $f),
        );

        foreach ($sections as $section) {
            $files = glob($this->contentDirectory . '/' . $section . '/*.md');
            if (!$files) {
                continue;
            }

            foreach ($files as $file) {
                $slug = basename($file, '.md');
                $content = file_get_contents($file);
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
}
