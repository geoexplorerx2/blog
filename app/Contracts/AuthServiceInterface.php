<?php

namespace App\Contracts;

interface AuthServiceInterface
{
    public function login(string $username, string $password): bool;
    public function logout(): void;
    public function isLoggedIn(): bool;
    public function getCurrentUser(): ?string;
}
