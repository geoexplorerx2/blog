<?php

namespace App\Services;

use App\Contracts\ProfileRepositoryInterface;
use App\Models\Profile;
use App\Repositories\ProfileRepository;

class ProfileService
{
    private ProfileRepositoryInterface $repository;

    public function __construct(?ProfileRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new ProfileRepository();
    }

    public function getRepository(): ProfileRepositoryInterface
    {
        return $this->repository;
    }

    public function getProfile(): ?Profile
    {
        return $this->repository->getProfile();
    }

    public function getProfileArray(): array
    {
        $profile = $this->getProfile();
        return $profile ? $profile->toArray() : [];
    }

    public function saveProfile(array $data): bool
    {
        return $this->repository->saveProfile($data);
    }
}
