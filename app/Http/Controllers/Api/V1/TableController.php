<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TableHasOpenOrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Table\StoreTableRequest;
use App\Http\Requests\Table\UpdateTableRequest;
use App\Http\Requests\Table\UpdateTableStatusRequest;
use App\Http\Resources\TableResource;
use App\Services\TableService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(private readonly TableService $service) {}

    public function index(Request $request): JsonResponse
    {
        $onlyActive = ! $request->has('is_active') || filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);

        $tables = $this->service->list($request->query('status'), $onlyActive);

        return $this->success(TableResource::collection($tables));
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        try {
            $table = $this->service->create($request->validated());

            return $this->created(new TableResource($table));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(UpdateTableRequest $request, int $id): JsonResponse
    {
        try {
            $table = $this->service->update($id, $request->validated());

            return $this->success(new TableResource($table));
        } catch (ModelNotFoundException $e) {
            return $this->error('Mesa não encontrada.', 404);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function updateStatus(UpdateTableStatusRequest $request, int $id): JsonResponse
    {
        try {
            $table = $this->service->updateStatus($id, $request->validated()['status']);

            return $this->success(new TableResource($table));
        } catch (ModelNotFoundException $e) {
            return $this->error('Mesa não encontrada.', 404);
        }
    }

    public function rotateQr(int $id): JsonResponse
    {
        try {
            $table = $this->service->rotateQrToken($id);

            return $this->success(new TableResource($table));
        } catch (ModelNotFoundException $e) {
            return $this->error('Mesa não encontrada.', 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deactivate($id);

            return $this->success(message: 'Mesa removida.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Mesa não encontrada.', 404);
        } catch (TableHasOpenOrderException $e) {
            return $this->error($e->getMessage(), 409);
        }
    }
}
