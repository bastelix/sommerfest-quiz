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
            $plan = isset($data['plan']) ? (string) $data['plan'] : null;
            $billing = isset($data['billing']) ? (string) $data['billing'] : null;
            $email = isset($data['email']) ? (string) $data['email'] : null;
            $name = isset($data['imprint_name']) ? (string) $data['imprint_name'] : null;
            $street = isset($data['imprint_street']) ? (string) $data['imprint_street'] : null;
            $zip = isset($data['imprint_zip']) ? (string) $data['imprint_zip'] : null;
            $city = isset($data['imprint_city']) ? (string) $data['imprint_city'] : null;
            $this->service->createTenant(
                (string) $data['uid'],
                (string) $data['schema'],
                $plan,
                $billing,
                $email,
                $name,
                $street,
                $zip,
                $city
            );
        } catch (PDOException $e) {
            $code = (string) $e->getCode();
            if (in_array($code, ['23505', '23000'], true)) {
                $response->getBody()->write(json_encode(['error' => 'tenant-exists']));
                return $response
                    ->withStatus(409)
                    ->withHeader('Content-Type', 'application/json');
            }
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
     * Import missing tenants by scanning available schemas.
     */
    public function sync(Request $request, Response $response): Response
    {
        $count = $this->service->importMissing();
        $response->getBody()->write(json_encode(['imported' => $count]));
        return $response->withHeader('Content-Type', 'application/json');
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
