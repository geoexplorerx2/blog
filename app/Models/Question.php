<?php

namespace App\Models;

class Question
{
    public function __construct(
        public int $id,
        public string $question,
        public string $answer,
        public ?string $category = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'category' => $this->category ?? 'General',
        ];
    }
}
