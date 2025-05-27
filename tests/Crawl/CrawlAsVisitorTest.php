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
	#[TestWith(['', '/api/docs', 200])]
	#[TestWith(['', '/api', 200])]
	#[TestWith(['', '/api/api/meili/Song', 200])]
	#[TestWith(['', '/api/songs', 200])]
	#[TestWith(['', '/api/videos', 200])]
	#[TestWith(['', '/api/meili-videos', 200])]
	#[TestWith(['', '/js/routing', 200])]
	#[TestWith(['', '/auth/profile', 200])]
	#[TestWith(['', '/auth/providers', 200])]
	#[TestWith(['', '/admin/commands/admin/commands/', 200])]
	#[TestWith(['', '/meiliAdmin/meiliAdmin/meili/admin', 200])]
	#[TestWith(['', '/meiliAdmin/meiliAdmin/riccox', 200])]
	#[TestWith(['', '/workflow/', 200])]
	#[TestWith(['', '/', 200])]
	#[TestWith(['', '/song_credits', 200])]
	#[TestWith(['', '/publish', 200])]
	#[TestWith(['', '/load-kpa-channel', 200])]
	#[TestWith(['', '/load-kpa-songs', 200])]
	#[TestWith(['', '/load-lyrics-from-files', 200])]
	#[TestWith(['', '/load-best-friends', 200])]
	#[TestWith(['', '/liform', 200])]
	#[TestWith(['', '/register', 200])]
	#[TestWith(['', '/verify/email', 200])]
	#[TestWith(['', '/login', 200])]
	#[TestWith(['', '/songs/browse', 200])]
	#[TestWith(['', '/video/index', 200])]
	#[TestWith(['', '/video/browse', 200])]
	public function testRoute(string $username, string $url, string|int|null $expected): void
	{
		parent::loginAsUserAndVisit($username, $url, (int)$expected);
	}
}
