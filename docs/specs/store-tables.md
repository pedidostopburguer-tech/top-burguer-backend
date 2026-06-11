# Spec: Gestão de Mesas (Modo Mesa Multi-tenant)

**Domínio:** Table
**Status:** implemented (pendente migrate/test no Docker)
**Branch:** feature/TB-018-store-tables
**Criado em:** 2026-06-11

---

## Contexto e Motivação

O frontend (`top-burguer`) já tem o "Modo Mesa" (Card 13): o cliente acessa o cardápio via QR Code
(`?mesa=XX`), o checkout presencial é simplificado, e o Kanban agrupa pedidos por mesa. Hoje a
lista de mesas (`TablesTab.jsx`) vive 100% em `localStorage` — até 50 mesas por navegador/dispositivo.

Em multi-tenant isso não escala: troca de dispositivo perde a configuração, dois funcionários veem
listas de mesas diferentes, e não há como saber se uma mesa está livre/ocupada sem abrir o Kanban
e procurar manualmente pelos pedidos ativos.

Esta spec cria a entidade `tables`, server-side, isolada por `store_id` (`BelongsToTenant`),
substituindo o `localStorage` do `TablesTab.jsx`. Cobre:

1. CRUD de mesas pelo painel administrativo.
2. Status manual por mesa (`livre`, `ocupada`, `limpeza`) para visão rápida do staff.
3. `qr_token` por mesa, para preparar uma futura validação de QR Code mais segura que a heurística
   atual (`?mesa=XX` puro).

`orders.table_number` (já existente, adicionado em `add_feedback_and_bi_columns_to_orders_table`)
**não muda** — continua sendo um snapshot string do número da mesa no momento do pedido,
preservando histórico mesmo se a mesa for renomeada/excluída depois.

---

## Contrato da API

Todas as rotas abaixo ficam sob `/api/v1/admin/tables`, autenticadas via `auth:sanctum` e
protegidas por `tenant.role:store_owner,store_manager`.

### `GET /api/v1/admin/tables`

Lista todas as mesas da loja (tenant resolvido via `IdentifyTenant`).

**Query params opcionais:**
- `status` — filtra por `livre`, `ocupada`, `limpeza`
- `is_active` — `true`/`false` (default: retorna só ativas)

**Response — sucesso (200):**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 1,
      "number": "01",
      "capacity": 4,
      "status": "livre",
      "is_active": true,
      "qr_token": "a1b2c3d4e5f6"
    }
  ]
}
```

### `POST /api/v1/admin/tables`

Cria uma nova mesa.

**Request body:**
```json
{
  "number": "12",
  "capacity": 4
}
```

**Response — sucesso (201):** mesma forma do item da listagem, com `status: "livre"` e
`qr_token` gerado automaticamente pelo backend (não enviado no request).

**Response — erros esperados:**
| Status | Quando |
|--------|--------|
| 422 | `number` ausente/vazio |
| 422 | `number` já existe para essa loja (índice único `store_id` + `number`) |
| 401 | Não autenticado |
| 403 | Usuário autenticado mas sem role `store_owner`/`store_manager` |

### `PUT /api/v1/admin/tables/{id}`

Atualiza `number` e/ou `capacity` de uma mesa existente.

**Request body:**
```json
{
  "number": "12-A",
  "capacity": 6
}
```

**Response — erros esperados:**
| Status | Quando |
|--------|--------|
| 404 | Mesa não encontrada (ou pertence a outra loja — `BelongsToTenant` bloqueia) |
| 422 | Novo `number` colide com outra mesa ativa da mesma loja |

### `PATCH /api/v1/admin/tables/{id}/status`

Troca rápida de status (usada pelo staff ao sentar/limpar uma mesa).

**Request body:**
```json
{
  "status": "ocupada"
}
```

**Response — erros esperados:**
| Status | Quando |
|--------|--------|
| 404 | Mesa não encontrada |
| 422 | `status` fora de `livre`, `ocupada`, `limpeza` |

### `PATCH /api/v1/admin/tables/{id}/rotate-qr`

Gera um novo `qr_token` para a mesa (invalida QR Codes impressos anteriormente — útil se o papel
for roubado/copiado).

**Response — sucesso (200):** item da mesa com `qr_token` novo.

### `DELETE /api/v1/admin/tables/{id}`

Remove (soft, via `is_active = false`) uma mesa.

**Regra:** se existir pedido com `channel = 'mesa'`, `table_number = {number da mesa}` e status
fora de `Finalizado`/`Recusado` (ou seja, comanda em aberto), bloquear a exclusão.

**Response — erros esperados:**
| Status | Quando |
|--------|--------|
| 404 | Mesa não encontrada |
| 409 | Mesa possui pedido(s) em aberto — não pode ser desativada |

---

## Schema (`tables` table)

| Campo | Tipo | Restrições | Descrição |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `store_id` | uuid | FK → `stores`, `cascadeOnDelete` | `BelongsToTenant` |
| `number` | varchar(10) | not null | identificador exibido (ex: "01", "VIP-2") |
| `qr_token` | varchar(32) | not null, unique | usado na URL do QR Code (`?mesa={number}&t={qr_token}`) |
| `capacity` | integer | nullable | nº de lugares (uso futuro: PDV/Card 14, otimização de fila) |
| `status` | varchar(20) | not null, default `'livre'` | `livre`, `ocupada`, `limpeza` |
| `is_active` | boolean | not null, default `true` | mesa "excluída" vira `false`, preserva histórico |
| `created_at` / `updated_at` | timestamps | | |

**Índices:**
- único composto `(store_id, number)` — impede duas mesas com o mesmo número na mesma loja
  (mesmo soft-deletadas? **ver Pergunta em Aberto 1**)
- único `qr_token`

---

## Regras de Negócio

- **RN-01 (Geração de `qr_token`):** ao criar uma mesa, o backend gera automaticamente um
  `qr_token` aleatório (ex: `Str::random(12)`), nunca recebido do cliente.
- **RN-02 (Unicidade de `number` por loja):** duas mesas ativas da mesma loja não podem ter o
  mesmo `number`. Validado via FormRequest + índice de banco como segunda camada.
- **RN-03 (Status default):** toda mesa nova nasce com `status = 'livre'`.
- **RN-04 (Bloqueio de exclusão com comanda aberta):** `DELETE` retorna 409 se houver pedido
  `channel = 'mesa'` com `table_number` igual ao `number` da mesa e `status` não em
  (`Finalizado`, `Recusado`).
- **RN-05 (Rotação de QR):** `rotate-qr` gera novo `qr_token` único, sobrescrevendo o anterior
  (QR Codes impressos com o token antigo deixam de ser válidos para uma futura validação
  server-side — ver Pergunta em Aberto 2).
- **RN-06 (Isolamento multi-tenant):** todas as operações respeitam `BelongsToTenant` —
  `store_id` nunca vem do request, sempre de `app('current_tenant_id')`.

---

## Edge Cases

- [ ] Criar mesa com `number` igual a uma mesa **inativa** (`is_active = false`) da mesma loja —
  permitir e reativar a antiga, ou criar uma nova linha? (ver Pergunta em Aberto 1)
- [ ] `PATCH /status` para o mesmo status atual (ex: `livre` → `livre`) — deve ser idempotente,
  retornar 200 normalmente.
- [ ] Loja com 0 mesas cadastradas — `GET /admin/tables` retorna `data: []`, não erro.
- [ ] `capacity` negativo ou zero — rejeitar com 422 (`min:1`).
- [ ] Tentar acessar/editar mesa de outro tenant via ID adivinhado — `BelongsToTenant` deve
  retornar 404 (não 403, para não vazar existência do recurso).

---

## Testes Planejados

### Feature Tests (`tests/Feature/Table/TableManagementTest.php`)
```
it('lista mesas da loja autenticada')
it('cria mesa com qr_token gerado automaticamente')
it('rejeita criação de mesa com number duplicado na mesma loja')
it('permite o mesmo number em lojas diferentes')
it('atualiza number e capacity de uma mesa')
it('atualiza status da mesa via PATCH /status')
it('rejeita status inválido')
it('rotaciona o qr_token e invalida o anterior')
it('bloqueia exclusão de mesa com pedido em aberto (409)')
it('permite exclusão de mesa sem pedidos em aberto')
it('retorna 404 ao tentar acessar mesa de outro tenant')
it('bloqueia acesso sem role store_owner/store_manager (403)')
```

### Unit Tests (`tests/Unit/Services/TableServiceTest.php`)
```
it('gera qr_token único ao criar mesa')
it('detecta pedido em aberto corretamente para bloquear exclusão')
it('considera Finalizado e Recusado como "sem pedido em aberto"')
```

---

## Arquivos a Criar/Modificar

### Novos
- [x] `database/migrations/2026_06_11_000000_create_tables_table.php`
- [x] `app/Models/Table.php`
- [x] `app/Enums/TableStatus.php` (não previsto originalmente — adicionado a pedido do usuário para padronizar `status` como enum)
- [x] `app/Exceptions/TableHasOpenOrderException.php` (não previsto originalmente — usado para mapear RN-04 para HTTP 409)
- [x] `app/Repositories/Contracts/TableRepositoryInterface.php`
- [x] `app/Repositories/Eloquent/TableRepository.php`
- [x] `app/Services/TableService.php`
- [x] `app/Http/Requests/Table/StoreTableRequest.php`
- [x] `app/Http/Requests/Table/UpdateTableRequest.php`
- [x] `app/Http/Requests/Table/UpdateTableStatusRequest.php`
- [x] `app/Http/Controllers/Api/V1/TableController.php`
- [x] `app/Http/Resources/TableResource.php`
- [x] `database/factories/TableFactory.php`
- [x] `tests/Feature/Table/TableManagementTest.php`
- [x] `tests/Unit/Services/TableServiceTest.php`

### Modificados
- [x] `routes/api.php` (grupo `admin/tables`, `tenant.role:store_owner,store_manager`)
- [x] `app/Providers/AppServiceProvider.php` (binding `TableRepositoryInterface` → `TableRepository`)
- [x] `docs/DATABASE.md` (nova tabela `tables`)
- [x] `docs/planejamento-multitenancy.md` (marcado item 2 como `implementado`)

### Pendente (requer Docker)
- [ ] `php artisan migrate`
- [ ] `php artisan test`
- [ ] `./vendor/bin/pint`

---

## Perguntas em Aberto

1. **Reuso de `number` de mesa inativa:** se o lojista desativar a mesa "05" e depois criar uma
   nova mesa "05", devemos (a) bloquear até reativar a antiga, (b) reativar automaticamente a
   antiga, ou (c) permitir criar uma nova linha (índice único só considera `is_active = true`)?
   → Sugestão: opção (c), com índice único parcial `WHERE is_active = true`, mais simples e
   evita reaproveitar `qr_token`/histórico de uma mesa "fechada".
2. **Validação server-side do QR Code:** hoje o frontend só lê `?mesa=XX` da URL. Vale a pena,
   nesta spec, expor um endpoint público `GET /api/v1/tables/validate?number=XX&t={qr_token}`
   para o frontend confirmar que o QR é legítimo antes de entrar em "Modo Mesa"? Ou isso fica
   para uma spec futura quando o frontend de fato adotar `qr_token` na URL?
   → Sugestão: deixar de fora desta spec (o frontend nem gera URL com `t=` ainda); só preparamos
   o campo `qr_token` no schema para não precisar de migration extra depois.
3. **Auto-transição de status:** RN-05 do item 1 (`docs/specs/order-feedback-and-bi.md`) já marca
   `dispatched_at` quando um pedido de mesa é `Finalizado`. Devemos, nesta spec ou numa futura,
   também mudar `tables.status` para `limpeza` automaticamente nesse momento (via listener no
   `OrderService::updateStatus`)? → Sugestão: fora do escopo desta spec (manter status 100%
   manual por enquanto), registrar como melhoria futura no `planejamento-multitenancy.md`.

---

## Histórico

| Data | Status | Nota |
|------|--------|------|
| 2026-06-11 | draft | Criação inicial |
| 2026-06-11 | implemented | Implementação completa (código). `status` da mesa modelado como enum `App\Enums\TableStatus` (PHP 8.1+). Pendente: `migrate`/`test`/`pint` no Docker. |
