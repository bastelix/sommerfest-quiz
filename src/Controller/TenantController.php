<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TenantService;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for managing tenants.
 */
class TenantController
{
    private TenantService $service;
    private bool $displayErrors;

    public function __construct(TenantService $service, bool $displayErrors = false)
    {
        $this->service = $service;
        $this->displayErrors = $displayErrors;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['uid'], $data['schema'])) {
            return $response->withStatus(400);
        }
        try {
            $this->service->createTenant((string) $data['uid'], (string) $data['schema']);
        } catch (PDOException $e) {
            $msg = 'Database error: ' . $e->getMessage();
            error_log($msg);
            if ($this->displayErrors) {
                $msg .= "\n" . $e->getTraceAsString();
            }
            $response->getBody()->write($msg);
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $status = $msg === 'tenant-exists' ? 409 : 500;
            if ($status !== 409) {
                error_log($msg);
                if ($this->displayErrors) {
                    $msg .= "\n" . $e->getTraceAsString();
                }
                $response->getBody()->write($msg);
            }
            return $response->withStatus($status)->withHeader('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            $msg = 'Error creating tenant: ' . $e->getMessage();
            error_log($msg);
            if ($this->displayErrors) {
                $msg .= "\n" . $e->getTraceAsString();
            }
            $response->getBody()->write($msg);
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }
        return $response->withStatus(201);
    }

    public function delete(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['uid'])) {
            return $response->withStatus(400);
        }
        $this->service->deleteTenant((string) $data['uid']);
        return $response->withStatus(204);
    }

    /**
     * Check if a tenant with the given subdomain already exists.
     */
    public function exists(Request $request, Response $response, array $args): Response
    {
        $sub = (string) ($args['subdomain'] ?? '');
        return $this->service->exists($sub)
            ? $response->withStatus(200)
            : $response->withStatus(404);
    }

    /**
     * List all tenants as JSON.
     */
    public function list(Request $request, Response $response): Response
    {
        $list = $this->service->getAll();
        $response->getBody()->write(json_encode($list));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
