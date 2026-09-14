<?php

namespace App\Services;

use App\Contracts\QuestionRepositoryInterface;
use App\Core\JsonSanitizer;
use App\Core\MarkdownParser;
use App\Models\Question;
use App\Repositories\QuestionRepository;
use Exception;

class QuestionService
{
    private QuestionRepositoryInterface $repository;

    public function __construct(?QuestionRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new QuestionRepository();
    }

    public function getRepository(): QuestionRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @return Question[]
     */
    public function getQuestions(?string $category = null): array
    {
        if ($category && strcasecmp($category, 'all') !== 0) {
            return $this->repository->getByCategory($category);
        }
        return $this->repository->getAll();
    }

    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    public function getQuestionById(int $id): ?Question
    {
        return $this->repository->getById($id);
    }

    public function addQuestion(string $question, string $answer, ?string $category): int
    {
        return $this->repository->addQuestion($question, $answer, $category);
    }

    public function updateQuestion(int $id, string $question, string $answer, ?string $category): bool
    {
        return $this->repository->updateQuestion($id, $question, $answer, $category);
    }

    public function deleteQuestion(int $id): bool
    {
        return $this->repository->deleteQuestion($id);
    }

    public function addCategory(string $name, ?string $description, ?string $image): void
    {
        $this->repository->addCategory($name, $description, $image);
    }

    public function updateCategory(string $oldName, string $newName, ?string $description, ?string $image): int
    {
        return $this->repository->updateCategory($oldName, $newName, $description, $image);
    }

    public function renameCategory(string $oldCategory, string $newCategory): int
    {
        return $this->repository->renameCategory($oldCategory, $newCategory);
    }

    public function deleteCategory(string $category): int
    {
        return $this->repository->deleteByCategory($category);
    }

    public function countAll(): int
    {
        return $this->repository->countAll();
    }

    public function clearAll(): void
    {
        $this->repository->clearAll();
    }

    public function renderMarkdown(string $text): string
    {
        return MarkdownParser::parse($text);
    }

    public function getExportJson(?string $category = null): string
    {
        $questions = $this->getQuestions($category);
        $exportData = array_map(fn(Question $q) => [
            'id' => (string)$q->id,
            'question' => $q->question,
            'answer' => $q->answer,
            'category' => $q->category ?? 'General',
        ], $questions);

        return json_encode(['data' => $exportData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function importJson(string $rawContent, bool $replaceExisting = false): array
    {
        $sanitizedJson = JsonSanitizer::sanitize($rawContent);
        $decoded = json_decode($sanitizedJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON file: ' . json_last_error_msg(),
                'count' => 0
            ];
        }

        $questions = [];
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $questions = $decoded['data'];
            } else {
                $questions = $decoded;
            }
        } else {
            return [
                'success' => false,
                'message' => 'JSON must be an array of questions or an object with a "data" array.',
                'count' => 0
            ];
        }

        if (empty($questions)) {
            return [
                'success' => false,
                'message' => 'No valid question entries found in the file.',
                'count' => 0
            ];
        }

        try {
            if ($replaceExisting) {
                $this->repository->clearAll();
            }
            $inserted = $this->repository->importQuestions($questions);
            return [
                'success' => true,
                'message' => "Successfully imported $inserted questions into the database.",
                'count' => $inserted
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }
}
