# Comando: /new-feature

Implementa uma feature a partir de uma spec aprovada em `docs/specs/`.

## Argumento
$ARGUMENTS — caminho da spec ou nome da feature (ex: "order-historico-cliente" ou "docs/specs/order-historico-cliente.md")

## Pré-requisito
A spec deve ter `Status: approved`. Se estiver em `draft`, peça ao usuário para revisar antes de implementar.

## Processo de implementação

Leia a spec integralmente primeiro. Depois implemente nesta ordem exata:

### 1. Migration
```bash
docker compose exec app php artisan make:migration {descricao}
```
- Campos com tipos corretos
- Foreign keys com `constrained()`
- Método `down()` reversível obrigatório

### 2. Model
```bash
docker compose exec app php artisan make:model {Nome}
```
- `use BelongsToTenant` se pertence a uma loja
- `$fillable` explícito
- Casts para decimais, datas e booleans
- Relacionamentos

### 3. Factory
```bash
docker compose exec app php artisan make:factory {Nome}Factory
```
- Cobrir todos os campos de `$fillable`
- Criar `state()` para casos especiais da spec

### 4. Repository Interface
Arquivo: `app/Repositories/Contracts/{Domain}RepositoryInterface.php`
- Métodos com tipos de retorno explícitos
- Nomes de intenção (não CRUD): `findByCode()`, não `getByCode()`

### 5. Eloquent Repository
Arquivo: `app/Repositories/Eloquent/{Domain}Repository.php`
- Implementa a interface
- Queries simples — sem lógica de negócio
- Registrar no `AppServiceProvider`

### 6. Service
Arquivo: `app/Services/{Domain}Service.php`
- `DB::transaction()` em operações multi-tabela
- Exceções tipadas — nunca retornar `false`
- Nunca acessar `request()` aqui

### 7. FormRequest
```bash
docker compose exec app php artisan make:request {Domain}/Store{Domain}Request
```
- `authorize()` retorna `true` (auth é no middleware)
- Mensagens em português

### 8. Controller
```bash
docker compose exec app php artisan make:controller Api/V1/{Domain}Controller
```
- Thin: recebe, delega ao Service, retorna Resource
- Usa `$this->success()`, `$this->created()`, `$this->error()`

### 9. API Resource
```bash
docker compose exec app php artisan make:resource {Domain}Resource
```
- Nunca expor `store_id`
- Datas em ISO 8601
- Decimais como string com 2 casas

### 10. Rota
Em `routes/api.php` — colocar no grupo correto (público ou `auth:sanctum`)

### 11. Testes
```bash
docker compose exec app php artisan make:test Feature/{Domain}/{Nome}Test
docker compose exec app php artisan make:test Unit/Services/{Domain}ServiceTest --unit
```
Cobrir todos os casos listados na spec (feliz + erros).

### 12. Verificação final
```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app php artisan route:list --path=api/v1
```
**Todos os testes devem passar antes de considerar a feature concluída.**

## Ao terminar
- Marcar spec como `Status: implemented`
- Listar os arquivos criados/modificados
- Mostrar o curl de exemplo para testar o endpoint
