<?php

declare(strict_types=1);

namespace App\Controller\Traits;

use App\Controller\Marketing\PageController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function array_key_exists;
use function is_array;
use function json_decode;
use function str_starts_with;
use function trim;

trait CmsBlockDetectionTrait
{
    private function isCmsBlockContent(string $content): bool
    {
        $trimmed = trim($content);
        if ($trimmed === '' || !str_starts_with($trimmed, '{')) {
            return false;
        }
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) && array_key_exists('blocks', $decoded);
    }

    private function renderCmsPage(Request $request, Response $response, string $namespace, string $slug): Response
    {
        $request = $request->withAttribute('namespace', $namespace);
        $controller = new PageController($slug);

        return $controller($request, $response);
    }
}
