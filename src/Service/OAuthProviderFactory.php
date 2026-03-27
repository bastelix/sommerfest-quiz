<?php

declare(strict_types=1);

namespace App\Service;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;

class OAuthProviderFactory
{
    public static function create(string $name): AbstractProvider
    {
        return match ($name) {
            'google' => new Google([
                'clientId' => getenv('OAUTH_GOOGLE_CLIENT_ID') ?: '',
                'clientSecret' => getenv('OAUTH_GOOGLE_CLIENT_SECRET') ?: '',
                'redirectUri' => getenv('OAUTH_GOOGLE_REDIRECT_URI') ?: '',
            ]),
            default => throw new \InvalidArgumentException("Unknown OAuth provider: {$name}"),
        };
    }

    public static function isEnabled(string $name): bool
    {
        return match ($name) {
            'google' => filter_var(getenv('OAUTH_GOOGLE_ENABLED'), FILTER_VALIDATE_BOOLEAN),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        $providers = [];
        foreach (['google'] as $name) {
            if (self::isEnabled($name)) {
                $providers[] = $name;
            }
        }

        return $providers;
    }
}
