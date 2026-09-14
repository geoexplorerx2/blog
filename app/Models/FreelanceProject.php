<?php

namespace App\Models;

class FreelanceProject
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $title,
        public ?string $description = null,
        public float $hourlyRate = 500000.00,
        public string $currency = 'تومان',
        public string $status = 'in_progress',
        public ?string $shareToken = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public array $tasks = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'title' => $this->title,
            'description' => $this->description,
            'hourly_rate' => $this->hourlyRate,
            'currency' => $this->currency,
            'status' => $this->status,
            'share_token' => $this->shareToken,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'tasks' => array_map(fn($t) => $t instanceof FreelanceTask ? $t->toArray() : $t, $this->tasks),
        ];
    }
}
