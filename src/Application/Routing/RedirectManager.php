<?php

declare(strict_types=1);

namespace App\Application\Routing;

/**
 * Stores and registers HTTP redirects.
 */
class RedirectManager
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? dirname(__DIR__, 3) . '/data/redirects.json';
    }

    /**
     * Register a redirect from one path or URL to another.
     */
    public function register(string $from, string $to, int $status = 301): void
    {
        $redirects = [];
        if (is_file($this->file)) {
            $data = json_decode((string) file_get_contents($this->file), true);
            if (is_array($data)) {
                $redirects = $data;
            }
        }
        $redirects[] = [
            'from' => $from,
            'to' => $to,
            'status' => $status,
        ];
        file_put_contents($this->file, json_encode($redirects, JSON_PRETTY_PRINT) . "\n");
    }
}
