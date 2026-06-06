<?php
namespace App\Repositories\Eloquent;
use App\Models\{StoreSettings, StoreStatus};
use App\Repositories\Contracts\StoreRepositoryInterface;

class StoreRepository implements StoreRepositoryInterface
{
    private function tenantId(): ?string { return app('current_tenant_id') ?: null; }

    public function getSettings(): ?StoreSettings { return StoreSettings::where('store_id', $this->tenantId())->first(); }
    public function updateSettings(array $data): StoreSettings { return StoreSettings::updateOrCreate(['store_id' => $this->tenantId()], $data); }
    public function getStatus(): ?StoreStatus { return StoreStatus::where('store_id', $this->tenantId())->first(); }
    public function updateStatus(bool $isOpen, bool $isAuto): StoreStatus { return StoreStatus::updateOrCreate(['store_id' => $this->tenan