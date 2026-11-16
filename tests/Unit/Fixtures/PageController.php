<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Fixtures;

use Zephyrus\Http\Response;

final class PageController
{
    public function home(): Response
    {
        return Response::html('<h1>home</h1>');
    }

    public function page(string $page): Response
    {
        return Response::html('<h1>' . $page . '</h1>');
    }
}
