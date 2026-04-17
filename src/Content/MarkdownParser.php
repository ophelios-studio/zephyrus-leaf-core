<?php

declare(strict_types=1);

namespace Leaf\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Wraps league/commonmark with pre-configured extensions for documentation
 * rendering: GFM tables, front matter, heading permalinks, and code fencing.
 */
final class MarkdownParser
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'heading_permalink' => [
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'before',
                'min_heading_level' => 2,
                'max_heading_level' => 4,
                'symbol' => '#',
                'html_class' => 'heading-permalink',
                'apply_id_to_heading' => true,
                'aria_hidden' => true,
                'title' => 'Permalink',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function parse(string $markdown): ParsedMarkdown
    {
        $result = $this->converter->convert($markdown);

        $frontMatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontMatter = $result->getFrontMatter() ?? [];
            if (!is_array($frontMatter)) {
                $frontMatter = [];
            }
        }

        $html = $result->getContent();
        $toc = $this->extractToc($result->getDocument());

        return new ParsedMarkdown(
            html: $html,
            frontMatter: $frontMatter,
            toc: $toc,
        );
    }

    /**
     * Extract front matter only (without full rendering).
     *
     * @return array<string, mixed>
     */
    public function extractFrontMatter(string $markdown): array
    {
        $extension = new FrontMatterExtension();
        $parsed = $extension->getFrontMatterParser()->parse($markdown);
        $frontMatter = $parsed->getFrontMatter();
        return is_array($frontMatter) ? $frontMatter : [];
    }

    /**
     * @return list<array{id: string, text: string, level: int}>
     */
    private function extractToc(\League\CommonMark\Node\Node $document): array
    {
        $toc = [];

        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }

            $level = $node->getLevel();
            if ($level < 2 || $level > 4) {
                continue;
            }

            $text = StringContainerHelper::getChildText($node, [HeadingPermalink::class]);
            $slug = '';

            foreach ($node->children() as $child) {
                if ($child instanceof HeadingPermalink) {
                    $slug = $child->getSlug();
                    break;
                }
            }

            if ($slug === '') {
                continue;
            }

            $toc[] = [
                'id' => $slug,
                'text' => $text,
                'level' => $level,
            ];
        }

        return $toc;
    }
}
