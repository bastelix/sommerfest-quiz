<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Domain\Roles;
use App\Service\CustomerProfileService;
use App\Service\UserService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CustomerController
{
    public const SCOPE_CUSTOMER_READ = 'customer:read';
    public const SCOPE_CUSTOMER_WRITE = 'customer:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?UserService $users = null,
        private readonly ?CustomerProfileService $profiles = null,
    ) {
    }

    /**
     * POST /api/v1/register
     *
     * Public endpoint — no API token required.
     * Creates a new customer user (active=false, requires admin approval).
     */
    public function register(Request $request, Response $response): Response
    {
        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $username = isset($payload['username']) && is_string($payload['username']) ? trim($payload['username']) : '';
        $email = isset($payload['email']) && is_string($payload['email']) ? trim($payload['email']) : '';
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';

        if ($username === '' || $email === '' || $password === '') {
            return $this->json($response, ['error' => 'missing_required_fields'], 422);
        }

        if (strlen($password) < 8) {
            return $this->json($response, ['error' => 'password_too_short'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => 'invalid_email'], 422);
        }

        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        $userService = $this->users ?? new UserService($pdo);

        // Check for existing user
        $existingByUsername = $userService->getByUsername($username);
        if ($existingByUsername !== null) {
            return $this->json($response, ['error' => 'username_taken'], 409);
        }
        $existingByEmail = $userService->getByEmail($email);
        if ($existingByEmail !== null) {
            return $this->json($response, ['error' => 'email_taken'], 409);
        }

        try {
            $userService->create($username, $password, $email, Roles::CUSTOMER, false);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'registration_failed', 'message' => $e->getMessage()], 422);
        }

        // Create empty customer profile
        $user = $userService->getByUsername($username);
        if ($user !== null) {
            $profileService = $this->profiles ?? new CustomerProfileService($pdo);
            $profileService->upsert($user['id'], null, null, null, null);
        }

        return $this->json($response, [
            'status' => 'registered',
            'message' => 'awaiting_approval',
        ], 201);
    }

    /**
     * GET /api/v1/namespaces/{ns}/customer/profile
     */
    public function getProfile(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $profileService = $this->profiles ?? new CustomerProfileService($pdo);

        $tokenId = $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_ID);
        if ($tokenId === null) {
            return $this->json($response, ['error' => 'no_token'], 401);
        }

        // For API token-based access, use the token ID as a user reference lookup.
        // In practice, the profile is looked up via a userId provided as query param.
        $userId = isset($args['userId']) ? (int) $args['userId'] : null;
        $queryParams = $request->getQueryParams();
        if ($userId === null && isset($queryParams['userId']) && is_numeric($queryParams['userId'])) {
            $userId = (int) $queryParams['userId'];
        }

        if ($userId === null || $userId <= 0) {
            return $this->json($response, ['error' => 'missing_user_id'], 422);
        }

        $profile = $profileService->getByUserId($userId);
        if ($profile === null) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        return $this->json($response, ['namespace' => $ns, 'profile' => $profile->jsonSerialize()]);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/customer/profile
     */
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $userId = isset($payload['userId']) && is_numeric($payload['userId']) ? (int) $payload['userId'] : 0;
        if ($userId <= 0) {
            return $this->json($response, ['error' => 'missing_user_id'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $profileService = $this->profiles ?? new CustomerProfileService($pdo);

        $existing = $profileService->getByUserId($userId);
        $displayName = array_key_exists('displayName', $payload) && is_string($payload['displayName'])
            ? $payload['displayName']
            : ($existing?->getDisplayName());
        $company = array_key_exists('company', $payload) && is_string($payload['company'])
            ? $payload['company']
            : ($existing?->getCompany());
        $phone = array_key_exists('phone', $payload) && is_string($payload['phone'])
            ? $payload['phone']
            : ($existing?->getPhone());
        $avatarUrl = array_key_exists('avatarUrl', $payload) && is_string($payload['avatarUrl'])
            ? $payload['avatarUrl']
            : ($existing?->getAvatarUrl());

        $profile = $profileService->upsert($userId, $displayName, $company, $phone, $avatarUrl);

        return $this->json($response, [
            'status' => 'updated',
            'profile' => $profile->jsonSerialize(),
        ]);
    }

    private function resolvePdo(Request $request): PDO
    {
        $pdo = $this->pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        return RequestDatabase::resolve($request);
    }

    private function requireNamespaceMatch(Request $request, array $args): ?string
    {
        $ns = isset($args['ns']) ? (string) $args['ns'] : '';
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($ns === '' || $tokenNs === '' || $ns !== $tokenNs) {
            return null;
        }
        return $ns;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
