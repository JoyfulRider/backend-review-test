<?php

namespace App\Tests\Unit\GitHubArchive;

use App\GitHubArchive\EventsExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class EventsExtractorTest extends TestCase
{
    private const BASE_PATH = __DIR__.'/fixtures/extractor-test';

    protected function setUp(): void
    {
        (new Filesystem())->remove(self::BASE_PATH.'/processing');
    }

    public function testExtract(): void
    {
        $eventsExtractor = new EventsExtractor(new NullLogger());

        $extractedPath = $eventsExtractor->extract(self::BASE_PATH.'/archive.json.gz');

        self::assertSame(self::BASE_PATH.'/processing/archive.json', $extractedPath);
        self::assertFileExists($extractedPath);
        self::assertFileEquals(self::BASE_PATH.'/expected.archive.json', $extractedPath);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(self::BASE_PATH.'/processing');
    }
}
