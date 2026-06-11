<?php

namespace App\Repositories\Contracts;

use App\Models\Table;
use Illuminate\Database\Eloquent\Collection;

interface TableRepositoryInterface
{
    /**
     * Lista as mesas da loja atual (escopo via BelongsToTenant).
     */
    public function all(?string $status = null, bool $onlyActive = true): Collection;

    public function findById(int $id): ?Table;

    /**
     * Cria uma mesa. `qr_token` é gerado automaticamente e `status` nasce como 'livre'.
     */
    public function create(array $data): Table;

    public function update(int $id, array $data): Table;

    public function updateStatus(int $id, string $status): Table;

    /**
     * Gera um novo `qr_token` único para a mesa, invalidando o anterior.
     */
    public function rotateQrToken(int $id): Table;

    /**
     * Soft delete: marca `is_active = false`.
     */
    public function deactivate(int $id): Table;

    /**
     * Verifica se já existe uma mesa ATIVA com este `number` na loja atual.
     */
    public function existsByNumber(string $number, ?int $excludeId = null): bool;

    /**
     * Verifica se há pedido em aberto (channel='mesa', table_number=$number,
     * status fora de Finalizado/Recusado) para a mesa atual.
     */
    public function hasOpenOrder(string $number): bool;
}
