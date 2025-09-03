<?php

namespace App\GitHubArchive;

final readonly class GitHubArchiveEvent
{
    public function __construct(
        public int|string $id,
        public string $type,
        public array $actor,
        public array $repo,
        public array $payload,
        public bool $public,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
