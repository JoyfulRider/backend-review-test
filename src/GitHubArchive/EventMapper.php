<?php

namespace App\GitHubArchive;

use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Repo;
use Doctrine\ORM\EntityManagerInterface;

class EventMapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function map(GitHubArchiveEvent $gitHubArchiveEvent): ?Event
    {
        $managedType = match ($gitHubArchiveEvent->type) {
            'PushEvent' => EventType::COMMIT,
            'PullRequestEvent' => EventType::PULL_REQUEST,
            'CommitCommentEvent', 'IssueCommentEvent', 'PullRequestReviewCommentEvent' => EventType::COMMENT,
            default => null,
        };

        if (null === $managedType) {
            return null;
        }

        $event = $this->entityManager->find(Event::class, $gitHubArchiveEvent->id);

        if (null !== $event) {
            return $event;
        }

        $comment = null;

        if (EventType::COMMENT === $managedType) {
            $comment = $gitHubArchiveEvent->payload['comment']['body'] ?? null;
        }

        $actor = $this->entityManager->find(Actor::class, $gitHubArchiveEvent->actor['id']);

        if (null === $actor) {
            $actor = Actor::fromArray($gitHubArchiveEvent->actor);
        }

        $repo = $this->entityManager->find(Repo::class, $gitHubArchiveEvent->repo['id']);

        if (null === $repo) {
            $repo = Repo::fromArray($gitHubArchiveEvent->repo);
        }

        return new Event(
            $gitHubArchiveEvent->id,
            $managedType,
            $actor,
            $repo,
            $gitHubArchiveEvent->payload,
            $gitHubArchiveEvent->createdAt,
            $comment,
        );
    }
}
