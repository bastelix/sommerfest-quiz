<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Admin view: list central customer accounts with their app subscriptions.
 */
class AccountListController
{
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);

        return $view->render($response, 'admin/account_list.twig', []);
    }

    public function list(Request $request, Response $response): Response
    {
        $pdo = Database::connectFromEnv();

        $stmt = $pdo->query(
            'SELECT a.id, a.email, a.name, a.status, a.stripe_customer_id, a.created_at,
                    s.app, s.plan, s.status AS sub_status, s.valid_until, s.stripe_subscription_id, s.created_at AS sub_created_at
             FROM accounts a
             LEFT JOIN app_subscriptions s ON s.account_id = a.id
             ORDER BY a.created_at DESC, s.created_at DESC'
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group subscriptions by account
        $accounts = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($accounts[$id])) {
                $accounts[$id] = [
                    'id' => $id,
                    'email' => $row['email'],
                    'name' => $row['name'],
                    'status' => $row['status'],
                    'stripe_customer_id' => $row['stripe_customer_id'],
                    'created_at' => $row['created_at'],
                    'subscriptions' => [],
                ];
            }
            if ($row['app'] !== null) {
                $accounts[$id]['subscriptions'][] = [
                    'app' => $row['app'],
                    'plan' => $row['plan'],
                    'status' => $row['sub_status'],
                    'valid_until' => $row['valid_until'],
                    'created_at' => $row['sub_created_at'],
                ];
            }
        }

        $response->getBody()->write(json_encode(array_values($accounts), JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
