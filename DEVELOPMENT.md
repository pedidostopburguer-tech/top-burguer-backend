# Top Burguer Backend — Guia de Desenvolvimento

Este documento é a **fonte de verdade** para qualquer pessoa (ou IA) que for escrever código neste projeto. Todo novo código deve seguir os padrões aqui descritos.

---

## 1. Filosofia do Projeto

### Pragmatismo acima de dogma
Seguimos os princípios de SOLID, DDD e boas práticas de testes — mas sempre de forma pragmática. Nenhum padrão é aplicado por obrigação; ele existe porque resolve um problema real neste projeto.

### As três perguntas antes de escrever código
1. **Esta classe tem uma responsabilidade só?** (SRP)
2. **Onde mora esta regra de negócio?** (Service ou Action, nunca Controller)
3. **Tem teste cobrindo o caminho feliz e o caminho de erro?**

---

## 2. Arquitetura de Camadas

```
Request
   │
   ▼
Middleware (IdentifyTenant → resolve store_id)
   │
   ▼
FormRequest (validação — nunca no Controller)
   │
   ▼
Controller (thin — recebe, delega, responde)
   │
   ▼
Service (orquestra regras de negócio)
   │         │
   ▼         ▼
Action    Repository Interface (contrato)
              │
              ▼
         Eloquent Repository (implementação)
              │
              ▼
           Model (BelongsToTenant — escopo automático)
              │
              ▼
         PostgreSQL
```

### Responsabilidade de cada camada

| Camada | Faz | Não faz |
|--------|-----|---------|
| **Controller** | Recebe request, chama Service, retorna Resource | Validação, regra de negócio, acesso a DB |
| **FormRequest** | Valida e autoriza o request | Transformação de dados, lógica |
| **API Resource** | Transforma Model em JSON de resposta | Lógica de negócio |
| **Service** | Orquestra regras de negócio, usa Repository | Acesso direto ao DB, HTTP |
| **Action** | Executa uma operação atômica e complexa | Múltiplas responsabilidades |
| **Repository** | Abstrai queries Eloquent | Regras de negócio |
| **Model** | Define estrutura, relacionamentos, scopes | Regra de negócio, HTTP |
| **Event/Listener** | Reage a eventos de domínio | Lógica de negócio primária |
| **Job** | Processa tarefas assíncronas (fila) | Lógica síncrona crítica |

---

## 3. Multi-Tenancy

Todo o isolamento de dados é feito por **Row-Level via `store_id`**.

### Como funciona
1. O `IdentifyTenant` middleware resolve o tenant pelo header `X-Store-Slug`
2. O `store_id` é disponibilizado via `app('current_tenant_id')`
3. Qualquer Model com `use BelongsToTenant` aplica automaticamente o `WHERE store_id = ?` em todo SELECT e injeta o `store_id` em todo INSERT

### Regra obrigatória
**Todo Model que pertence a uma loja DEVE usar o trait `BelongsToTenant`.**

```php
class Product extends Model
{
    use BelongsToTenant; // ← obrigatório para dados por loja
}
```

Models globais (Store, Profile) **não** usam o trait.

### Nunca fazer
```php
// ❌ ERRADO — nunca filtrar tenant manualmente
Product::where('store_id', $storeId)->get();

// ✅ CERTO — o trait já aplica o filtro automaticamente
Product::all();
```

---

## 4. Padrão de Controllers

Controllers são **thin** (finos). Toda lógica vai para o Service.

```php
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        // 1. FormRequest já validou
        // 2. Chama o service com dados validados
        // 3. Retorna Resource
        $order = $this->service->place($request->validated());

        return $this->created(new OrderResource($order), 'Pedido realizado!');
    }
}
```

### Métodos de resposta (herdados de Controller base)
```php
$this->success($data, 'mensagem', 200);  // { success: true, data: ... }
$this->created($data, 'mensagem');       // HTTP 201
$this->error('mensagem', 422, $errors);  // { success: false, ... }
```

---

## 5. Padrão de FormRequests

Toda validação mora em `app/Http/Requests/`.

```
app/Http/Requests/
├── Order/
│   ├── StoreOrderRequest.php
│   └── UpdateOrderStatusRequest.php
├── Product/
│   ├── StoreProductRequest.php
│   └── UpdateProductRequest.php
└── Coupon/
    ├── StoreCouponRequest.php
    └── ValidateCouponRequest.php
```

```php
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth é feita no middleware
    }

    public function rules(): array
    {
        return [
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'subtotal'         => 'required|numeric|min:0',
            'delivery_fee'     => 'required|numeric|min:0',
            'coupon_code'      => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'O carrinho não pode estar vazio.',
        ];
    }
}
```

---

## 6. Padrão de API Resources

Toda resposta de Model é transformada via `app/Http/Resources/`.

```
app/Http/Resources/
├── OrderResource.php
├── OrderCollection.php
├── ProductResource.php
└── StoreProfileResource.php
```

```php
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customer'       => [
                'name'  => $this->customer_name,
                'phone' => $this->customer_phone,
            ],
            'items'          => $this->items,
            'subtotal'       => number_format($this->subtotal, 2, '.', ''),
            'delivery_fee'   => number_format($this->delivery_fee, 2, '.', ''),
            'discount'       => number_format($this->discount_amount, 2, '.', ''),
            'total'          => number_format($this->total, 2, '.', ''),
            'status'         => $this->status,
            'created_at'     => $this->created_at->toIso8601String(),
            // ← store_id NUNCA exposto na API pública
        ];
    }
}
```

---

## 7. Padrão de Services

Services contêm as regras de negócio. Dependem de interfaces de Repository.

```php
class OrderService
{
    // Injeção via construtor com readonly (imutável)
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly CouponService              $couponService,
    ) {}

    // Métodos com nomes de intenção, não de CRUD
    public function place(array $data): Order { ... }      // não "create"
    public function updateStatus(...): Order { ... }
    public function cancel(int $id, string $reason): Order { ... }
}
```

### Regras para Services
- Sempre usar `DB::transaction()` em operações que tocam múltiplas tabelas
- Lançar exceptions tipadas (`InvalidArgumentException`, `RuntimeException`) — nunca retornar `false`
- Nunca acessar `request()` ou qualquer coisa HTTP dentro do service

---

## 8. Padrão de Actions

Use Actions quando uma operação é complexa demais para um Service mas pertence a um domínio único.

```
app/Actions/
├── Order/
│   ├── PlaceOrderAction.php
│   └── CancelOrderAction.php
└── Coupon/
    └── ApplyCouponAction.php
```

```php
class PlaceOrderAction
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly ApplyCouponAction          $applyCoupon,
    ) {}

    public function execute(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // lógica atômica e focada
        });
    }
}
```

---

## 9. Padrão de Testes

### Estrutura
```
tests/
├── Feature/
│   ├── Order/
│   │   ├── PlaceOrderTest.php
│   │   └── UpdateOrderStatusTest.php
│   ├── Product/
│   │   └── ProductCrudTest.php
│   └── Coupon/
│       └── CouponValidationTest.php
└── Unit/
    ├── Services/
    │   ├── OrderServiceTest.php
    │   └── CouponServiceTest.php
    └── Actions/
        └── PlaceOrderActionTest.php
```

### Feature Test (testa endpoint completo com DB)
```php
// tests/Feature/Order/PlaceOrderTest.php
it('cria um pedido com sucesso', function () {
    $store   = Store::factory()->create();
    $product = Product::factory()->for($store)->create(['price' => 25.00]);

    $response = $this->withHeaders(['X-Store-Slug' => $store->slug])
        ->postJson('/api/v1/orders', [
            'customer_name'  => 'João Teste',
            'customer_phone' => '11999999999',
            'address'        => 'Rua A, 100',
            'payment_method' => 'Pix',
            'items'          => [['id' => $product->id, 'name' => $product->name, 'price' => 25.00, 'quantity' => 2]],
            'subtotal'       => 50.00,
            'delivery_fee'   => 5.00,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'Realizado')
        ->assertJsonPath('data.total', '55.00');

    $this->assertDatabaseHas('orders', ['customer_phone' => '11999999999']);
});

it('recusa pedido quando produto está esgotado', function () {
    $store   = Store::factory()->create();
    $product = Product::factory()->for($store)->create(['is_available' => false]);

    $this->withHeaders(['X-Store-Slug' => $store->slug])
        ->postJson('/api/v1/orders', [...])
        ->assertStatus(422);
});
```

### Unit Test (testa service isolado, sem DB)
```php
// tests/Unit/Services/CouponServiceTest.php
it('calcula desconto percentual corretamente', function () {
    $coupon = Coupon::factory()->make([
        'discount_type'  => 'percentage',
        'discount_value' => 10,
        'min_order_value' => 0,
        'is_active' => true,
    ]);

    $repo    = Mockery::mock(CouponRepositoryInterface::class);
    $repo->shouldReceive('findByCode')->andReturn($coupon);
    $repo->shouldReceive('hasUsedCoupon')->andReturn(false);

    $service = new CouponService($repo);
    $result  = $service->validate('TOP10', 100.00, '11999999999');

    expect($result['discount_amount'])->toBe(10.0);
});
```

### Regras de teste
- **Feature tests**: sempre usar `RefreshDatabase` trait e Factories
- **Unit tests**: mockar dependências externas (repositories), nunca o banco
- **Cobertura mínima**: todo Service e toda Action precisa de teste
- **Nomenclatura**: `it('faz X quando Y')` — descreve comportamento, não implementação
- **Um assert por cenário** — testes com 10 asserts são difíceis de debugar

---

## 10. Padrão de Factories

Toda Model precisa de Factory para os testes.

```php
// database/factories/ProductFactory.php
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id'       => Store::factory(),
            'name'           => fake()->words(3, true),
            'description'    => fake()->sentence(),
            'price'          => fake()->randomFloat(2, 5, 80),
            'stock_unit'     => 'un',
            'is_available'   => true,
        ];
    }

    // States para cenários específicos
    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }

    public function withLimitedStock(int $qty = 5): static
    {
        return $this->state(['stock_quantity' => $qty]);
    }
}
```

---

## 11. Git Flow

```
main          ← produção (deploy automático)
  └── develop ← integração (base para todas as features)
        ├── feature/TB-001-autenticacao-sanctum
        ├── feature/TB-002-api-resources-orders
        └── fix/TB-003-coupon-validation-edge-case
```

### Nomenclatura de branches
```
feature/TB-{número}-{descricao-curta}
fix/TB-{número}-{descricao-curta}
refactor/TB-{número}-{descricao-curta}
```

### Conventional Commits
```
feat(order): implementa endpoint de listagem de pedidos
feat(coupon): adiciona validação de cupom por telefone
fix(tenant): corrige escopo quando store_id é null
test(order): adiciona testes de criação de pedido
refactor(product): extrai lógica de upload para ProductService
docs: atualiza DEVELOPMENT.md com padrão de Actions
```

### Checklist antes de abrir PR
- [ ] Testes passando (`docker compose exec app php artisan test`)
- [ ] Sem `dd()`, `dump()`, `var_dump()` no código
- [ ] FormRequest criado para o endpoint
- [ ] API Resource criado para a resposta
- [ ] Migration reversível (tem `down()`)

---

## 12. Criando uma Nova Feature (passo a passo)

Exemplo: **"Adicionar endpoint de histórico de pedidos do cliente"**

```bash
# 1. Branch
git checkout develop && git pull
git checkout -b feature/TB-010-historico-pedidos-cliente

# 2. Migration (se precisar de nova tabela/coluna)
docker compose exec app php artisan make:migration add_column_to_orders

# 3. Model (se nova tabela)
docker compose exec app php artisan make:model NomeModel

# 4. Factory
docker compose exec app php artisan make:factory NomeModelFactory

# 5. Repository Interface + Eloquent Repository (manual — seguir padrão existente)

# 6. Service (lógica de negócio)
docker compose exec app php artisan make:class app/Services/NomeService

# 7. FormRequest
docker compose exec app php artisan make:request NomeDomain/NomeRequest

# 8. Controller
docker compose exec app php artisan make:controller Api/V1/NomeController

# 9. API Resource
docker compose exec app php artisan make:resource NomeResource

# 10. Rota em routes/api.php

# 11. Testes (Feature + Unit)
docker compose exec app php artisan make:test Feature/NomeDomain/NomeTest
docker compose exec app php artisan make:test Unit/Services/NomeServiceTest --unit

# 12. Roda os testes
docker compose exec app php artisan test
```

---

## 13. Padrão de Resposta da API

Todas as respostas seguem o mesmo envelope:

```json
// Sucesso
{
    "success": true,
    "message": "OK",
    "data": { ... }
}

// Erro de validação (422)
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "customer_name": ["O campo nome é obrigatório."]
    }
}

// Erro de negócio (400/422)
{
    "success": false,
    "message": "Estoque insuficiente para 'X-Burguer'.",
    "errors": null
}

// Não encontrado (404)
{
    "success": false,
    "message": "Loja não encontrada ou inativa.",
    "errors": null
}
```

---

## 14. Comandos do Dia a Dia

```bash
# Entrar no container
docker compose exec app bash

# Migrations
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed   # ⚠ apaga tudo e recria

# Testes
php artisan test                          # todos
php artisan test --filter=PlaceOrder      # filtrado
php artisan test --coverage               # com cobertura

# Cache (quando algo estranho acontecer)
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Code style (Laravel Pint)
./vendor/bin/pint                    # corrige automaticamente
./vendor/bin/pint --test             # só verifica, não corrige

# Ver rotas
php artisan route:list --path=api/v1
```

---

## 15. Variáveis de Ambiente por Contexto

| Variável | Local | Produção |
|----------|-------|----------|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `DB_HOST` | `pgsql` (container) | IP/hostname do servidor |
| `CACHE_STORE` | `redis` | `redis` |
| `QUEUE_CONNECTION` | `redis` | `redis` |
| `TENANT_IDENTIFICATION` | `header` | `subdomain` |
| `FRONTEND_URL` | `http://localhost:5173` | `https://app.topburguer.com.br` |

---

## 16. Spec-Driven Development (SDD)

**Nenhuma feature começa com código — começa com uma spec.**

O guia completo está em `docs/SPEC_DRIVEN.md`. O fluxo resumido:

```
/new-spec {descrição}   → cria spec em docs/specs/ (Status: draft)
                        → revisar, responder perguntas em aberto
                        → mudar para Status: approved
/new-feature {spec}     → implementa a partir da spec aprovada
                        → testes passando → Status: implemented
```

### Por que não pular a spec?

Sem spec a IA (e o dev) implementa o que *acha* que foi pedido. Com spec, o contrato da API, as regras de negócio e os edge cases são acordados **antes** de qualquer código — eliminando refactoring de lógica e APIs que o frontend vai precisar quebrar.

### Specs ficam em `docs/specs/`

Convenção de nome: `{dominio}-{acao-kebab-case}.md`

```
docs/specs/
├── _TEMPLATE.md                      ← template para novas specs
├── store-perfil-publico.md           ← exemplo (já implementado)
└── order-listagem-por-telefone.md    ← exemplo (a implementar)
```

---

## 17. Slash Commands (Claude Code)

Atalhos disponíveis em `.claude/commands/`:

| Comando | O que faz |
|---------|-----------|
| `/new-spec {feature}` | Cria spec em `docs/specs/` com template completo |
| `/new-feature {spec}` | Implementa feature a partir de spec aprovada |
| `/review-code {arquivo}` | Revisa código contra os padrões deste projeto |

Para usar no Claude Code: `/new-spec listagem de pedidos por telefone`

---

*Última atualização: 2026-06-06 — João Pedro / Claude*
