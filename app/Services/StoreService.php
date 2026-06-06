<?php
namespace App\Services;
use App\Models\{StoreSettings, StoreStatus};
use App\Repositories\Contracts\StoreRepositoryInterface;

class StoreService
{
    public function __construct(private readonly StoreRepositoryInterface $store) {}
    public function getPublicProfile(): array { return ['settings' => $this->store->getSettings(), 'status' => $this->store->getStatus()]; }
    public function updateSettings(array $data): StoreSettings { return $this->store->updateSettings($data); }
    public function updateStatus(bool $isOpen, bool $isAuto): StoreStatus { return $this->store->updateStatus($isOpen, $isAuto); }
}
