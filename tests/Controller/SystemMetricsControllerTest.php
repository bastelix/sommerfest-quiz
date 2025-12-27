<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Admin\SystemMetricsController;
use App\Service\ContainerMetricsService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class SystemMetricsControllerTest extends TestCase
{
    private function resetCpuSample(): void
    {
        $ref = new \ReflectionClass(ContainerMetricsService::class);
        $prop = $ref->getProperty('lastCpuSample');
        $prop->setAccessible(true);
        $prop->setValue(null);
    }

    public function testReturnsCgroupMetrics(): void
    {
        $this->resetCpuSample();
        $root = sys_get_temp_dir() . '/metrics-' . uniqid();
        mkdir($root, 0777, true);
        file_put_contents($root . '/cgroup.controllers', 'cpu');
        file_put_contents($root . '/memory.current', '1048576');
        file_put_contents($root . '/memory.max', '2097152');
        file_put_contents($root . '/cpu.stat', "usage_usec 1000000\n");
        file_put_contents($root . '/memory.events', "oom 1\noom_kill 0\n");

        $clockValues = [0.0, 1.0];
        $clock = static function () use (&$clockValues): float {
            $value = array_shift($clockValues);
            return $value === null ? 0.0 : $value;
        };

        $service = new ContainerMetricsService($root, $clock);
        $controller = new SystemMetricsController($service);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/admin/system/metrics');
        $response = $controller($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame(2, $data['cgroupVersion']);
        $this->assertSame(1048576, $data['memory']['currentBytes']);
        $this->assertSame(2097152, $data['memory']['maxBytes']);
        $this->assertSame(1000000, $data['cpu']['usageMicros']);
        $this->assertSame(0.0, (float) $data['cpu']['percent']);
        $this->assertSame(1, $data['oom']['events']);
        $this->assertSame(0, $data['oom']['kills']);

        file_put_contents($root . '/cpu.stat', "usage_usec 3000000\n");
        $responseSecond = $controller($request, new Response());
        $dataSecond = json_decode((string) $responseSecond->getBody(), true);
        $this->assertSame(200, $responseSecond->getStatusCode());
        $this->assertSame(2, $dataSecond['cgroupVersion']);
        $this->assertSame(3000000, $dataSecond['cpu']['usageMicros']);
        $this->assertSame(1.0, (float) $dataSecond['cpu']['sampleWindowSeconds']);
        $this->assertSame(200.0, (float) $dataSecond['cpu']['percent']);
    }

    public function testReturnsCgroupV1Metrics(): void
    {
        $this->resetCpuSample();
        $root = sys_get_temp_dir() . '/metrics-' . uniqid();
        mkdir($root . '/memory', 0777, true);
        mkdir($root . '/cpu', 0777, true);
        file_put_contents($root . '/memory/memory.usage_in_bytes', '1048576');
        file_put_contents($root . '/memory/memory.limit_in_bytes', '0');
        file_put_contents($root . '/cpu/cpuacct.usage', '1000000000');
        file_put_contents($root . '/memory/memory.oom_control', "oom_kill 2\n");

        $clockValues = [0.0, 1.0];
        $clock = static function () use (&$clockValues): float {
            $value = array_shift($clockValues);
            return $value === null ? 0.0 : $value;
        };

        $service = new ContainerMetricsService($root, $clock);
        $controller = new SystemMetricsController($service);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/admin/system/metrics');
        $response = $controller($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame(1, $data['cgroupVersion']);
        $this->assertSame(1048576, $data['memory']['currentBytes']);
        $this->assertSame(0, $data['memory']['maxBytes']);
        $this->assertSame(1000000000, $data['cpu']['usageNanos']);
        $this->assertSame(0.0, (float) $data['cpu']['percent']);
        $this->assertSame(2, $data['oom']['kills']);

        file_put_contents($root . '/cpu/cpuacct.usage', '3000000000');
        $responseSecond = $controller($request, new Response());
        $dataSecond = json_decode((string) $responseSecond->getBody(), true);
        $this->assertSame(200, $responseSecond->getStatusCode());
        $this->assertSame(1.0, (float) $dataSecond['cpu']['sampleWindowSeconds']);
        $this->assertSame(200.0, (float) $dataSecond['cpu']['percent']);
    }

    public function testUnavailableMetricsReturnError(): void
    {
        $this->resetCpuSample();
        $service = new ContainerMetricsService('/not-existing');
        $controller = new SystemMetricsController($service);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/admin/system/metrics');
        $response = $controller($request, new Response());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('metrics_unavailable', $payload['error']);
    }
}
