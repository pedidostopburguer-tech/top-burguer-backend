# Planejamento: Alinhamento Backend ↔ Frontend (Multi-tenancy)

> Criado em 11/06/2026. Contexto: o frontend (`top-burguer`, hoje em Supabase) avançou bastante
> (Cards 11-15, Modo Mesa, refatoração SOLID + apiClient multi-tenant) e o backend Laravel
> (`top-burguer-backend`) precisa fechar alguns gaps de schema e arquitetura antes da
> integração real entre os dois. Este documento lista o que falta, em ordem de prioridade.
>
> Quando for implementar algum item, seguir o fluxo normal: `/new-spec` para detalhar em
> `docs/specs/{dominio}-{feature}.md` e depois `/new-feature`.

---

## 1. Fechar gap de schema da tabela `orders` (alta prioridade) — ✅ CONCLUÍDO (fe8185a)

Implementado na migration `2026_06_10_195626_add_feedback_and_bi_columns_to_orders_table`:
`rating`, `feedback_text`, `production_started_at`, `dispatched_at`, `channel` (default `delivery`)
e `table_number` (nullable). `Order` model, `OrderResource` e `docs/DATABASE.md` já atualizados.

---

## 2. Modo Mesa: persistência server-side — ✅ IMPLEMENTADO (tabela `tables` dedicada)

Implementado conforme `docs/specs/store-tables.md`: migration `2026_06_11_000000_create_tables_table`,
`Table` model (com enum `App\Enums\TableStatus` para `status`), repository/service, FormRequests,
`TableController` + `TableResource`, factory e testes (`TableManagementTest`,
`TableServiceTest`). Rotas em `/api/v1/admin/tables`, protegidas por `tenant.role:store_owner,store_manager`.

Falta apenas rodar `php artisan migrate` + `php artisan test` no container Docker para validar
e aplicar a migration (não disponível no sandbox desta sessão).

<details>
<summary>Decisão original (histórico)</summary>

Voltamos atrás da ideia de JSONB simples em `store_settings`. Pensando a longo prazo, o ganho de
ter `tables` como entidade própria compensa o esforço extra: status de disponibilidade em tempo
real (livre/ocupada/limpeza), QR token rotativo por mesa e métricas por mesa (giro, faturamento,
tempo de ocupação) só são viáveis com registros individuais.

**Schema proposto — tabela `tables`:**

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint PK | |
| `store_id` | uuid FK → stores | `BelongsToTenant` |
| `number` | varchar(10) | identificador exibido (ex: "01", "VIP-2") |
| `qr_token` | varchar único | usado na URL do QR Code (`?mesa={number}&t={qr_token}`), rotacionável |
| `capacity` | integer nullable | nº de lugares (info útil pro PDV/Card 14 mais pra frente) |
| `status` | varchar(20) default `'livre'` | `livre`, `ocupada`, `limpeza` — controle manual do staff |
| `is_active` | boolean default true | mesa desativada some do painel sem perder histórico |
| timestamps | | |

Índice único `(store_id, number)`.

**Relação com `orders.table_number`:** mantém como está (string denormalizada, já no item 1) —
preserva o histórico mesmo se a mesa for renomeada/excluída depois. `tables.number` é a fonte
da verdade atual; `orders.table_number` é o snapshot no momento do pedido.

**Disponibilidade:** `status` é setado manualmente pelo staff (ex: ao fechar a comanda, marcar
"limpeza" → depois "livre"). Pode evoluir depois para auto-transição (`ocupada` quando entra
pedido `channel=mesa`, `limpeza` quando o pedido vira `Finalizado`), mas começar manual é mais
simples e testável.

**Endpoints:** CRUD em `/admin/tables`, protegido por `tenant.role:store_owner,store_manager`,
mais talvez `PATCH /admin/tables/{id}/status` para troca rápida de status pelo garçom/staff.

**Próximo passo:** `/new-spec` para detalhar em `docs/specs/store-tables.md` (contratos,
regras de unicidade do `number`/`qr_token`, edge cases de exclusão de mesa com pedidos ativos,
testes planejados).

</details>

---

## 3. Portar lógica de estoque atômico (triggers do Supabase)

`tr_order_stock_decrement` e `tr_order_stock_restock` (Supabase) precisam de equivalente no
Postgres do Laravel:

- **Decremento atômico no checkout:** ao criar `order`, decrementar `stock_quantity` dos
  produtos envolvidos dentro de uma transaction com `lockForUpdate()`. Se algum item não tiver
  estoque suficiente, abortar (rollback) com mensagem amigável. Se zerar, marcar
  `is_available = false`.
- **Restock automático na recusa:** quando `OrderService::updateStatus` muda status para
  `Recusado`, devolver as quantidades dos itens (`items` JSONB) ao `stock_quantity` e reativar
  `is_available = true`.

Pode ser feito como trigger SQL na migration de `orders` (mais próximo do que já existe) ou
inteiramente no `OrderService` (mais testável com PHPUnit). Preferência: lógica no Service,
com transaction + lock — mais fácil de testar e de manter junto da regra de negócio.

---

## 4. Endpoints de BI/Analytics no backend (Card 15)

Hoje o Card 15 (heatmap por bairro, distribuição por canal, tempo médio de preparo, perdas por
recusa) é calculado **no cliente**, baixando todos os pedidos via Supabase. Em multi-tenant isso
é ruim por performance (lojas com histórico grande) e por segurança (não depender só de RLS para
volume de dados de faturamento).

**Proposta:** `AnalyticsController` + `AnalyticsService` com queries agregadas
(`GROUP BY`, `AVG`, filtros de período), sempre escopadas por `BelongsToTenant`:

- `GET /admin/analytics/overview?from=&to=` — faturamento, ticket médio, total de pedidos,
  tempo médio de preparo (`AVG(dispatched_at - production_started_at)`)
- `GET /admin/analytics/by-neighborhood` — ranking de bairros por faturamento/volume
- `GET /admin/analytics/by-channel` — receita por `channel` (delivery/mesa/balcão)
- `GET /admin/analytics/cancellations` — faturamento perdido + ranking de `rejection_reason`
- `GET /admin/analytics/satisfaction` — média de `rating` + últimos `feedback_text`

---

## 5. Migrar autenticação do painel (Card 16 — ainda backlog)

O `useCollaboratorAuth.js` do frontend ainda usa RC4 + localStorage. O backend já tem
Sanctum/JWT funcionando (register/login/logout/me/reset). Esse é o elo que falta para realmente
"ligar" o frontend no backend multi-tenant — enquanto o painel autentica via RC4 local, o
frontend continua acoplado ao Supabase de uma loja só.

Trabalho é majoritariamente no frontend (trocar `useCollaboratorAuth` por chamadas a
`/api/v1/auth/login` + Sanctum token), mas vale revisar no backend:

- CORS liberado para os domínios do frontend (`doqui.com.br`, `app.doqui.com.br`, etc.)
- Expiração/refresh de token adequados para sessão "lembrar por 30 dias"

---

## 6. Realtime (Kanban, KDS, campainha, sincronização de estoque)

Boa parte das features premium depende do Supabase Realtime. Migrar para Laravel exige
broadcasting (Reverb ou Pusher) com:

- **Canais privados por `store_id`** (ex: `private-store.{store_id}.orders`)
- Autorização de canal validando que o usuário autenticado pertence àquele `store_id`
  (evita lojista A ouvir eventos da loja B)
- Eventos: `OrderCreated`, `OrderStatusUpdated`, `ProductStockUpdated`

---

## 7. Storage de imagens dos produtos

`image_url` já existe na migration de `products`, mas falta a infraestrutura de
upload/compressão/exclusão (hoje feita no Supabase Storage + canvas no frontend).

**Proposta:** endpoint de upload (`POST /admin/products/{id}/image`) salvando em storage
S3-compatível (ou local em dev) com path isolado por tenant:
`stores/{store_id}/products/{product_id}.webp`. Ao trocar/excluir produto, remover o arquivo
antigo (garbage collection, como já existe no `productService.js` do frontend).

---

## 8. Endpoint de avaliação de pedido (Card 12)

Falta uma rota pública para o cliente avaliar o próprio pedido depois de `Finalizado`:

```
PATCH /api/v1/orders/{id}/feedback
Body: { customer_phone, rating, feedback_text }
```

Validar que `customer_phone` bate com o pedido antes de gravar `rating`/`feedback_text`
(evita que qualquer pessoa avalie pedidos alheios).

---

## 9. PDV / Balcão (Card 14 — ainda backlog no frontend)

Quando for implementado no frontend, o backend já tem a base pronta. Só precisa de:

```
POST /api/v1/admin/orders/balcao
```

Protegido por `tenant.role:store_owner,store_manager`, cria o pedido com `channel = balcao`,
status inicial configurável (`Finalizado` ou `Em produção`), decrementa estoque na hora
(reaproveitando a lógica do item 3) e retorna dados prontos para impressão térmica.

---

## 10. Testes de isolamento entre tenants

Os 19/19 testes atuais cobrem auth/RBAC. Antes de ligar o frontend de verdade, criar uma suíte
que cria 2 stores (A e B) e garante que:

- Usuário da loja A nunca lista/edita/exclui produtos, pedidos ou cupons da loja B
- `BelongsToTenant` bloqueia corretamente mesmo com IDs adivinhados na URL
- Rotas públicas (`GET /store`, `GET /products`, `POST /orders`) respeitam o `store_id`
  resolvido pelo `IdentifyTenant` (header `X-Store-Slug` / subdomínio)

Esse é o tipo de bug que não aparece em dev com 1 loja só, mas é o que mais assusta cliente
pagante em produção.

---

## 11. Padronização com PHP Enums

> Criado em 11/06/2026, junto com a spec `store-tables.md`. Contexto: ao modelar `Table.status`
> como `App\Enums\TableStatus`, surgiu a pergunta "o que mais no sistema deveria ser enum?".

### ✅ Concluído (refactor de baixo risco, sem migration)

Os seguintes campos já eram `varchar` com conjunto fixo de valores e ganharam enum PHP +
`casts()` no Model, sem alterar schema (cast aceita string em escrita, serializa como `->value`
em JSON — `Resource`s não precisaram mudar):

- `Order.channel` → `App\Enums\OrderChannel` (`delivery`, `mesa`, `balcao`)
- `Coupon.discount_type` → `App\Enums\CouponDiscountType` (`percentage`, `fixed`, `free_delivery`)
  — substituiu a constante `Coupon::TYPES`
- `Product.stock_unit` → `App\Enums\ProductStockUnit` (`un`, `porção`, `g`, `ml`)

Validação nos Controllers passou a usar `new Enum(XxxEnum::class)` (`Illuminate\Validation\Rules\Enum`)
no lugar de `in:a,b,c`.

### 🔜 Specs futuras (maior escopo — requerem `/new-spec` dedicado)

- **`Order.status` → enum + `STATUS_FLOW`** (`App\Enums\OrderStatus`): hoje são as constantes
  `Order::STATUSES` e `Order::STATUS_FLOW` (arrays de strings em PT-BR: `'Realizado'`,
  `'Em produção'`, etc.). Vira enum, mas a transição de estado (`nextStatus()`,
  `OrderService::updateStatus`) e a validação em `OrderController::updateStatus`
  (`required|in:Realizado,Em produção,...`) precisam de cuidado extra — strings com acento/espaço
  como `case` de enum funcionam, mas vale revisar se não é melhor usar valores ASCII
  (`'em_producao'`) com label de exibição separado. Tocar em testes de Kanban/fluxo de status.
- **`Profile.role` → enum** (`App\Enums\UserRole`): RBAC-sensível. Hoje `Profile::ROLES` e
  `Profile::PLATFORM_ROLES` são arrays de strings, e os middlewares `role:` / `tenant.role:`
  recebem os nomes de role como **parâmetros de string nas rotas** (`->middleware('role:store_owner,store_manager')`).
  Migrar para enum exige decidir se os middlewares passam a aceitar `UserRole::case()->value` ou
  se mantém strings nas rotas e só o Model/`casts()` usa o enum. Fazer como spec própria,
  cobrindo todos os pontos de RBAC (testes de Auth/RBAC existentes não podem quebrar).
- **FormRequest em `ProductController` e `CouponController`**: ainda usam `$request->validate([...])`
  inline (diferente do padrão FormRequest adotado em `Table`). Padronizar para
  `StoreProductRequest`/`UpdateProductRequest` e `StoreCouponRequest`/`UpdateCouponRequest`,
  já usando `new Enum(...)` para `stock_unit`/`discount_type`.
- **Policies/Gates**: direção futura discutida junto com a spec de `tables` — autorização
  hoje é 100% via middlewares `role:`/`tenant.role:`. Policies dariam controle por recurso
  (ex: "só o dono pode editar este cupom"), mas não é urgente — registrar aqui para não perder
  o contexto da decisão.

### 🔍 Candidato adicional (avaliar)

- **`Order.payment_method`**: hoje é `'required|string|max:100'` (texto livre), mas a
  `OrderFactory` só usa `dinheiro`, `pix`, `cartao_credito`, `cartao_debito`. Antes de virar
  enum, confirmar com o frontend se cada loja pode cadastrar formas de pagamento próprias
  (texto livre intencional) ou se o conjunto é fixo — só faz sentido enum se for fixo.

---

## Ordem sugerida de execução

1. Item 1 (schema `orders`) — rápido, desbloqueia 4, 8 e 10
2. Item 3 (estoque atômico) — crítico para correção, base para o item 9
3. Item 10 (testes de isolamento) — rede de segurança antes de avançar
4. Item 8 (endpoint de feedback) — pequeno, fecha o Card 12 no backend
5. Item 4 (analytics endpoints) — fecha o Card 15 no backend
6. Item 2 (tabela `tables`) — habilita Modo Mesa multi-dispositivo
7. Item 7 (storage de imagens)
8. Item 6 (realtime/broadcasting)
9. Item 5 (auth do painel — Card 16, maior esforço, principalmente frontend)
10. Item 9 (PDV/Balcão — depende do frontend implementar o Card 14 primeiro)
11. Item 11 (Enums futuros: `Order.status`, `Profile.role`, FormRequests Product/Coupon, Policies) —
    fazer junto com os respectivos itens acima quando essas áreas forem mexidas (ex: `Order.status`
    junto do item 9/Kanban, `Profile.role` junto do item 5/auth do painel)
