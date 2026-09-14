<?php

namespace App\Contracts;

interface RepositoryInterface
{
    public function ensureTableExists(): void;
}
