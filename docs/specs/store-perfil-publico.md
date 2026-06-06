# Spec: Perfil Público da Loja

**Domínio:** Store
**Status:** implemented
**Branch:** feature/TB-001-store-setup (já no main)
**Criado em:** 2026-06-06
**Autor:** João Pedro

---

## Contexto e Motivação

O cliente (consumidor final) precisa carregar as informações da loja ao abrir o cardápio digital — nome, logo, horário de funcionamento, taxa de entrega e se a loja está aceitando pedidos no momento. É a primeira chamada que o frontend faz ao iniciar.

**Usuário:** cliente público (não autenticado).

---

## Contrato da API

### Endpoint

```
GET /api/v1/store
```

**Headers obrigatórios:**
- `X-Store-Slug: {slug}` — identifica o tenant

**Response — sucesso (200):**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "name": "Top Burguer",
    "logo_url": "https://...",
    "description": "Os melhores burgers de Itapevi",
    "address": "Rua A, 100 — Itapevi/SP",
    "phone": "11999999999",
    "delivery_fee": "5.00",
    "min_order_value": "30.00",
    "estimated_delivery_minutes": 40,
    "is_open": true,
    "payment_methods": ["Pix", "Dinheiro", "Cartão"]
  }
}
```

**Responses de erro:**
| Status | Quando |
|--------|--------|
| 404 | Loja não encontrada pelo slug ou inativa |
| 404 | Sem header `X-Store-Slug` (sem tenant identificado) |

---

## Regras de Negócio

- **RN-01:** A loja só é retornada se `is_active = true` na tabela `stores`.
- **RN-02:** `is_open` considera tanto o flag manual (`is_open` em `store_statuses`) quanto o horário automático (quando `is_auto = true` em `store_statuses`, calcular pelo horário da semana em `store_settings`).
- **RN-03:** `store_id` nunca é exposto na resposta.

---

## Edge Cases

- [x] Sem header `X-Store-Slug` → 404 (middleware retorna false para `current_tenant_id`)
- [x] Slug de loja inativa → 404
- [x] Slug inexistente → 404
- [ ] Loja sem `store_settings` ainda (recém criada) → retornar defaults
- [ ] Loja sem `store_status` ainda → assumir fechada (`is_open: false`)

---

## Testes Planejados

### Feature Tests (`tests/Feature/Store/`)
```
it('retorna perfil da loja quando slug é válido')
it('retorna 404 quando loja não existe')
it('retorna 404 quando loja está inativa')
it('retorna is_open false quando loja não tem status cadastrado')
```

---

## Arquivos

### Existentes
- `app/Http/Controllers/Api/V1/StoreController.php`
- `app/Services/StoreService.php`
- `app/Repositories/Contracts/StoreRepositoryInterface.php`
- `app/Repositories/Eloquent/StoreRepository.php`
- `app/Http/Resources/StoreProfileResource.php`
- `routes/api.php`

---

## Exemplo de uso

```bash
# Sucesso
curl http://localhost:8000/api/v1/store \
  -H "X-Store-Slug: top-burguer"

# Sem tenant → 404
curl http://localhost:8000/api/v1/store
```

---

## Histórico

| Data | Status | Nota |
|------|--------|------|
| 2026-06-06 | implemented | Documentação retroativa da feature inicial |
