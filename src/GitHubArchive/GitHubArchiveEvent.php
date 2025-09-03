<?php

namespace App\GitHubArchive;

final readonly class GitHubArchiveEvent
{
    /**
     * @param array{id: int|string, login: string, url: string, avatar_url: string} $actor
     * @param array{id: int|string, name: string, url: string}                      $repo
     * @param array<string, mixed>                                                  $payload
     */
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
