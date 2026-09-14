<?php

namespace App\Models;

class Project
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public ?string $projectUrl = null,
        public ?string $githubUrl = null,
        public ?string $company = null,
        public ?string $duration = null,
        public ?string $role = null,
        public array $technologies = [],
        public array $highlights = [],
        public bool $featured = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'project_url' => $this->projectUrl,
            'github_url' => $this->githubUrl,
            'company' => $this->company,
            'duration' => $this->duration,
            'role' => $this->role,
            'technologies' => $this->technologies,
            'highlights' => $this->highlights,
            'featured' => $this->featured ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
