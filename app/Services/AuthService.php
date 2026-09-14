<?php

namespace App\Services;

use App\Contracts\AuthServiceInterface;
use App\Contracts\UserRepositoryInterface;
use App\Core\Session;
use App\Repositories\UserRepository;

class AuthService implements AuthServiceInterface
{
    private UserRepositoryInterface $userRepository;

    public function __construct(?UserRepositoryInterface $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function ensureUserTableExists(): void
    {
        $this->userRepository->ensureTableExists();
    }

    public function login(string $username, string $password): bool
    {
        $this->ensureUserTableExists();
        $user = $this->userRepository->findByUsername($username);

        if ($user && password_verify($password, $user->password)) {
            Session::set('logged_in_user', $user->username);
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function isLoggedIn(): bool
    {
        $user = Session::get('logged_in_user');
        return !empty($user);
    }

    public function getCurrentUser(): ?string
    {
        return Session::get('logged_in_user');
    }
}
