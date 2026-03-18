<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\OAuthAccessTokenRepository;
use App\Repository\OAuthAuthorizationCodeRepository;
use App\Repository\OAuthClientRepository;
use App\Support\CsrfTokenHelper;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class OAuthController
{
    private const ALLOWED_SCOPES = [
        'cms:read', 'cms:write', 'seo:write',
        'menu:read', 'menu:write',
        'news:read', 'news:write',
    ];

    /**
     * GET /.well-known/oauth-authorization-server
     */
    public function metadata(Request $request, Response $response): Response
    {
        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort();
        $base = $scheme . '://' . $host;
        if ($port !== null && $port !== 80 && $port !== 443) {
            $base .= ':' . $port;
        }

        $basePath = getenv('BASE_PATH') ?: '';
        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') {
            $basePath = '';
        }
        $base .= $basePath;

        $meta = [
            'issuer' => $base,
            'authorization_endpoint' => $base . '/oauth/authorize',
            'token_endpoint' => $base . '/oauth/token',
            'registration_endpoint' => $base . '/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'scopes_supported' => self::ALLOWED_SCOPES,
        ];

        return $this->json($response, $meta);
    }

    /**
     * POST /oauth/register — Dynamic Client Registration (RFC 7591)
     */
    public function register(Request $request, Response $response): Response
    {
        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_request'], 400);
        }

        $name = isset($payload['client_name']) && is_string($payload['client_name'])
            ? trim($payload['client_name']) : '';

        $redirectUris = [];
        if (isset($payload['redirect_uris']) && is_array($payload['redirect_uris'])) {
            foreach ($payload['redirect_uris'] as $uri) {
                if (is_string($uri) && trim($uri) !== '') {
                    $redirectUris[] = trim($uri);
                }
            }
        }
        if ($redirectUris === []) {
            return $this->json($response, ['error' => 'invalid_redirect_uri'], 400);
        }

        $scope = isset($payload['scope']) && is_string($payload['scope'])
            ? trim($payload['scope']) : implode(' ', self::ALLOWED_SCOPES);

        $requestedScopes = array_filter(explode(' ', $scope), static fn(string $s) => $s !== '');
        foreach ($requestedScopes as $s) {
            if (!in_array($s, self::ALLOWED_SCOPES, true)) {
                return $this->json($response, ['error' => 'invalid_scope', 'scope' => $s], 400);
            }
        }

        $namespace = isset($payload['namespace']) && is_string($payload['namespace'])
            ? trim($payload['namespace']) : 'default';

        $pdo = RequestDatabase::resolve($request);
        $repo = new OAuthClientRepository($pdo);
        $result = $repo->create($name, $namespace, $redirectUris, implode(' ', $requestedScopes));

        return $this->json($response, [
            'client_id' => $result['clientId'],
            'client_secret' => $result['clientSecret'],
            'client_name' => $name,
            'redirect_uris' => $redirectUris,
            'scope' => implode(' ', $requestedScopes),
        ], 201);
    }

    /**
     * GET /oauth/authorize — Show consent page
     */
    public function authorize(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $clientId = isset($params['client_id']) && is_string($params['client_id']) ? trim($params['client_id']) : '';
        $redirectUri = isset($params['redirect_uri']) && is_string($params['redirect_uri']) ? trim($params['redirect_uri']) : '';
        $responseType = isset($params['response_type']) && is_string($params['response_type']) ? trim($params['response_type']) : '';
        $scope = isset($params['scope']) && is_string($params['scope']) ? trim($params['scope']) : '';
        $state = isset($params['state']) && is_string($params['state']) ? $params['state'] : '';
        $codeChallenge = isset($params['code_challenge']) && is_string($params['code_challenge']) ? trim($params['code_challenge']) : '';
        $codeChallengeMethod = isset($params['code_challenge_method']) && is_string($params['code_challenge_method']) ? trim($params['code_challenge_method']) : '';

        if ($responseType !== 'code') {
            return $this->json($response, ['error' => 'unsupported_response_type'], 400);
        }

        if ($codeChallenge !== '' && $codeChallengeMethod !== 'S256') {
            return $this->json($response, ['error' => 'invalid_request', 'error_description' => 'Only S256 code_challenge_method is supported'], 400);
        }

        $pdo = RequestDatabase::resolve($request);
        $clientRepo = new OAuthClientRepository($pdo);
        $client = $clientRepo->findById($clientId);
        if ($client === null) {
            return $this->json($response, ['error' => 'invalid_client'], 400);
        }

        if ($redirectUri === '') {
            $redirectUri = $client['redirect_uris'][0] ?? '';
        }
        if (!in_array($redirectUri, $client['redirect_uris'], true)) {
            return $this->json($response, ['error' => 'invalid_redirect_uri'], 400);
        }

        $requestedScopes = $scope !== '' ? array_filter(explode(' ', $scope), static fn(string $s) => $s !== '') : explode(' ', $client['scope']);
        foreach ($requestedScopes as $s) {
            if (!in_array($s, self::ALLOWED_SCOPES, true)) {
                return $this->redirectWithError($response, $redirectUri, 'invalid_scope', $state);
            }
        }

        // Check if user is logged in
        if (!isset($_SESSION['user']['id'])) {
            // Store OAuth params in session and redirect to login
            $_SESSION['oauth_authorize'] = $params;
            $basePath = getenv('BASE_PATH') ?: '';
            $basePath = '/' . trim($basePath, '/');
            if ($basePath === '/') {
                $basePath = '';
            }
            return $response
                ->withHeader('Location', $basePath . '/login?redirect=' . urlencode($basePath . '/oauth/authorize?' . http_build_query($params)))
                ->withStatus(302);
        }

        $csrf = CsrfTokenHelper::ensure();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'oauth/authorize.twig', [
            'client' => $client,
            'scopes' => $requestedScopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'csrf_token' => $csrf,
        ]);
    }

    /**
     * POST /oauth/authorize — Handle consent submission
     */
    public function authorizeSubmit(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($_SESSION['user']['id'])) {
            return $this->json($response, ['error' => 'unauthorized'], 401);
        }

        $clientId = isset($data['client_id']) && is_string($data['client_id']) ? trim($data['client_id']) : '';
        $redirectUri = isset($data['redirect_uri']) && is_string($data['redirect_uri']) ? trim($data['redirect_uri']) : '';
        $scope = isset($data['scope']) && is_string($data['scope']) ? trim($data['scope']) : '';
        $state = isset($data['state']) && is_string($data['state']) ? $data['state'] : '';
        $codeChallenge = isset($data['code_challenge']) && is_string($data['code_challenge']) ? trim($data['code_challenge']) : '';
        $approved = isset($data['approve']);

        if (!$approved) {
            return $this->redirectWithError($response, $redirectUri, 'access_denied', $state);
        }

        $pdo = RequestDatabase::resolve($request);
        $clientRepo = new OAuthClientRepository($pdo);
        $client = $clientRepo->findById($clientId);
        if ($client === null) {
            return $this->json($response, ['error' => 'invalid_client'], 400);
        }

        if (!in_array($redirectUri, $client['redirect_uris'], true)) {
            return $this->json($response, ['error' => 'invalid_redirect_uri'], 400);
        }

        $scopes = $scope !== '' ? array_filter(explode(' ', $scope), static fn(string $s) => $s !== '') : [];

        $code = OAuthAuthorizationCodeRepository::generateCode();
        $codeRepo = new OAuthAuthorizationCodeRepository($pdo);
        $codeRepo->create($code, $clientId, $client['namespace'], $scopes, $redirectUri, $codeChallenge !== '' ? $codeChallenge : null);

        $query = ['code' => $code];
        if ($state !== '') {
            $query['state'] = $state;
        }

        $sep = str_contains($redirectUri, '?') ? '&' : '?';
        $location = $redirectUri . $sep . http_build_query($query);

        return $response->withHeader('Location', $location)->withStatus(302);
    }

    /**
     * POST /oauth/token — Exchange authorization code for access token
     */
    public function token(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = json_decode((string) $request->getBody(), true);
            if (!is_array($data)) {
                return $this->json($response, ['error' => 'invalid_request'], 400);
            }
        }

        $grantType = isset($data['grant_type']) && is_string($data['grant_type']) ? trim($data['grant_type']) : '';
        if ($grantType !== 'authorization_code') {
            return $this->json($response, ['error' => 'unsupported_grant_type'], 400);
        }

        $code = isset($data['code']) && is_string($data['code']) ? trim($data['code']) : '';
        $clientId = isset($data['client_id']) && is_string($data['client_id']) ? trim($data['client_id']) : '';
        $clientSecret = isset($data['client_secret']) && is_string($data['client_secret']) ? trim($data['client_secret']) : '';
        $codeVerifier = isset($data['code_verifier']) && is_string($data['code_verifier']) ? trim($data['code_verifier']) : '';

        if ($code === '' || $clientId === '') {
            return $this->json($response, ['error' => 'invalid_request'], 400);
        }

        $pdo = RequestDatabase::resolve($request);

        // Verify client
        $clientRepo = new OAuthClientRepository($pdo);
        if ($clientSecret !== '') {
            if (!$clientRepo->verifySecret($clientId, $clientSecret)) {
                return $this->json($response, ['error' => 'invalid_client'], 401);
            }
        }

        // Consume authorization code
        $codeRepo = new OAuthAuthorizationCodeRepository($pdo);
        $authCode = $codeRepo->consume($code);
        if ($authCode === null) {
            return $this->json($response, ['error' => 'invalid_grant'], 400);
        }

        if ($authCode['client_id'] !== $clientId) {
            return $this->json($response, ['error' => 'invalid_grant'], 400);
        }

        // Verify PKCE
        if ($authCode['code_challenge'] !== null) {
            if ($codeVerifier === '') {
                return $this->json($response, ['error' => 'invalid_grant', 'error_description' => 'code_verifier required'], 400);
            }
            $expected = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            if (!hash_equals($authCode['code_challenge'], $expected)) {
                return $this->json($response, ['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed'], 400);
            }
        }

        // Issue access token
        $tokenRepo = new OAuthAccessTokenRepository($pdo);
        $tokenResult = $tokenRepo->create($clientId, $authCode['namespace'], $authCode['scopes'], 3600);

        return $this->json($response, [
            'access_token' => $tokenResult['token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokenResult['expiresIn'],
            'scope' => implode(' ', $authCode['scopes']),
        ]);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function redirectWithError(Response $response, string $redirectUri, string $error, string $state): Response
    {
        if ($redirectUri === '') {
            $res = new \Slim\Psr7\Response(400);
            $res->getBody()->write((string) json_encode(['error' => $error]));
            return $res->withHeader('Content-Type', 'application/json');
        }

        $query = ['error' => $error];
        if ($state !== '') {
            $query['state'] = $state;
        }
        $sep = str_contains($redirectUri, '?') ? '&' : '?';
        return $response->withHeader('Location', $redirectUri . $sep . http_build_query($query))->withStatus(302);
    }
}
