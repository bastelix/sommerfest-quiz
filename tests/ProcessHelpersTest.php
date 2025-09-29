<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class ProcessHelpersTest extends TestCase
{
    public function testRunSyncProcessHandlesSpacesInScriptPath(): void {
        $dir = sys_get_temp_dir() . '/space dir ' . uniqid();
        mkdir($dir);
        $script = $dir . '/test script.sh';
        file_put_contents($script, "#!/bin/sh\nexit 0\n");
        chmod($script, 0755);

        $result = \App\runSyncProcess($script);
        $this->assertTrue($result['success']);

        unlink($script);
        rmdir($dir);
    }

    public function testRunSyncProcessReturnsErrorOutputOnFailure(): void {
        $dir = sys_get_temp_dir() . '/space dir ' . uniqid();
        mkdir($dir);
        $script = $dir . '/test script.sh';
        file_put_contents($script, "#!/bin/sh\necho 'boom' 1>&2\nexit 1\n");
        chmod($script, 0755);

        $result = \App\runSyncProcess($script);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('boom', $result['stderr']);

        unlink($script);
        rmdir($dir);
    }
}
