<?php

declare(strict_types=1);

namespace App\Support;

class CsrfTokenHelper
{
    /**
     * Ensure a CSRF token exists in the session and return it.
     */
    public static function ensure(): string
    {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['csrf_token'];
    }
}
