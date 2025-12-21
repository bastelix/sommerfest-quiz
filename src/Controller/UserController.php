<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Roles;
use App\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDOException;

/**
 * CRUD operations for user accounts.
 */
class UserController
{
    private UserService $service;

    public function __construct(UserService $service) {
        $this->service = $service;
    }

    /**
     * Return all users as JSON.
     */
    public function get(Request $request, Response $response): Response {
        $list = $this->service->getAll();
        $response->getBody()->write(json_encode($list));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Replace the user list with the provided data.
     */
    public function post(Request $request, Response $response): Response {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== Roles::ADMIN) {
            foreach ($data as &$entry) {
                if (is_array($entry)) {
                    unset($entry['namespaces']);
                }
            }
            unset($entry);
        }
        try {
            $this->service->saveAll($data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                return $response->withStatus(409);
            }

            throw $e;
        }

        $list = $this->service->getAll();
        $response->getBody()->write(json_encode($list));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
