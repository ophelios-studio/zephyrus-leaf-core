<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Rendering;

use Leaf\StaticBuildResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StaticBuildResultTest extends TestCase
{
    #[Test]
    public function propertiesAreAccessible(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 10,
            totalPaths: 12,
            elapsedMs: 45.5,
            errors: ['page /broken returned HTTP 500'],
            outputDirectory: '/tmp/dist',
        );

        $this->assertSame(10, $result->pagesBuilt);
        $this->assertSame(12, $result->totalPaths);
        $this->assertSame(45.5, $result->elapsedMs);
        $this->assertCount(1, $result->errors);
        $this->assertSame('/tmp/dist', $result->outputDirectory);
    }

    #[Test]
    public function isSuccessfulReturnsTrueWhenNoErrors(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 5,
            totalPaths: 5,
            elapsedMs: 20.0,
            errors: [],
            outputDirectory: '/tmp/dist',
        );

        $this->assertTrue($result->isSuccessful());
    }

    #[Test]
    public function isSuccessfulReturnsFalseWhenErrors(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 4,
            totalPaths: 5,
            elapsedMs: 30.0,
            errors: ['one error'],
            outputDirectory: '/tmp/dist',
        );

        $this->assertFalse($result->isSuccessful());
    }

    #[Test]
    public function summaryContainsPageCount(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 8,
            totalPaths: 10,
            elapsedMs: 55.3,
            errors: [],
            outputDirectory: '/tmp/dist',
        );

        $summary = $result->summary();
        $this->assertStringContainsString('8/10', $summary);
        $this->assertStringContainsString('55.3ms', $summary);
        $this->assertStringContainsString('OK', $summary);
        $this->assertStringContainsString('/tmp/dist', $summary);
    }

    #[Test]
    public function summaryShowsErrorCountWhenFailed(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 3,
            totalPaths: 5,
            elapsedMs: 40.0,
            errors: ['error1', 'error2'],
            outputDirectory: '/tmp/dist',
        );

        $summary = $result->summary();
        $this->assertStringContainsString('2 error(s)', $summary);
    }

    #[Test]
    public function immutability(): void
    {
        $result = new StaticBuildResult(
            pagesBuilt: 1,
            totalPaths: 1,
            elapsedMs: 1.0,
            errors: [],
            outputDirectory: '/tmp/dist',
        );

        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }
}
