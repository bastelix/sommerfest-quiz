<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TenantService;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * API endpoints for managing tenants.
 */
class TenantController
{
    private const FILTERABLE_STATUSES = [
        'active',
        'canceled',
        'simulated',
        TenantService::ONBOARDING_PENDING,
        TenantService::ONBOARDING_PROVISIONING,
        TenantService::ONBOARDING_PROVISIONED,
        TenantService::ONBOARDING_FAILED,
    ];

    private TenantService $service;
    private bool $displayErrors;

    public function __construct(TenantService $service, bool $displayErrors = false) {
        $this->service = $service;
        $this->displayErrors = $displayErrors;
    }

    public function create(Request $request, Response $response): Response {
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

    public function delete(Request $request, Response $response): Response {
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
    public function exists(Request $request, Response $response, array $args): Response {
        $sub = (string) ($args['subdomain'] ?? '');
        return $this->service->exists($sub)
            ? $response->withStatus(200)
            : $response->withStatus(404);
    }

    /**
     * Import missing tenants by scanning available schemas.
     */
    public function sync(Request $request, Response $response): Response {
        try {
            $result = $this->service->importMissing();
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $msg = 'Error importing tenants: ' . $e->getMessage();
            error_log($msg);
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Export tenant list as CSV file.
     */
    public function export(Request $request, Response $response): Response {
        $tenants = $this->service->getAll();
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['subdomain', 'plan', 'billing', 'email', 'created_at']);
        foreach ($tenants as $row) {
            fputcsv($handle, [
                $row['subdomain'],
                $row['plan'] ?? '',
                $row['billing_info'] ?? '',
                $row['imprint_email'] ?? '',
                $row['created_at'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);
        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="tenants.csv"');
    }

    /**
     * List all tenants as JSON.
     */
    public function list(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $query = isset($params['query']) ? (string) $params['query'] : '';
        $status = isset($params['status']) ? strtolower((string) $params['status']) : '';
        $list = $this->service->getAll($query);
        $list = $this->filterByStatus($list, $status);
        $response->getBody()->write(json_encode($list));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Render tenant list as HTML.
     */
    public function listHtml(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $status = isset($params['status']) ? strtolower((string) $params['status']) : '';
        $query = isset($params['query']) ? (string) $params['query'] : '';
        $list = $this->service->getAll($query);
        /** @var array<array<string, mixed>> $list */
        $list = $this->filterByStatus($list, $status);
        $view = Twig::fromRequest($request);
        $html = $view->fetch('admin/tenant_list.twig', [
            'tenants' => $list,
            'main_domain' => getenv('MAIN_DOMAIN') ?: '',
            'stripe_dashboard' => filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN)
                ? 'https://dashboard.stripe.com/test'
                : 'https://dashboard.stripe.com',
            'tenant_sync' => $this->service->getSyncState(),
        ]);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Provide a simple tenant report as CSV with plan counts.
     */
    public function report(Request $request, Response $response): Response {
        $tenants = $this->service->getAll();
        $stats = [];
        foreach ($tenants as $row) {
            $plan = (string) ($row['plan'] ?? '');
            $stats[$plan] = ($stats[$plan] ?? 0) + 1;
        }
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['plan', 'count']);
        foreach ($stats as $plan => $count) {
            fputcsv($handle, [$plan === '' ? 'none' : $plan, (string) $count]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);
        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="tenant-report.csv"');
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array<int, array<string, mixed>>
     */
    private function filterByStatus(array $list, string $status): array {
        if ($status === '' || !in_array($status, self::FILTERABLE_STATUSES, true)) {
            return $list;
        }

        return array_values(
            array_filter(
                $list,
                static function (array $t) use ($status): bool {
                    return isset($t['status']) && $t['status'] === $status;
                }
            )
        );
    }
}
