<?php

namespace App\Contracts;

use App\Models\Project;

interface ProjectRepositoryInterface extends RepositoryInterface
{
    /** @return Project[] */
    public function getAll(): array;
    public function getById(int $id): ?Project;
    public function save(array $data): int;
    public function delete(int $id): bool;
    public function toggleFeatured(int $id): bool;
}
