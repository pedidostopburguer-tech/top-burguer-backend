# Spec: {Nome da Feature}

**Domínio:** {Order | Product | Coupon | Store | Auth}
**Status:** draft
**Branch:** feature/TB-XXX-{descricao-kebab-case}
**Criado em:** {YYYY-MM-DD}
**Autor:** {nome}

---

## Contexto e Motivação

> Por que essa feature existe? Qual dor do usuário/operador ela resolve?
> Quem a vai usar — cliente público, operador admin, ou ambos?

---

## Contrato da API

### Endpoint(s)

```
MÉTODO /api/v1/{rota}
```

**Headers obrigatórios:**
- `X-Store-Slug: {slug}` — identifica o tenant (todas as rotas)
- `Authorization: Bearer {token}` — apenas rotas admin (`auth:sanctum`)

**Parâmetros de rota (se aplicável):**
| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `{id}` | uuid/int | ... |

**Query params (se aplicável):**
| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `page` | int | não | Paginação |

**Request body (se aplicável):**
```json
{
  "campo_obrigatorio": "string — descrição e restrições",
  "campo_opcional": "number|null — descrição"
}
```

**Response — sucesso:**
```json
{
  "success": true,
  "message": "Mensagem amigável",
  "data": {
    "id": 1,
    "campo": "valor"
  }
}
```

**Responses de erro esperados:**
| Status | Quando |
|--------|--------|
| 401 | Token ausente ou inválido (rotas admin) |
| 403 | Sem permissão para este recurso |
| 404 | Loja não encontrada ou inativa |
| 422 | Dados de entrada inválidos |
| 409 | Conflito (ex: cupom já utilizado) |

---

## Regras de Negócio

- **RN-01:** {descrição clara da regra}
- **RN-02:** {descrição clara da regra}

---

## Edge Cases

- [ ] O que acontece quando o tenant não é identificado (sem header)?
- [ ] O que acontece quando a loja está inativa?
- [ ] O que acontece com inputs vazios ou nulos?
- [ ] Concorrência — dois requests simultâneos causam problema?
- [ ] {edge case específico desta feature}

---

## Testes Planejados

### Feature Tests (`tests/Feature/{Domain}/`)
```
it('retorna {dado} quando {condição}')
it('retorna 422 quando {campo obrigatório} está ausente')
it('retorna 404 quando a loja não existe')
it('retorna 401 quando não autenticado') // apenas rotas admin
it('não vaza dados de outro tenant')      // obrigatório para rotas tenant
```

### Unit Tests (`tests/Unit/Services/`)
```
it('{Service}: {comportamento específico}')
it('{Service}: lança exception quando {condição de erro}')
```

---

## Arquivos a Criar/Modificar

### Novos
- [ ] `database/migrations/{timestamp}_create_{table}_table.php`
- [ ] `app/Models/{Model}.php`
- [ ] `database/factories/{Model}Factory.php`
- [ ] `app/Repositories/Contracts/{Domain}RepositoryInterface.php`
- [ ] `app/Repositories/Eloquent/{Domain}Repository.php`
- [ ] `app/Services/{Domain}Service.php` (ou método novo em service existente)
- [ ] `app/Http/Requests/{Domain}/Store{Domain}Request.php`
- [ ] `app/Http/Controllers/Api/V1/{Domain}Controller.php`
- [ ] `app/Http/Resources/{Domain}Resource.php`
- [ ] `tests/Feature/{Domain}/{Nome}Test.php`
- [ ] `tests/Unit/Services/{Domain}ServiceTest.php`

### Modificados
- [ ] `routes/api.php` — adicionar rota
- [ ] `app/Providers/AppServiceProvider.php` — registrar novo Repository (se criado)

---

## Perguntas em Aberto

> Questões que precisam de resposta antes de implementar.
> Remover esta seção quando a spec estiver `approved`.

- ?

---

## Exemplo de uso (curl)

```bash
curl -X POST http://localhost:8000/api/v1/{rota} \
  -H "Content-Type: application/json" \
  -H "X-Store-Slug: minha-loja" \
  -d '{
    "campo": "valor"
  }'
```

---

## Histórico

| Data | Status | Nota |
|------|--------|------|
| {YYYY-MM-DD} | draft | Criação inicial |
