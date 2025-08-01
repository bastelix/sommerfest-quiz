<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * CRUD operations for user accounts.
 */
class UserController
{
    private UserService $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all users as JSON.
     */
    public function get(Request $request, Response $response): Response
    {
        $list = $this->service->getAll();
        $response->getBody()->write(json_encode($list));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Replace the user list with the provided data.
     */
    public function post(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data);
        return $response->withStatus(204);
    }
}
