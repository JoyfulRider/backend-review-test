<?php

namespace App\Tests\Unit\GitHubArchive;

use App\GitHubArchive\EventsDownloader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class EventsDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->mkdir(__DIR__.'/tmp');
    }

    public function testDownload(): void
    {
        $callback = function ($method, $url): MockResponse {
            self::assertSame('GET', $method);
            self::assertMatchesRegularExpression('#^https://example.com/2025-08-01-\d+\.json\.gz$#', $url);

            return new MockResponse('foo');
        };

        $client = new MockHttpClient($callback);
        $eventsDownloader = new EventsDownloader($client, new NullLogger(), __DIR__.'/tmp');

        $paths = $eventsDownloader->download(new \DateTimeImmutable('2025-08-01T13:37:00Z'));

        self::assertNotEmpty($paths);
        self::assertCount(24, $paths);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(__DIR__.'/tmp');
    }
}
