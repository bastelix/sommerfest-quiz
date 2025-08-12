<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\TenantService;
use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProfileController
{
    public function update(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $fields = [
            'plan' => (string) ($data['plan'] ?? ''),
            'billing_info' => (string) ($data['billing_info'] ?? ''),
            'imprint_name' => (string) ($data['imprint_name'] ?? ''),
            'imprint_street' => (string) ($data['imprint_street'] ?? ''),
            'imprint_zip' => (string) ($data['imprint_zip'] ?? ''),
            'imprint_city' => (string) ($data['imprint_city'] ?? ''),
            'imprint_email' => (string) ($data['imprint_email'] ?? ''),
        ];

        $domainType = $request->getAttribute('domainType');
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $service = new TenantService($pdo);
        if ($domainType === 'main') {
            $service->getMainTenant();
            $service->updateProfile('main', $fields);
        } else {
            $host = $request->getUri()->getHost();
            $sub = explode('.', $host)[0];
            $service->updateProfile($sub, $fields);
        }

        return $response->withStatus(204);
    }
}
