# Planejamento: Alinhamento Backend ↔ Frontend (Multi-tenancy)

> Criado em 11/06/2026. Contexto: o frontend (`top-burguer`, hoje em Supabase) avançou bastante
> (Cards 11-15, Modo Mesa, refatoração SOLID + apiClient multi-tenant) e o backend Laravel
> (`top-burguer-backend`) precisa fechar alguns gaps de schema e arquitetura antes da
> integração real entre os dois. Este documento lista o que falta, em ordem de prioridade.
>
> Quando for implementar algum item, seguir o fluxo normal: `/new-spec` para detalhar em
> `docs/specs/{dominio}-{feature}.md` e depois `/new-feature`.

---

## 1. Fechar gap de schema da tabela `orders` (alta prioridade)

O Supabase já tem essas colunas (ver `db_schema.md` do frontend), o backend ainda não:

- `rating` (INTEGER, nullable) — nota de 1 a 5 dada pelo cliente (Card 12)
- `feedback_text` (TEXT, nullable) — comentário do cliente (Card 12)
- `production_started_at` (TIMESTAMP TZ, nullable) — marcado ao entrar em "Em produção" (Card 15)
- `dispatched_at` (TIMESTAMP TZ, nullable) — marcado ao sair para entrega/ser servido (Card 15)

Adicionalmente, considerar um campo estruturado de **canal/origem** do pedido
(`channel` enum: `delivery`, `mesa`, `balcao`, ou `table_number` nullable), já que hoje o
frontend infere "pedido de mesa" por busca textual ("mesa X") — em multi-tenant isso deveria
ser dado estruturado, não heurística de string.

**Migration:** nova migration `add_feedback_and_bi_columns_to_orders_table` +
atualizar `Order` model (casts, fillable) e `docs/DATABASE.md`.

---

## 2. Modo Mesa: decidir persistência server-side

Hoje o `TablesTab.jsx` do frontend é 100% local (localStorage, até 50 mesas por navegador/dispositivo).
Isso não escala em multi-tenant: troca de dispositivo perde as mesas, e QR Codes não são estáveis
entre staff diferentes.

**Proposta:** tabela `tables`:

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint PK | |
| `store_id` | uuid FK → stores | `BelongsToTenant` |
| `number` | varchar/int | número/identificador da mesa |
| `qr_token` | varchar único | usado na URL do QR Code (`?mesa=XX&t={qr_token}`) |
| `is_active` | boolean default true | |
| timestamps | | |

Endpoints CRUD protegidos por `tenant.role:store_owner,store_manager`.

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
