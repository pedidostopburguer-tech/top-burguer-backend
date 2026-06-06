<?php
namespace App\Repositories\Contracts;
use App\Models\{StoreSettings, StoreStatus};

interface StoreRepositoryInterface {
    public function getSettings(): ?StoreSettings;
    public function updateSettings(array $data): StoreSettings;
    public function getStatus(): ?StoreStatus;
    public function updateStatus(bool $isOpen, bool $isAuto): StoreStatus;
}
