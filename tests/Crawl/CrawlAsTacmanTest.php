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
	#[TestWith(['tacman@gmail.com', '/', 200])]
	#[TestWith(['tacman@gmail.com', '/auth/profile', 200])]
	#[TestWith(['tacman@gmail.com', '/songs/browse', 200])]
	#[TestWith(['tacman@gmail.com', '/video/browse', 200])]
	public function testRoute(string $username, string $url, string|int|null $expected): void
	{
		parent::testWithLogin($username, $url, (int)$expected);
	}
}
