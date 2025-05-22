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
	#[TestWith(['', '/', 200])]
	#[TestWith(['', '/login', 200])]
	#[TestWith(['', '/register', 200])]
	#[TestWith(['', '/songs/browse', 200])]
	#[TestWith(['', '/video/browse', 200])]
	public function testRoute(string $username, string $url, string|int|null $expected): void
	{
		parent::testWithLogin($username, $url, (int)$expected);
	}
}
