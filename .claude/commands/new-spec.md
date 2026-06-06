# Comando: /new-spec

Cria uma spec de feature seguindo o padrão Spec-Driven Development do projeto.

## Argumento
$ARGUMENTS — nome/descrição da feature (ex: "listagem de pedidos por cliente")

## O que fazer

1. Identificar o domínio (Order, Product, Coupon, Store, etc.)
2. Criar o arquivo `docs/specs/{dominio}-{feature-kebab-case}.md` usando o template abaixo
3. Preencher todas as seções com base no que o usuário descreveu
4. Listar explicitamente os edge cases e perguntas em aberto
5. **Não implementar nada** — a spec é revisada antes do código

## Template a preencher

```markdown
# Spec: {Nome da Feature}

**Domínio:** {Order | Product | Coupon | Store | Auth}
**Status:** draft | approved | implemented
**Branch:** feature/TB-XXX-{descricao}
**Criado em:** {data}

---

## Contexto e Motivação
Por que essa feature existe? Qual problema resolve?

## Contrato da API

### Endpoint
`MÉTODO /api/v1/{rota}`

**Headers obrigatórios:**
- `X-Store-Slug: {slug}` — identifica o tenant

**Request body (se aplicável):**
```json
{
  "campo": "tipo e descrição"
}
```

**Response — sucesso (2xx):**
```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

**Response — erros esperados:**
| Status | Situação |
|--------|----------|
| 404 | Loja não encontrada |
| 422 | Dados inválidos |
| 401 | Não autenticado (rotas admin) |

## Regras de Negócio
- RN-01: ...
- RN-02: ...

## Edge Cases
- [ ] O que acontece quando X está vazio?
- [ ] O que acontece quando o tenant não existe?
- [ ] Concorrência: dois requests simultâneos?

## Testes Planejados

### Feature Tests
- `it('retorna X quando Y')`
- `it('retorna 422 quando Z está ausente')`

### Unit Tests
- `it('calcula X corretamente')`

## Arquivos a Criar/Modificar
- [ ] `database/migrations/YYYY_MM_DD_create_{table}.php`
- [ ] `app/Models/{Model}.php`
- [ ] `app/Repositories/Contracts/{Domain}RepositoryInterface.php`
- [ ] `app/Repositories/Eloquent/{Domain}Repository.php`
- [ ] `app/Services/{Domain}Service.php`
- [ ] `app/Http/Requests/{Domain}/Store{Domain}Request.php`
- [ ] `app/Http/Controllers/Api/V1/{Domain}Controller.php`
- [ ] `app/Http/Resources/{Domain}Resource.php`
- [ ] `routes/api.php` (adicionar rota)
- [ ] `tests/Feature/{Domain}/...Test.php`
- [ ] `tests/Unit/Services/...ServiceTest.php`

## Perguntas em Aberto
- ?
```

## Após criar a spec
Informe o caminho do arquivo criado e pergunte ao usuário se há algo a ajustar antes de implementar.
