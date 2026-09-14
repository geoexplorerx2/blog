<?php

namespace App\Contracts;

use App\Models\Profile;

interface ProfileRepositoryInterface extends RepositoryInterface
{
    public function getProfile(): ?Profile;
    public function saveProfile(array $data): bool;
}
