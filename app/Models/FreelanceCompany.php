<?php

namespace App\Models;

class FreelanceCompany
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $clientName = null,
        public ?string $clientEmail = null,
        public string $color = '#12466f',
        public ?string $shareToken = null,
        public ?string $createdAt = null,
        public array $projects = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->clientName,
            'client_email' => $this->clientEmail,
            'color' => $this->color,
            'share_token' => $this->shareToken,
            'created_at' => $this->createdAt,
            'projects' => array_map(fn($p) => $p instanceof FreelanceProject ? $p->toArray() : $p, $this->projects),
        ];
    }
}
