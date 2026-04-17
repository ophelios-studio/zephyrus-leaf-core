<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Immutable result of a static site build.
 */
final readonly class StaticBuildResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $builtPages Locale-agnostic paths that were successfully built
     */
    public function __construct(
        public int $pagesBuilt,
        public int $totalPaths,
        public float $elapsedMs,
        public array $errors,
        public string $outputDirectory,
        public array $builtPages = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }

    public function summary(): string
    {
        $status = $this->isSuccessful() ? 'OK' : sprintf('%d error(s)', count($this->errors));
        return sprintf(
            'Built %d/%d pages in %.1fms [%s] -> %s',
            $this->pagesBuilt,
            $this->totalPaths,
            $this->elapsedMs,
            $status,
            $this->outputDirectory,
        );
    }
}
