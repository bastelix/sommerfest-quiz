<?php

declare(strict_types=1);

namespace App\Application\Security;

interface TurnstileVerifierInterface
{
    public function verify(string $token, ?string $ip = null): bool;
}
