<?php

namespace App\Tests\Crawl;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Survos\CrawlerBundle\Tests\BaseVisitLinksTest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CrawlAsTtTest extends BaseVisitLinksTest
{
	#[TestDox('/$method $url ($route)')]
	#[TestWith(['tt@survos.com', '/api/docs', 200])]
	#[TestWith(['tt@survos.com', '/api', 200])]
	#[TestWith(['tt@survos.com', '/api/api/meili/Song', 200])]
	#[TestWith(['tt@survos.com', '/api/songs', 200])]
	#[TestWith(['tt@survos.com', '/api/videos', 200])]
	#[TestWith(['tt@survos.com', '/api/meili-videos', 200])]
	#[TestWith(['tt@survos.com', '/js/routing', 200])]
	#[TestWith(['tt@survos.com', '/auth/profile', 200])]
	#[TestWith(['tt@survos.com', '/auth/providers', 200])]
	#[TestWith(['tt@survos.com', '/admin/commands/admin/commands/', 200])]
	#[TestWith(['tt@survos.com', '/meiliAdmin/meiliAdmin/meili/admin', 200])]
	#[TestWith(['tt@survos.com', '/meiliAdmin/meiliAdmin/riccox', 200])]
	#[TestWith(['tt@survos.com', '/workflow/', 200])]
	#[TestWith(['tt@survos.com', '/', 200])]
	#[TestWith(['tt@survos.com', '/song_credits', 200])]
	#[TestWith(['tt@survos.com', '/publish', 200])]
	#[TestWith(['tt@survos.com', '/load-kpa-channel', 200])]
	#[TestWith(['tt@survos.com', '/load-kpa-songs', 200])]
	#[TestWith(['tt@survos.com', '/load-lyrics-from-files', 200])]
	#[TestWith(['tt@survos.com', '/load-best-friends', 200])]
	#[TestWith(['tt@survos.com', '/liform', 200])]
	#[TestWith(['tt@survos.com', '/register', 200])]
	#[TestWith(['tt@survos.com', '/verify/email', 200])]
	#[TestWith(['tt@survos.com', '/login', 200])]
	#[TestWith(['tt@survos.com', '/songs/browse', 200])]
	#[TestWith(['tt@survos.com', '/video/index', 200])]
	#[TestWith(['tt@survos.com', '/video/browse', 200])]
	public function testRoute(string $username, string $url, string|int|null $expected): void
	{
		parent::loginAsUserAndVisit($username, $url, (int)$expected);
	}
}
