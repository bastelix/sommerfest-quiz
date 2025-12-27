<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ContainerMetricsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;
use RuntimeException;

class SystemMetricsController
{
    private ContainerMetricsService $metricsService;

    public function __construct(?ContainerMetricsService $metricsService = null)
    {
        $this->metricsService = $metricsService ?? new ContainerMetricsService();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            $data = $this->metricsService->collect();
        } catch (RuntimeException $exception) {
            $errorResponse = $response instanceof SlimResponse ? $response : new SlimResponse();
            $errorResponse->getBody()->write(json_encode([
                'error' => 'metrics_unavailable',
                'message' => $exception->getMessage(),
            ]));

            return $errorResponse
                ->withStatus(503)
                ->withHeader('Content-Type', 'application/json');
        }

        $payload = json_encode($data);
        if (!is_string($payload)) {
            $payload = json_encode(['error' => 'encoding_failed']);
        }
        $response->getBody()->write((string) $payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
