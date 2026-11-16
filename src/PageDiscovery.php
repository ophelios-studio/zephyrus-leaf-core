<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Scans a Views directory for standalone Latte pages that should each
 * produce a top-level URL. Convention:
 *
 *     app/Views/pages/about.latte    ->   /about
 *     app/Views/pages/contact.latte  ->   /contact
 *     app/Views/pages/pricing.latte  ->   /pricing
 *
 * Wire discovered paths into BuildCommand::addPaths() so the static
 * builder renders them. The matching controller action lives in the
 * project template (PagesController), which resolves the page name back
 * to the Latte file at render time.
 */
final class PageDiscovery
{
    /** Route constraint (unanchored) and file-name rule shared by discovery and the page route. */
    public const string NAME_PATTERN = '[a-z0-9][a-z0-9-]*';

    /**
     * Return the list of URL paths for every .latte file under
     * `{viewsDir}/pages/`. Result is sorted for build-reproducibility.
     * Missing directories yield an empty list (no error).
     *
     * @return list<string>
     * @throws \InvalidArgumentException When a file name does not match NAME_PATTERN.
     */
    public static function discover(string $viewsDir): array
    {
        $pagesDir = rtrim($viewsDir, '/\\') . DIRECTORY_SEPARATOR . 'pages';
        if (!is_dir($pagesDir)) {
            return [];
        }

        $paths = [];
        $entries = scandir($pagesDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!str_ends_with($entry, '.latte')) {
                continue;
            }
            $name = substr($entry, 0, -strlen('.latte'));
            // Ignore nested layout fragments and dotfiles.
            if ($name === '' || $name[0] === '.') {
                continue;
            }
            if (preg_match('/^' . self::NAME_PATTERN . '$/D', $name) !== 1) {
                throw new \InvalidArgumentException(self::refusal($entry, $name));
            }
            $paths[] = '/' . $name;
        }

        sort($paths);
        return $paths;
    }

    private static function refusal(string $file, string $name): string
    {
        $message = sprintf(
            'Page "%s" cannot be served: page file names may only contain lowercase letters, '
            . 'digits and hyphens, and must not start with a hyphen.',
            $file,
        );
        $suggestion = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');

        return $suggestion === '' ? $message : $message . sprintf(' Rename it to "%s.latte".', $suggestion);
    }
}
