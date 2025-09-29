<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Application\Routing\RedirectManager;

class NullRedirectManager extends RedirectManager
{
    public function __construct() {
    }

    public function register(string $from, string $to, int $status = 301): void {
    }
}
