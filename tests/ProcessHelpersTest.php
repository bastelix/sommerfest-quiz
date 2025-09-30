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

    /**
     * @runInSeparateProcess
     */
    public function testRunSyncProcessFallsBackWhenProcessThrows(): void {
        require_once __DIR__ . '/Stubs/FailingProcess.php';

        $dir = sys_get_temp_dir() . '/proc_open dir ' . uniqid();
        mkdir($dir);
        $script = $dir . '/fallback.sh';
        file_put_contents($script, "#!/bin/sh\necho 'ok'\nexit 0\n");
        chmod($script, 0755);

        $result = \App\runSyncProcess($script);

        $this->assertTrue($result['success']);
        $this->assertSame("ok\n", $result['stdout']);
        $this->assertSame('', $result['stderr']);

        unlink($script);
        rmdir($dir);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRunSyncProcessFallbackThrowsOnErrorWhenConfigured(): void {
        require_once __DIR__ . '/Stubs/FailingProcess.php';

        $dir = sys_get_temp_dir() . '/proc_open dir ' . uniqid();
        mkdir($dir);
        $script = $dir . '/fallback_error.sh';
        file_put_contents($script, "#!/bin/sh\necho 'fail' 1>&2\nexit 1\n");
        chmod($script, 0755);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fail');

        try {
            \App\runSyncProcess($script, [], true);
        } finally {
            unlink($script);
            rmdir($dir);
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testRunSyncProcessFallbackReturnsOutputsOnError(): void {
        require_once __DIR__ . '/Stubs/FailingProcess.php';

        $dir = sys_get_temp_dir() . '/proc_open dir ' . uniqid();
        mkdir($dir);
        $script = $dir . '/fallback_error_outputs.sh';
        file_put_contents($script, "#!/bin/sh\necho 'fail out'\necho 'fail err' 1>&2\nexit 2\n");
        chmod($script, 0755);

        try {
            $result = \App\runSyncProcess($script);

            $this->assertFalse($result['success']);
            $this->assertSame("fail out\n", $result['stdout']);
            $this->assertSame("fail err\n", $result['stderr']);
        } finally {
            unlink($script);
            rmdir($dir);
        }
    }
}
