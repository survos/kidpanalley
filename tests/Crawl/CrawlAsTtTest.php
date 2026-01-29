<?php

namespace App\Tests\Crawl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Survos\CrawlerBundle\Tests\BaseVisitLinksTest;

class CrawlAsTtTest extends BaseVisitLinksTest
{
    public static function routesToVisit(): iterable
    {
        $hasAny = false;

        foreach (parent::linksToVisit() as $dataset) {
            $hasAny = true;
            yield $dataset;
        }

        if (!$hasAny) {
            yield ['', '__SKIP__', 200];
        }
    }

    #[DataProvider('routesToVisit')]
    #[TestDox('$username $url should return $expected')]
    public function testRoute(string $username, string $url, string|int|null $expected): void
    {
        if ('__SKIP__' === $url) {
            self::markTestSkipped('No crawl data sets configured.');
        }

        parent::loginAsUserAndVisit($username, $url, (int) $expected);
    }
}
