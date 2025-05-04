<?php

namespace App\Tests\Crawl;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Survos\CrawlerBundle\Tests\BaseVisitLinksTest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CrawlAsTacmanTest extends BaseVisitLinksTest
{
	#[TestDox('/$method $url ($route)')]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/', 200])]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/auth/profile', 200])]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/songs/browse', 200])]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/video/browse', 200])]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/song/1209/', 200])]
	#[TestWith(['tacman@gmail.com', 'App\Entity\User', '/video/wQrG1R0xmTw', 200])]
	public function testRoute(string $username, string $userClassName, string $url, string|int|null $expected): void
	{
		parent::testWithLogin($username, $userClassName, $url, (int)$expected);
	}
}
