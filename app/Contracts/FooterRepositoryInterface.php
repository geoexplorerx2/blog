<?php

namespace App\Contracts;

use App\Models\FooterSetting;

interface FooterRepositoryInterface extends RepositoryInterface
{
    public function getSettings(): FooterSetting;
    public function saveSettings(array $data): bool;
}
