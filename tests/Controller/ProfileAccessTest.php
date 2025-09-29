<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ProfileAccessTest extends TestCase
{
    private function setupDb(): string {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testNonAdminCanAccessProfileAndSubscription(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'team-manager'];

        $profile = $app->handle($this->createRequest('GET', '/admin/profile'));
        $this->assertEquals(200, $profile->getStatusCode());

        $subscription = $app->handle($this->createRequest('GET', '/admin/subscription'));
        $this->assertEquals(200, $subscription->getStatusCode());

        session_destroy();
        unlink($db);
    }
}
