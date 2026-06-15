# Top Burguer Backend — Guia para IA

Multi-tenant SaaS de cardápio/delivery. **Cada loja é um tenant isolado por `store_id` em row-level.**
Stack: Laravel 12 · PHP 8.4 (Docker) · PostgreSQL 16 · Redis 7 · Sanctum

> Documentação completa: `DEVELOPMENT.md`
> Specs de features: `docs/specs/`
> Slash commands: `.claude/commands/`
> Infraestrutura/produção: `docs/DEPLOYMENT.md`

---

## Branching

```
feature/* → develop → main
```

`develop` é a branch de integração padrão (toda feature nasce dela e volta pra ela via PR).
`main` reflete sempre a última versão estável/produção. Nomenclatura completa de branches e
Conventional Commits: ver `DEVELOPMENT.md` seção "Git Flow".

---

## Regra de ouro: tudo roda dentro do container

```bash
docker compose exec app bash          # entrar no container
docker compose exec app php artisan X # rodar artisan direto
```

PHP do host (Windows) é irrelevante — o projeto usa PHP 8.4 dentro do Docker.

---

## Arquitetura em uma linha

```
Request → IdentifyTenant middleware → FormRequest → Controller (thin)
       → Service (regras de negócio) → Repository Interface
       → Eloquent Repository → Model (BelongsToTenant) → PostgreSQL
```

**Nunca colocar lógica de negócio no Controller ou no Model.**

---

## Multi-tenancy — as duas regras que nunca quebram

1. Todo Model de dados de loja usa `use BelongsToTenant` — aplica `WHERE store_id = ?` automaticamente.
2. Nunca filtrar `store_id` manualmente na query — o trait já faz isso.

```php
// ❌ nunca
Product::where('store_id', app('current_tenant_id'))->get();

// ✅ sempre
Product::all(); // BelongsToTenant aplica o escopo sozinho
```

O tenant é resolvido pelo header `X-Store-Slug` (dev) ou subdomínio (prod) no `IdentifyTenant` middleware.
`app('current_tenant_id')` retorna o ID da loja ou `false` quando não há tenant.

---

## Padrão de resposta da API

```json
{ "success": true,  "message": "OK", "data": { ... } }
{ "success": false, "message": "Erro", "errors": { ... } }
```

Use os helpers do Controller base: `$this->success()`, `$this->created()`, `$this->error()`.

---

## Enums — padrão do projeto

**Todo campo com conjunto fixo e conhecido de valores deve ser um PHP backed enum em `app/Enums/`.**
Não usar `const ARRAY = [...]` + `'in:a,b,c'` para valores fixos — isso é dívida técnica.

```php
// ❌ evitar
const TYPES = ['percentage', 'fixed', 'free_delivery'];
'discount_type' => 'required|in:percentage,fixed,free_delivery',

// ✅ padrão
enum CouponDiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';
}

// Model: protected function casts(): array { return ['discount_type' => CouponDiscountType::class]; }
// FormRequest/Controller: 'discount_type' => ['required', new Enum(CouponDiscountType::class)]
// Comparações: match ($coupon->discount_type) { CouponDiscountType::Percentage => ..., ... }
```

Backed enums (`: string`) serializam automaticamente para `->value` no JSON — não exige mudança em
Resources. Cast Eloquent aceita string plain na escrita (`create()`/`update()`) e converte para
instância de enum na leitura.

**Já modelados como enum:** `Table.status` (`TableStatus`), `Order.channel` (`OrderChannel`),
`Coupon.discount_type` (`CouponDiscountType`), `Product.stock_unit` (`ProductStockUnit`).

**Pendentes (specs futuras — ver `docs/planejamento-multitenancy.md` item 11):**
`Order.status` + `STATUS_FLOW` (`OrderStatus`), `Profile.role`/`PLATFORM_ROLES` (`UserRole` —
RBAC-sensível, mexe nos middlewares `role:`/`tenant.role:`).

Ao criar uma feature nova, qualquer campo com valores fixos (`status`, `type`, `channel`,
`unit`, `method` etc.) já nasce como enum — não como string solta + `const`/`in:`.

---

## Criando uma nova feature — fluxo obrigatório

```
1. Escrever spec em docs/specs/{dominio}-{feature}.md
2. Validar spec (contratos, edge cases, testes planejados)
3. Implementar na ordem: Migration → Model → Repository → Service → FormRequest → Controller → Resource → Route
4. Escrever testes (Feature test + Unit test do Service)
5. Rodar php artisan test — todos verdes antes de commitar
```

Use `/new-spec` para criar uma spec e `/new-feature` para implementar a partir dela.

---

## Comandos frequentes (dentro do container)

```bash
php artisan migrate
php artisan test
php artisan test --filter=NomeDoTeste
php artisan route:list --path=api/v1
php artisan config:clear && php artisan cache:clear
./vendor/bin/pint          # formata código
```

---

## Autenticação — Sanctum (armadilhas conhecidas)

**Configuração obrigatória — não alterar sem entender o motivo:**

```php
// config/auth.php
'defaults' => ['guard' => 'web', 'passwords' => 'users'],
// SEM guard 'api' — auth:sanctum gerencia o próprio guard
```

```php
// config/sanctum.php
'guard' => ['web'],
// NÃO usar ['api'] — causaria busca de coluna api_token inexistente
```

**Por que `defaults.guard = 'web'`?**
Se `defaults.guard = 'api'` com `driver = 'sanctum'`, o Sanctum Guard chama `$this->auth->user()` internamente → resolve o guard padrão → si mesmo → loop infinito / memory exhausted.

**Rotas protegidas:** usar `auth:sanctum` diretamente (não `auth` nem `auth:api`).

**Reset de senha:** backend é API-only, sem named route `password.reset`. O `AppServiceProvider` usa `ResetPassword::createUrlUsing()` para apontar ao frontend.

**`$request->validated()` omite `password_confirmation`** — campos `_confirmation` são removidos pelo Laravel após validação. Usar `$request->only([..., 'password_confirmation'])` quando o broker do Laravel precisar dele.

---

## RBAC — middlewares disponíveis

```php
// Verifica papel (qualquer tenant)
->middleware('role:store_owner,store_manager')

// Verifica papel E que o usuário pertence ao tenant atual
->middleware('tenant.role:store_owner')
```

Roles: `super_admin`, `saas_support` (plataforma, store_id null) · `store_owner`, `store_manager`, `kitchen_staff`, `delivery_driver` (loja, store_id obrigatório).

---

## Status atual do projeto (Card 16 completo)

| Área | Status |
|------|--------|
| Docker (PHP 8.4, Nginx, PostgreSQL, Redis) | ✅ |
| Schema PostgreSQL (11 tabelas, migrations ordenadas) | ✅ |
| Models + BelongsToTenant trait | ✅ |
| Factories para todos os Models | ✅ |
| Repository Pattern (4 domínios) | ✅ |
| Services de domínio (Product, Order, Coupon, Store) | ✅ |
| Auth JWT via Sanctum (register/login/logout/me/reset) | ✅ |
| RBAC middlewares (CheckRole, CheckTenantRole) | ✅ |
| Testes Feature: 19/19 verdes | ✅ |
| Spec-Driven Development (SDD) setup | ✅ |
| Deploy em produção (Vultr, Docker Compose) | ✅ |
| Hardening de segurança (SSH, fail2ban, backups, monitoramento, rate limiting) | ✅ |
| Domínio doqui.com.br registrado (DNS/HTTPS pendentes) | ⏳ |
| Frontend migrado de Supabase para a API Laravel | ⏳ |

> Detalhes de infra/produção e próximos passos: `docs/DEPLOYMENT.md`

---

## O que NÃO fazer

- `dd()`, `dump()` ou `var_dump()` em código commitado
- Lógica de negócio em Controller, Model ou FormRequest
- Acesso a `request()` dentro de Service
- Migration sem método `down()`
- Filtrar `store_id` manualmente em queries de Models com `BelongsToTenant`
- Expor `store_id` na resposta da API pública
