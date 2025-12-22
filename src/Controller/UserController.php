<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Roles;
use App\Service\UserService;
use App\Support\UsernameBlockedException;
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
        } catch (UsernameBlockedException $exception) {
            $response->getBody()->write(json_encode([
                'error' => $exception->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $message = 'Duplicate entry.';
                $details = $e->getMessage();
                if (str_contains($details, 'users_username_key')) {
                    $message = 'The username is already in use.';
                } elseif (str_contains($details, 'users_email_key')) {
                    $message = 'The email address is already in use.';
                }

                $response->getBody()->write(json_encode([
                    'error' => $message,
                ]));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(409);
            }

            throw $e;
        }

        $list = $this->service->getAll();
        $response->getBody()->write(json_encode($list));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
