<?php

declare(strict_types=1);

namespace App\Service;

class PasswordPolicy
{
    /**
     * Validate the given password against security requirements.
     */
    public function validate(string $pass): bool {
        if (strlen($pass) < 8) {
            return false;
        }
        if (!preg_match('/[a-z]/', $pass)) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $pass)) {
            return false;
        }
        if (!preg_match('/\d/', $pass)) {
            return false;
        }
        return true;
    }
}
