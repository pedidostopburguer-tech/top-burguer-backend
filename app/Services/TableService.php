<?php

namespace App\Services;

use App\Exceptions\TableHasOpenOrderException;
use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TableService
{
    public function __construct(private readonly TableRepositoryInterface $tables) {}

    public function list(?string $status = null, bool $onlyActive = true): Collection
    {
        return $this->tables->all($status, $onlyActive);
    }

    /**
     * RN-01: qr_token gerado automaticamente pelo repository.
     * RN-02: number deve ser único entre mesas ativas da loja.
     * RN-03: status nasce 'livre' (definido pelo repository).
     */
    public function create(array $data): Table
    {
        $this->assertNumberAvailable($data['number']);

        return $this->tables->create($data);
    }

    /**
     * RN-02: ao trocar o number, valida unicidade contra as demais mesas ativas.
     */
    public function update(int $id, array $data): Table
    {
        if (isset($data['number'])) {
            $this->assertNumberAvailable($data['number'], $id);
        }

        return $this->tables->update($id, $data);
    }

    public function updateStatus(int $id, string $status): Table
    {
        return $this->tables->updateStatus($id, $status);
    }

    /**
     * RN-05: gera novo qr_token, invalidando o anterior.
     */
    public function rotateQrToken(int $id): Table
    {
        return $this->tables->rotateQrToken($id);
    }

    /**
     * RN-04: bloqueia (409) a desativação se houver pedido em aberto para a mesa.
     */
    public function deactivate(int $id): Table
    {
        $table = $this->tables->findById($id);

        if (! $table) {
            throw new ModelNotFoundException('Mesa não encontrada.');
        }

        if ($this->tables->hasOpenOrder($table->number)) {
            throw new TableHasOpenOrderException;
        }

        return $this->tables->deactivate($id);
    }

    private function assertNumberAvailable(string $number, ?int $excludeId = null): void
    {
        if ($this->tables->existsByNumber($number, $excludeId)) {
            throw new \InvalidArgumentException('Já existe uma mesa ativa com este número.');
        }
    }
}
