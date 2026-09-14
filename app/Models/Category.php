<?php

namespace App\Models;

class Category
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $image = null,
        public ?int $id = null
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
        ];
    }
}
