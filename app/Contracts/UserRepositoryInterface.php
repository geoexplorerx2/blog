<?php

namespace App\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByUsername(string $username): ?User;
    public function createUser(string $username, string $passwordHash): bool;
}
