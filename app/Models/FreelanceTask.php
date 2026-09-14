<?php

namespace App\Models;

class FreelanceTask
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $title,
        public string $taskDate,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public string $startTime = '09:00',
        public string $endTime = '17:00',
        public float $pricePerHour = 500000.00,
        public float $durationHours = 0.00,
        public float $totalPrice = 0.00,
        public ?string $description = null,
        public string $status = 'completed',
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'title' => $this->title,
            'task_date' => $this->taskDate,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'price_per_hour' => $this->pricePerHour,
            'duration_hours' => $this->durationHours,
            'total_price' => $this->totalPrice,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
