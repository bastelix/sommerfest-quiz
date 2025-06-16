<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ResultService;
use Tests\TestCase;

class ResultServiceTest extends TestCase
{
    public function testAddIncrementsAttemptForSameCatalog(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'results');
        $service = new ResultService($tmp);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $second = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(2, $second['attempt']);

        unlink($tmp);
    }

    public function testAddDoesNotIncrementAcrossCatalogs(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'results');
        $service = new ResultService($tmp);

        $first = $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $this->assertSame(1, $first['attempt']);

        $other = $service->add(['name' => 'TeamA', 'catalog' => 'cat2']);
        $this->assertSame(1, $other['attempt']);

        unlink($tmp);
    }

    public function testMarkPuzzleUpdatesEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'results');
        $service = new ResultService($tmp);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $ts = time();
        $service->markPuzzle('TeamA', 'cat1', $ts);
        $data = $service->getAll();

        $this->assertSame($ts, $data[0]['puzzleTime']);

        unlink($tmp);
    }

    public function testSetPhotoUpdatesEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'results');
        $service = new ResultService($tmp);

        $service->add(['name' => 'TeamA', 'catalog' => 'cat1']);
        $service->setPhoto('TeamA', 'cat1', '/photo/test.jpg');
        $data = $service->getAll();

        $this->assertSame('/photo/test.jpg', $data[0]['photo']);

        unlink($tmp);
    }
}
