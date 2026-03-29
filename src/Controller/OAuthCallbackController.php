<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\AccountService;
use App\Service\OAuthIdentityService;
use App\Service\OAuthProviderFactory;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class OAuthCallbackController
{
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $params = $request->getQueryParams();

        if (!OAuthProviderFactory::isEnabled($name)) {
            return $response->withStatus(404);
        }

        // Validate state
        $state = (string) ($params['state'] ?? '');
        $sessionState = (string) ($_SESSION['oauth_state'] ?? '');
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

        if ($state === '' || !hash_equals($sessionState, $state)) {
            return $this->redirectWithError($response, $basePath, 'state_mismatch');
        }

        // Check for error from provider
        if (isset($params['error'])) {
            return $this->redirectWithError($response, $basePath, (string) $params['error']);
        }

        $code = (string) ($params['code'] ?? '');
        if ($code === '') {
            return $this->redirectWithError($response, $basePath, 'missing_code');
        }

        try {
            $provider = OAuthProviderFactory::create($name);
            $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
            assert($token instanceof AccessToken);
            $resourceOwner = $provider->getResourceOwner($token);
            $ownerArray = $resourceOwner->toArray();
        } catch (\Throwable $e) {
            error_log('OAuth callback error (' . $name . '): ' . $e->getMessage());

            return $this->redirectWithError($response, $basePath, 'token_exchange_failed');
        }

        $providerUserId = (string) ($ownerArray['sub'] ?? $ownerArray['id'] ?? '');
        $email = (string) ($ownerArray['email'] ?? '');
        $displayName = (string) ($ownerArray['name'] ?? '');

        if ($providerUserId === '' || $email === '') {
            return $this->redirectWithError($response, $basePath, 'incomplete_profile');
        }

        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $oauthService = new OAuthIdentityService($pdo);

        // Look up existing OAuth identity
        $identity = $oauthService->findByProvider($name, $providerUserId);

        if ($identity !== null) {
            // Known identity — load account
            $account = $accountService->findById((int) $identity['account_id']);
            if ($account === null || $account['status'] !== 'active') {
                return $this->redirectWithError($response, $basePath, 'account_inactive');
            }
        } else {
            // New identity — find or create account by email
            $account = $accountService->findByEmail($email);

            if ($account !== null && $account['status'] !== 'active') {
                return $this->redirectWithError($response, $basePath, 'account_inactive');
            }

            if ($account === null) {
                $accountId = $accountService->create($email, $displayName !== '' ? $displayName : null);
                $account = $accountService->findById($accountId);
            }

            // Link OAuth identity to account
            $oauthService->create(
                (int) $account['id'],
                $name,
                $providerUserId,
                $email,
                $ownerArray
            );
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        $_SESSION['account_email'] = $account['email'];

        // If plan/app were stored during registration, proceed to Stripe checkout
        $regData = $_SESSION['auth_register'] ?? [];
        $plan = (string) ($regData['plan'] ?? '');
        $app = (string) ($regData['app'] ?? '');
        unset($_SESSION['auth_register']);

        if ($plan !== '' && $app !== '') {
            $target = $basePath . '/stripe/checkout?' . http_build_query(['plan' => $plan, 'app' => $app]);
        } else {
            $returnUrl = $_SESSION['auth_return_url'] ?? null;
            unset($_SESSION['auth_return_url']);
            $target = is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : $basePath . '/account/subscriptions';
        }

        return $response->withHeader('Location', $target)->withStatus(302);
    }

    private function redirectWithError(Response $response, string $basePath, string $error): Response
    {
        $url = $basePath . '/auth/register?error=' . urlencode($error);

        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
