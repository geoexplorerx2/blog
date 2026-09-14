<?php

namespace App\Services;

use App\Contracts\ProjectRepositoryInterface;
use App\Models\Project;
use App\Repositories\ProjectRepository;

class ProjectService
{
    private ProjectRepositoryInterface $repository;

    public function __construct(?ProjectRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new ProjectRepository();
    }

    public function getRepository(): ProjectRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @return Project[]
     */
    public function getAllProjects(): array
    {
        return $this->repository->getAll();
    }

    public function getAllProjectsArray(): array
    {
        return array_map(fn(Project $p) => $p->toArray(), $this->getAllProjects());
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->repository->getById($id);
    }

    public function saveProject(array $inputData): int
    {
        $technologies = [];
        if (isset($inputData['technologies'])) {
            if (is_array($inputData['technologies'])) {
                $technologies = $inputData['technologies'];
            } elseif (is_string($inputData['technologies'])) {
                $rawTech = trim($inputData['technologies']);
                if (str_starts_with($rawTech, '[')) {
                    $technologies = json_decode($rawTech, true) ?: [];
                } else {
                    $technologies = array_values(array_filter(array_map('trim', explode(',', $rawTech))));
                }
            }
        }

        $highlights = [];
        if (isset($inputData['highlights'])) {
            if (is_array($inputData['highlights'])) {
                $highlights = $inputData['highlights'];
            } elseif (is_string($inputData['highlights'])) {
                $lines = explode("\n", str_replace("\r", "", $inputData['highlights']));
                foreach ($lines as $line) {
                    $clean = trim(ltrim(trim($line), "-*•"));
                    if (!empty($clean)) $highlights[] = $clean;
                }
            }
        }

        $data = [
            'id' => !empty($inputData['id']) ? (int)$inputData['id'] : null,
            'title' => trim($inputData['title'] ?? ''),
            'description' => trim($inputData['description'] ?? ''),
            'project_url' => trim($inputData['project_url'] ?? ''),
            'github_url' => trim($inputData['github_url'] ?? ''),
            'company' => trim($inputData['company'] ?? ''),
            'duration' => trim($inputData['duration'] ?? ''),
            'role' => trim($inputData['role'] ?? ''),
            'technologies' => $technologies,
            'highlights' => $highlights,
            'featured' => !empty($inputData['featured']) ? 1 : 0,
        ];

        return $this->repository->save($data);
    }

    public function deleteProject(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function toggleFeatured(int $id): bool
    {
        return $this->repository->toggleFeatured($id);
    }
}
