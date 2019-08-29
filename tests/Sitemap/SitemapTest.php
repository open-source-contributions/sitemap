<?php
declare(strict_types=1);

/**
 * GpsLab component.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011-2019, Peter Gribanov
 * @license   http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Sitemap\Tests\Sitemap;

use GpsLab\Component\Sitemap\Sitemap\Sitemap;
use PHPUnit\Framework\TestCase;

class SitemapTest extends TestCase
{
    /**
     * @return array
     */
    public function getSitemap(): array
    {
        return [
            ['', null],
            ['/', new \DateTime('-1 day')],
            ['/index.html', new \DateTimeImmutable('-1 day')],
            ['/about/index.html', null],
            ['?', null],
            ['?foo=bar', null],
            ['?foo=bar&baz=123', null],
            ['#', null],
            ['#about', null],
        ];
    }

    /**
     * @dataProvider getSitemap
     *
     * @param string                  $location
     * @param \DateTimeInterface|null $last_modify
     */
    public function testSitemap(string $location, ?\DateTimeInterface $last_modify = null): void
    {
        $sitemap = new Sitemap($location, $last_modify);

        $this->assertEquals($location, $sitemap->getLocation());
        $this->assertEquals($last_modify, $sitemap->getLastModify());
    }
}
