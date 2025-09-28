<?php

declare(strict_types=1);

namespace App\Application\Security\Captcha;

final class NullCaptchaVerifier implements CaptchaVerifierInterface
{
    public function verify(string $token, ?string $ipAddress = null): bool
    {
        return true;
    }
}
