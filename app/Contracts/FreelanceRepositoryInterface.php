<?php

namespace App\Contracts;

interface FreelanceRepositoryInterface extends RepositoryInterface
{
    public function getFullStructure(): array;
    public function getCompanyByShareToken(string $token): ?array;
    public function getProjectByShareToken(string $token): ?array;
    public function saveCompany(array $data): int;
    public function deleteCompany(int $id): bool;
    public function saveProject(array $data): int;
    public function deleteProject(int $id): bool;
    public function saveTask(array $data): int;
    public function deleteTask(int $id): bool;
    public function importCompanyData(array $data): bool;
    public function importProjectData(int $companyId, array $data): int;
}
