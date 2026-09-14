<?php

namespace App\Services;

use App\Contracts\FooterRepositoryInterface;
use App\Models\FooterSetting;
use App\Repositories\FooterRepository;

class FooterService
{
    private FooterRepositoryInterface $repository;

    public function __construct(?FooterRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new FooterRepository();
    }

    public function getRepository(): FooterRepositoryInterface
    {
        return $this->repository;
    }

    public function getSettings(): FooterSetting
    {
        return $this->repository->getSettings();
    }

    public function getSettingsArray(): array
    {
        return $this->getSettings()->toArray();
    }

    public function saveSettings(array $data): bool
    {
        return $this->repository->saveSettings($data);
    }
}
