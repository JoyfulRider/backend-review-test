<?php

namespace App\Repository;

use App\Dto\SearchInput;

interface ReadEventRepository
{
    public function countAll(SearchInput $searchInput): int;

    /**
     * @return array<string, int>
     */
    public function countByType(SearchInput $searchInput): array;

    /**
     * @return array<int, array{commit: int, pullRequest: int, comment: int}>
     */
    public function statsByTypePerHour(SearchInput $searchInput): array;

    /**
     * @return array{type: string, repo: array<mixed>}[]
     */
    public function getLatest(SearchInput $searchInput): array;

    public function exist(int $id): bool;
}
