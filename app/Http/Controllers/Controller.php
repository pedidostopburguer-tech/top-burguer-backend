<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }
    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
    protected function created(mixed $data = null, string $message = 'Criado com sucesso'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }
}
