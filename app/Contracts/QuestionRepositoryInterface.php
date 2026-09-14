<?php

namespace App\Contracts;

use App\Models\Question;

interface QuestionRepositoryInterface extends RepositoryInterface
{
    public function ensureCategoriesTableExists(): void;
    /** @return Question[] */
    public function getAll(): array;
    /** @return Question[] */
    public function getByCategory(string $category): array;
    public function getCategories(): array;
    public function addCategory(string $name, ?string $description, ?string $image): void;
    public function updateCategory(string $oldName, string $newName, ?string $description, ?string $image): int;
    public function renameCategory(string $oldCategory, string $newCategory): int;
    public function deleteByCategory(string $category): int;
    public function getById(int $id): ?Question;
    public function addQuestion(string $question, string $answer, ?string $category): int;
    public function updateQuestion(int $id, string $question, string $answer, ?string $category): bool;
    public function deleteQuestion(int $id): bool;
    public function importQuestions(array $questions): int;
    public function countAll(): int;
    public function clearAll(): void;
}
