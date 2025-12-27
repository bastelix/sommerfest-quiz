<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Admin\ProjectPagesController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ProjectPagesStartpageSelectionTest extends TestCase
{
    public function testSelectsDomainWithExistingStartpage(): void
    {
        $controller = $this->createController();
        $domainOptions = [
            'options' => [
                ['value' => '', 'label' => 'Namespace-weit (Fallback)'],
                ['value' => 'example.org', 'label' => 'example.org'],
                ['value' => 'custom.example', 'label' => 'custom.example'],
            ],
            'selected' => 'example.org',
        ];
        $startpageMap = [
            '' => null,
            'example.org' => null,
            'custom.example' => 99,
        ];

        $result = $this->resolveSelectedDomain($controller, $domainOptions, $startpageMap);

        $this->assertSame('custom.example', $result);
    }

    public function testFallsBackToCurrentHostWhenNoStartpageAssigned(): void
    {
        $controller = $this->createController();
        $domainOptions = [
            'options' => [
                ['value' => '', 'label' => 'Namespace-weit (Fallback)'],
                ['value' => 'example.org', 'label' => 'example.org'],
            ],
            'selected' => 'example.org',
        ];
        $startpageMap = [
            '' => null,
            'example.org' => null,
        ];

        $result = $this->resolveSelectedDomain($controller, $domainOptions, $startpageMap);

        $this->assertSame('example.org', $result);
    }

    private function createController(): ProjectPagesController
    {
        $reflection = new \ReflectionClass(ProjectPagesController::class);

        /** @var ProjectPagesController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();

        return $controller;
    }

    /**
     * @param array{options: list<array<string, mixed>>, selected: string} $domainOptions
     * @param array<string, int|null> $startpageMap
     */
    private function resolveSelectedDomain(
        ProjectPagesController $controller,
        array $domainOptions,
        array $startpageMap
    ): string {
        $method = new ReflectionMethod(ProjectPagesController::class, 'resolveSelectedStartpageDomain');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $domainOptions, $startpageMap);
    }
}
