<?php

namespace App\Tests\Crawl;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Survos\CrawlerBundle\Tests\BaseVisitLinksTest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CrawlAsVisitorTest extends BaseVisitLinksTest
{
	#[TestDox('/$method $url ($route)')]
	#[TestWith(['', 'App\Entity\User', '/', 200])]
	#[TestWith(['', 'App\Entity\User', '/login', 200])]
	#[TestWith(['', 'App\Entity\User', '/register', 200])]
	#[TestWith(['', 'App\Entity\User', '/songs/browse', 200])]
	#[TestWith(['', 'App\Entity\User', '/video/browse', 200])]
	#[TestWith(['', 'App\Entity\User', '/song/1209/', 200])]
	#[TestWith(['', 'App\Entity\User', '/video/wQrG1R0xmTw', 200])]
	public function testRoute(string $username, string $userClassName, string $url, string|int|null $expected): void
	{
		parent::testWithLogin($username, $userClassName, $url, (int)$expected);
	}
}
