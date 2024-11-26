<?php

namespace App\Tests;

use App\Services\AppService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UtilTest extends KernelTestCase
{
    public function testSomething(): void
    {
        $kernel = self::bootKernel();

        $this->assertSame('test', $kernel->getEnvironment());
        /** @var AppService $appService */
        $appService = static::getContainer()->get(AppService::class);
        $this->assertSame(AppService::class, $appService::class);

        // $myCustomService = static::getContainer()->get(CustomService::class);
    }
}
