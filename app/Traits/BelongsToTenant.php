<?php
namespace App\Traits;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Aplica escopo automático de store_id em queries e injeção no CREATE.
 * Qualquer Model com este trait é automaticamente isolado por tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $storeId = app('current_tenant_id');
            if ($storeId) {
                $builder->where($builder->getModel()->getTable().'.store_id', $storeId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->store_id)) {
                $storeId = app('current_tenant_id');
                if (! $storeId) throw new RuntimeException('Nenhum tenant identificado no request.');
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
