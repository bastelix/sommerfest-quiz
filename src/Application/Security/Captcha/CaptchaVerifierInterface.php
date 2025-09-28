<?php

declare(strict_types=1);

namespace App\Application\Security\Captcha;

interface CaptchaVerifierInterface
{
    public function verify(string $token, ?string $ipAddress = null): bool;
}
