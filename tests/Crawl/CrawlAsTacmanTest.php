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
	#[TestWith(['tacman@gmail.com', '/api/docs', 200])]
	#[TestWith(['tacman@gmail.com', '/api', 200])]
	#[TestWith(['tacman@gmail.com', '/api/api/meili/Song', 200])]
	#[TestWith(['tacman@gmail.com', '/api/songs', 200])]
	#[TestWith(['tacman@gmail.com', '/api/videos', 200])]
	#[TestWith(['tacman@gmail.com', '/api/meili-videos', 200])]
	#[TestWith(['tacman@gmail.com', '/js/routing', 200])]
	#[TestWith(['tacman@gmail.com', '/auth/profile', 200])]
	#[TestWith(['tacman@gmail.com', '/auth/providers', 200])]
	#[TestWith(['tacman@gmail.com', '/admin/commands/', 200])]
	#[TestWith(['tacman@gmail.com', '/meiliAdmin/meiliAdmin/meili/admin', 200])]
	#[TestWith(['tacman@gmail.com', '/meiliAdmin/meiliAdmin/riccox', 200])]
	#[TestWith(['tacman@gmail.com', '/workflow/', 200])]
	#[TestWith(['tacman@gmail.com', '/', 200])]
	#[TestWith(['tacman@gmail.com', '/song_credits', 200])]
	#[TestWith(['tacman@gmail.com', '/publish', 200])]
	#[TestWith(['tacman@gmail.com', '/load-kpa-channel', 200])]
	#[TestWith(['tacman@gmail.com', '/load-kpa-songs', 200])]
	#[TestWith(['tacman@gmail.com', '/load-lyrics-from-files', 200])]
	#[TestWith(['tacman@gmail.com', '/load-best-friends', 200])]
	#[TestWith(['tacman@gmail.com', '/liform', 200])]
	#[TestWith(['tacman@gmail.com', '/register', 200])]
	#[TestWith(['tacman@gmail.com', '/verify/email', 200])]
	#[TestWith(['tacman@gmail.com', '/login', 200])]
	#[TestWith(['tacman@gmail.com', '/songs/browse', 200])]
	#[TestWith(['tacman@gmail.com', '/video/index', 200])]
	#[TestWith(['tacman@gmail.com', '/video/browse', 200])]
	public function testRoute(string $username, string $url, string|int|null $expected): void
	{
		parent::loginAsUserAndVisit($username, $url, (int)$expected);
	}
}
