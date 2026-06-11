# Top Burguer — Documentação do Schema

> **Stack:** PostgreSQL 16 · Laravel 12 · Eloquent ORM  
> **Multi-tenancy:** Row-level isolation via `store_id` em todas as tabelas de dados da loja.  
> O trait `BelongsToTenant` aplica automaticamente `WHERE store_id = ?` em todas as queries.

---

## Visão geral (ER simplificado)

```
users (Sanctum)
  └─ profiles (RBAC: role + store_id)

stores
  ├─ store_settings   (1:1 — configurações editáveis)
  ├─ store_statuses   (1:1 — aberto/fechado em tempo real)
  ├─ profiles         (1:N — colaboradores da loja)
  ├─ product_categories (1:N)
  │     └─ products   (1:N)
  ├─ orders           (1:N)
  │     └─ [items]    (JSONB — snapshot imutável de produtos)
  ├─ coupons          (1:N)
  │     └─ coupon_usages (1:N)
  └─ coupon_usages    (1:N — via coupon e order)
```

---

## Tabelas

### `users`
Autenticação via Laravel Sanctum. Não é tenant — existe no nível de plataforma.

| Coluna              | Tipo      | Notas                    |
|---------------------|-----------|--------------------------|
| id                  | bigint PK | auto-increment           |
| name                | string    |                          |
| email               | string    | unique                   |
| email_verified_at   | timestamp | nullable                 |
| password            | string    | bcrypt                   |
| remember_token      | string    | nullable                 |
| created_at/updated_at | timestamps |                        |

Tabelas auxiliares: `password_reset_tokens`, `sessions`.

---

### `stores`
Tenant raiz. Cada loja é isolada por `store_id` nas tabelas filhas.

| Coluna     | Tipo      | Notas                   |
|------------|-----------|-------------------------|
| id         | uuid PK   | `HasUuids`              |
| name       | string    |                         |
| slug       | string    | unique — resolve tenant via `X-Store-Slug` |
| logo_url   | text      | nullable                |
| is_active  | boolean   | default true            |
| created_at/updated_at | timestamps | |

**Relacionamentos:**
- `hasOne` StoreSettings
- `hasOne` StoreStatus
- `hasMany` Profile
- `hasMany` ProductCategory
- `hasMany` Product
- `hasMany` Order
- `hasMany` Coupon
- `hasMany` CouponUsage

---

### `profiles`
RBAC — vincula um `User` ao seu papel na plataforma ou em uma loja específica.

| Coluna     | Tipo      | Notas                                     |
|------------|-----------|-------------------------------------------|
| id         | uuid PK   |                                           |
| user_id    | bigint FK | → users.id · unique · cascadeOnDelete    |
| store_id   | uuid FK   | → stores.id · nullable · nullOnDelete    |
| role       | string    | ver roles abaixo                         |
| is_active  | boolean   | default true                             |
| created_at/updated_at | timestamps | |

**Roles disponíveis:**

| Role             | Nível      | store_id |
|------------------|------------|----------|
| super_admin      | Plataforma | null     |
| saas_support     | Plataforma | null     |
| store_owner      | Loja       | obrigatório |
| store_manager    | Loja       | obrigatório |
| kitchen_staff    | Loja       | obrigatório |
| delivery_driver  | Loja       | obrigatório |

> `store_id = null` → role de plataforma (acesso global).  
> `store_id preenchido` → role vinculado àquela loja.

---

### `store_settings`
Configurações editáveis da loja. **Singleton** — exatamente 1 registro por loja.

| Coluna              | Tipo        | Notas                              |
|---------------------|-------------|------------------------------------|
| id                  | bigint PK   |                                    |
| store_id            | uuid FK     | → stores.id · unique · cascade     |
| store_name          | string      | nullable                           |
| store_description   | text        | nullable                           |
| store_address       | text        | nullable                           |
| whatsapp_number     | string(20)  | nullable                           |
| maps_url            | text        | nullable                           |
| opening_hours       | json        | `[{ day, hours }]`                 |
| neighborhood_fees   | json        | `{ "bairro": valor_decimal }`      |
| minimum_order       | decimal(10,2) | default 0.00                     |
| default_delivery_fee | decimal(10,2) | default 0.00                   |
| created_at/updated_at | timestamps | |

> Nomes de campos alinhados com o schema Supabase para compatibilidade com o frontend existente.

---

### `store_statuses`
Estado operacional da loja em tempo real (aberta/fechada).

| Coluna     | Tipo    | Notas                         |
|------------|---------|-------------------------------|
| id         | bigint PK |                             |
| store_id   | uuid FK | → stores.id · unique · cascade |
| is_open    | boolean | default false                 |
| created_at/updated_at | timestamps | |

---

### `product_categories`
Categorias do cardápio. Escopadas por tenant via `BelongsToTenant`.

| Coluna     | Tipo    | Notas          |
|------------|---------|----------------|
| id         | bigint PK |              |
| store_id   | uuid FK | → stores.id    |
| name       | string  |                |
| sort_order | integer | default 0 — ordem de exibição |
| is_active  | boolean | default true   |
| created_at/updated_at | timestamps | |

---

### `products`
Itens do cardápio. Escopados por tenant via `BelongsToTenant`.

| Coluna      | Tipo          | Notas                                  |
|-------------|---------------|----------------------------------------|
| id          | bigint PK     |                                        |
| store_id    | uuid FK       | → stores.id                            |
| category_id | bigint FK     | → product_categories.id · nullable     |
| name        | string        |                                        |
| description | text          | nullable                               |
| price       | decimal(10,2) |                                        |
| image_url   | text          | nullable                               |
| stock       | integer       | nullable — null = estoque ilimitado    |
| is_active   | boolean       | default true                           |
| created_at/updated_at | timestamps | |

**Helper:** `hasStock(int $quantity): bool` — retorna `true` se `stock === null` (ilimitado) ou `stock >= $quantity`.

---

### `orders`
Pedidos dos clientes. Escopados por tenant.

| Coluna           | Tipo          | Notas                                       |
|------------------|---------------|---------------------------------------------|
| id               | bigint PK     |                                             |
| store_id         | uuid FK       | → stores.id                                 |
| customer_name    | string        |                                             |
| customer_phone   | string(20)    |                                             |
| address          | text          |                                             |
| items            | json          | **snapshot imutável** — ver abaixo          |
| subtotal         | decimal(10,2) |                                             |
| delivery_fee     | decimal(10,2) | default 0.00                                |
| discount         | decimal(10,2) | default 0.00                                |
| total            | decimal(10,2) |                                             |
| status           | string(50)    | ver fluxo abaixo                            |
| payment_method   | string(50)    |                                             |
| notes            | text          | nullable                                    |
| coupon_code      | string(50)    | nullable — código usado (desnormalizado)    |
| rating           | integer       | nullable, de 1 a 5                          |
| feedback_text    | text          | nullable                                    |
| production_started_at | timestamp | nullable, timezone                          |
| dispatched_at    | timestamp     | nullable, timezone                          |
| channel          | string(20)    | default 'delivery' (delivery/mesa/balcao)   |
| table_number     | string(50)    | nullable                                    |
| created_at/updated_at | timestamps | |

**Campo `items` (JSONB):**
```json
[
  {
    "product_id": "uuid",
    "name": "X-Burguer",
    "price": 29.90,
    "quantity": 2,
    "subtotal": 59.80
  }
]
```
> Snapshot imutável: preserva nome e preço do produto no momento da compra, independente de alterações futuras no cardápio. Mesmo design do Supabase original.

**Fluxo de status (`STATUS_FLOW`):**

```
pendente → confirmado → em_preparo → saiu_para_entrega → entregue
                                  ↘ rejeitado
```

> `OrderService` replica a lógica das triggers do Supabase:
> - `tr_order_stock_decrement` → decrementa estoque atomicamente no checkout
> - `tr_order_stock_restock` → repõe estoque quando status = `rejeitado`

---

### `coupons`
Cupons de desconto. Escopados por tenant.

| Coluna          | Tipo          | Notas                                      |
|-----------------|---------------|--------------------------------------------|
| id              | bigint PK     |                                            |
| store_id        | uuid FK       | → stores.id                                |
| code            | string(50)    |                                            |
| discount_type   | string(20)    | `percentage` \| `fixed` \| `free_delivery` |
| discount_value  | decimal(10,2) | default 0.00                               |
| min_order_value | decimal(10,2) | default 0.00                               |
| max_uses        | integer       | nullable — null = ilimitado                |
| current_uses    | integer       | default 0                                  |
| starts_at       | timestamp     | default now                                |
| expires_at      | timestamp     | nullable                                   |
| is_active       | boolean       | default true                               |
| created_at/updated_at | timestamps | |

**Índice parcial único:**
```sql
CREATE UNIQUE INDEX idx_unique_active_coupon_per_store
ON coupons (store_id, code)
WHERE is_active = true AND (max_uses IS NULL OR current_uses < max_uses);
```
> Permite reutilização de código entre campanhas antigas inativas/esgotadas, preservando histórico de relatórios.

**Helpers no Model:**
- `isValid(): bool` — verifica atividade, datas e limite de usos
- `calculateDiscount(float $subtotal, float $deliveryFee): float` — retorna valor do desconto

---

### `coupon_usages`
Registro de uso de cupom por pedido. Escopado por tenant.

| Coluna         | Tipo       | Notas                              |
|----------------|------------|------------------------------------|
| id             | bigint PK  |                                    |
| store_id       | uuid FK    | → stores.id                        |
| coupon_id      | bigint FK  | → coupons.id · cascade             |
| order_id       | bigint FK  | → orders.id · cascade              |
| customer_phone | string(20) | identificador do cliente anônimo   |
| used_at        | timestamp  |                                    |
| created_at/updated_at | timestamps | |

> `customer_phone` sem FK — clientes são anônimos (sem tabela própria), compatível com o modelo original do Supabase.

---

## Decisões arquiteturais

### Multi-tenancy row-level
Toda tabela de dados de loja tem `store_id uuid FK → stores.id`. O trait `BelongsToTenant` aplica o escopo automaticamente — nunca filtrar `store_id` manualmente nas queries.

### Resolução de tenant
`IdentifyTenant` middleware resolve o tenant pelo header `X-Store-Slug` (dev) ou subdomínio (prod). Registra `current_tenant_id` e `current_store` no container Laravel. Valor sentinela: `false` (não `null`) — necessário porque `app()->instance('key', null)` não funciona com `isset()`.

### UUIDs em stores e profiles
`stores.id` e `profiles.id` são UUID (compatível com Supabase). FKs para stores usam `foreignUuid()`. Demais tabelas usam bigint auto-increment.

### Snapshot de itens em orders
`orders.items` é JSON imutável — snapshot do produto no momento da compra. Garante integridade histórica mesmo que produto seja renomeado ou removido.

### Singleton por loja
`store_settings` e `store_statuses` têm `store_id unique` — garantia de exatamente 1 registro por loja no nível de banco.

### Cupons com índice parcial
Unicidade apenas entre cupons ativos e não esgotados. Permite reciclar códigos sazonalmente sem perder histórico.

---

## Mapeamento Supabase → Laravel

| Entidade Supabase     | Tabela Laravel       | Observações                              |
|-----------------------|----------------------|------------------------------------------|
| `stores`              | `stores`             | UUID mantido                             |
| `profiles`            | `profiles`           | `email` substituído por `user_id FK`     |
| `menu_categories`     | `product_categories` | Renomeado                                |
| `menu_items`          | `products`           | Renomeado; `stock` nullable = ilimitado  |
| `orders` + trigger    | `orders` + Service   | Lógica das triggers migrada pro Service  |
| `store_settings`      | `store_settings`     | Campos idênticos para compatibilidade    |
| `store_status`        | `store_statuses`     |                                          |
| `coupons`             | `coupons`            | Índice parcial mantido                   |
| `coupon_usages`       | `coupon_usages`      |                                          |
| `auth.users`          | `users` (Sanctum)    | JWT via Sanctum em vez de Supabase Auth  |
