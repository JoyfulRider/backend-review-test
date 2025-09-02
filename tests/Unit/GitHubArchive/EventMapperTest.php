<?php

namespace App\Tests\Unit\GitHubArchive;

use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Repo;
use App\GitHubArchive\EventMapper;
use App\GitHubArchive\GitHubArchiveEvent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EventMapperTest extends TestCase
{
    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    /**
     * @dataProvider successfulProvider
     */
    public function testMap(
        GitHubArchiveEvent $gitHubArchiveEvent,
        Event $expected,
        ?Event $knownEvent,
        ?Actor $knownActor,
        ?Repo $knownRepo,
    ): void {
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('find')
            ->willReturnCallback(static fn (string $entityClass) => match ($entityClass) {
                Event::class => $knownEvent,
                Actor::class => $knownActor,
                Repo::class => $knownRepo,
                default => throw new \RuntimeException('unknown entity class'),
            });

        $mapper = new EventMapper($this->entityManager);
        $actual = $mapper->map($gitHubArchiveEvent);

        self::assertEquals($expected, $actual);
    }

    /**
     * @return \Generator<string, array{GitHubArchiveEvent, Event, Event|null, Actor|null, Repo|null}>
     */
    public function successfulProvider(): \Generator
    {
        $actor = [
            'id' => 2,
            'login' => 'foo',
            'url' => 'https://foo.bar',
            'avatar_url' => 'https://foo.bar/avatar',
        ];

        $repo = [
            'id' => 3,
            'name' => 'foo',
            'url' => 'https://foo.bar/repo',
        ];

        $gitHubArchiveEvent = self::createGitHubArchiveEvent('PushEvent', $actor, $repo);

        yield 'new event, new actor, new repo' => [
            $gitHubArchiveEvent,
            new Event(
                42,
                EventType::COMMIT,
                new Actor(
                    2,
                    'foo',
                    'https://foo.bar',
                    'https://foo.bar/avatar',
                ),
                new Repo(
                    3,
                    'foo',
                    'https://foo.bar/repo',
                ),
                [],
                new \DateTimeImmutable('2025-08-01T13:37:00Z'),
                null,
            ),
            null,
            null,
            null,
        ];

        $knownActor = new Actor(
            2,
            'foo',
            'https://foo.bar',
            'https://foo.bar/avatar',
        );

        yield 'new event, known actor, new repo' => [
            $gitHubArchiveEvent,
            new Event(
                42,
                EventType::COMMIT,
                $knownActor,
                new Repo(
                    3,
                    'foo',
                    'https://foo.bar/repo',
                ),
                [],
                new \DateTimeImmutable('2025-08-01T13:37:00Z'),
                null,
            ),
            null,
            $knownActor,
            null,
        ];

        $knownRepo = new Repo(
            3,
            'foo',
            'https://foo.bar/repo',
        );

        yield 'new event, known actor, known repo' => [
            $gitHubArchiveEvent,
            new Event(
                42,
                EventType::COMMIT,
                $knownActor,
                $knownRepo,
                [],
                new \DateTimeImmutable('2025-08-01T13:37:00Z'),
                null,
            ),
            null,
            $knownActor,
            $knownRepo,
        ];

        $knownEvent = new Event(
            42,
            EventType::COMMIT,
            $knownActor,
            $knownRepo,
            [],
            new \DateTimeImmutable('2025-08-01T13:37:00Z'),
            null,
        );

        yield 'known event, known actor, known repo' => [
            $gitHubArchiveEvent,
            $knownEvent,
            $knownEvent,
            $knownActor,
            $knownRepo,
        ];

        $pullRequestEvent = self::createGitHubArchiveEvent('PullRequestEvent', $actor, $repo);

        yield 'new event (PullRequestEvent)' => [
            $pullRequestEvent,
            new Event(
                42,
                EventType::PULL_REQUEST,
                $knownActor,
                $knownRepo,
                [],
                new \DateTimeImmutable('2025-08-01T13:37:00Z'),
                null,
            ),
            null,
            $knownActor,
            $knownRepo,
        ];

        $payload = ['comment' => ['body' => 'My comment']];
        $commentEvents = [
            self::createGitHubArchiveEvent('CommitCommentEvent', $actor, $repo, $payload),
            self::createGitHubArchiveEvent('IssueCommentEvent', $actor, $repo, $payload),
            self::createGitHubArchiveEvent('PullRequestReviewCommentEvent', $actor, $repo, $payload),
        ];

        foreach ($commentEvents as $commentEvent) {
            yield sprintf('new event (%s)', $commentEvent->type) => [
                $commentEvent,
                new Event(
                    42,
                    EventType::COMMENT,
                    $knownActor,
                    $knownRepo,
                    $payload,
                    new \DateTimeImmutable('2025-08-01T13:37:00Z'),
                    'My comment',
                ),
                null,
                $knownActor,
                $knownRepo,
            ];
        }
    }

    public function testMapWithUnmanagedType(): void
    {
        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $mapper = new EventMapper($this->entityManager);
        $actual = $mapper->map(self::createGitHubArchiveEvent('UnknownEvent'));

        self::assertNull($actual);
    }

    /**
     * @param array{id: int|string, login: string, url: string, avatar_url: string}|array{} $actor
     * @param array{id: int|string, name: string, url: string}|array{}                      $repo
     * @param array<string, mixed>                                                          $payload
     */
    private static function createGitHubArchiveEvent(
        string $type = 'PushEvent',
        array $actor = [],
        array $repo = [],
        array $payload = [],
    ): GitHubArchiveEvent {
        return new GitHubArchiveEvent(
            '42',
            $type,
            $actor,
            $repo,
            $payload,
            true,
            new \DateTimeImmutable('2025-08-01T13:37:00Z'),
        );
    }
}
