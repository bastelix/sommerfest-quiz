<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\PageVariableService;

class PageVariableServiceTest extends TestCase
{
    public function testApplyReplacesPlaceholders(): void {
        $html = '<p>[NAME], [STREET], [ZIP] [CITY], [EMAIL]</p>';
        $result = PageVariableService::apply($html);
        $this->assertStringNotContainsString('[NAME]', $result);
        $this->assertStringNotContainsString('[STREET]', $result);
        $this->assertStringNotContainsString('[ZIP]', $result);
        $this->assertStringNotContainsString('[CITY]', $result);
        $this->assertStringNotContainsString('[EMAIL]', $result);
    }
}
