<?php
declare(strict_types=1);

/**
 * GpsLab component.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011-2019, Peter Gribanov
 * @license   http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Sitemap\Tests\Unit\Stream;

use GpsLab\Component\Sitemap\Render\SitemapRender;
use GpsLab\Component\Sitemap\Stream\Exception\FileAccessException;
use GpsLab\Component\Sitemap\Stream\Exception\LinksOverflowException;
use GpsLab\Component\Sitemap\Stream\Exception\SizeOverflowException;
use GpsLab\Component\Sitemap\Stream\Exception\StreamStateException;
use GpsLab\Component\Sitemap\Stream\RenderFileStream;
use GpsLab\Component\Sitemap\Url\Url;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RenderFileStreamTest extends TestCase
{
    /**
     * @var MockObject|SitemapRender
     */
    private $render;

    /**
     * @var RenderFileStream
     */
    private $stream;

    /**
     * @var string
     */
    private $expected_content = '';

    /**
     * @var string
     */
    private $filename = '';

    /**
     * @var string
     */
    private $opened = 'Stream opened';

    /**
     * @var string
     */
    private $closed = 'Stream closed';

    protected function setUp(): void
    {
        if (!$this->filename) {
            $this->filename = tempnam(sys_get_temp_dir(), 'test');
        }
        file_put_contents($this->filename, '');

        $this->render = $this->createMock(SitemapRender::class);
        $this->stream = new RenderFileStream($this->render, $this->filename);
    }

    protected function tearDown(): void
    {
        try {
            $this->stream->close();
        } catch (StreamStateException $e) {
            // already closed exception is correct error
            // test correct saved content
            self::assertEquals($this->expected_content, file_get_contents($this->filename));
        }

        $this->stream = null;
        unlink($this->filename);
        $this->expected_content = '';
    }

    public function testGetFilename(): void
    {
        self::assertEquals($this->filename, $this->stream->getFilename());
    }

    public function testOpenClose(): void
    {
        $this->open();
        $this->close();
    }

    public function testAlreadyOpened(): void
    {
        $this->expectException(StreamStateException::class);
        $this->open();

        $this->stream->open();
    }

    public function testNotOpened(): void
    {
        $this->expectException(StreamStateException::class);
        $this->render
            ->expects(self::never())
            ->method('end')
        ;

        $this->stream->close();
    }

    public function testAlreadyClosed(): void
    {
        $this->expectException(StreamStateException::class);
        $this->open();
        $this->close();

        $this->stream->close();
    }

    public function testPushNotOpened(): void
    {
        $this->expectException(StreamStateException::class);
        $this->stream->push(new Url('/'));
    }

    public function testPushClosed(): void
    {
        $this->expectException(StreamStateException::class);
        $this->open();
        $this->close();

        $this->stream->push(new Url('/'));
    }

    public function testPush(): void
    {
        $this->open();

        $urls = [
            new Url('/foo'),
            new Url('/bar'),
            new Url('/baz'),
        ];

        foreach ($urls as $i => $url) {
            /* @var $url Url */
            $this->render
                ->expects(self::at($i))
                ->method('url')
                ->with($urls[$i])
                ->will(self::returnValue($url->getLoc()))
            ;
            $this->expected_content .= $url->getLoc();
        }

        foreach ($urls as $url) {
            $this->stream->push($url);
        }

        $this->close();
    }

    public function testOverflowLinks(): void
    {
        $this->expectException(LinksOverflowException::class);
        $loc = '/';
        $this->stream->open();
        $this->render
            ->expects(self::atLeastOnce())
            ->method('url')
            ->will(self::returnValue($loc))
        ;

        for ($i = 0; $i <= RenderFileStream::LINKS_LIMIT; ++$i) {
            $this->stream->push(new Url($loc));
        }
    }

    public function testOverflowSize(): void
    {
        $this->expectException(SizeOverflowException::class);
        $loops = 10000;
        $loop_size = (int) floor(RenderFileStream::BYTE_LIMIT / $loops);
        $prefix_size = RenderFileStream::BYTE_LIMIT - ($loops * $loop_size);
        ++$prefix_size; // overflow byte
        $loc = str_repeat('/', $loop_size);

        $this->render
            ->expects(self::at(0))
            ->method('start')
            ->will(self::returnValue(str_repeat('/', $prefix_size)))
        ;
        $this->render
            ->expects(self::atLeastOnce())
            ->method('url')
            ->will(self::returnValue($loc))
        ;

        $this->stream->open();

        for ($i = 0; $i < $loops; ++$i) {
            $this->stream->push(new Url($loc));
        }
    }

    public function testNotWritable(): void
    {
        $this->expectException(FileAccessException::class);
        $this->stream = new RenderFileStream($this->render, '');
        $this->stream->open();
        $this->stream->close();
    }

    private function open(): void
    {
        $this->render
            ->expects(self::at(0))
            ->method('start')
            ->will(self::returnValue($this->opened))
        ;
        $this->render
            ->expects(self::at(1))
            ->method('end')
            ->will(self::returnValue($this->closed))
        ;

        $this->stream->open();
        $this->expected_content .= $this->opened;
    }

    private function close(): void
    {
        $this->stream->close();
        $this->expected_content .= $this->closed;
    }
}
