# Spec: Autenticação JWT (Card 16 — SaaS Core)

**Domínio:** auth  
**Status:** aprovado  
**Criado em:** 2026-06-06

---

## Contexto

O frontend usa hoje autenticação via Stealth Admin + criptografia RC4 no localStorage (frágil, não escalável). O backend Laravel precisa expor fluxos completos de autenticação via Sanctum (JWT stateless) para substituí-la, suportando os 3 tiers de usuário:

- **Plataforma** — `super_admin`, `saas_support` (sem store_id)
- **Loja** — `store_owner`, `store_manager`, `kitchen_staff`, `delivery_driver` (com store_id)
- **Cliente** — anônimo (sem autenticação no MVP)

---

## Endpoints

### `POST /api/v1/auth/register`

Cria um `User` + `Profile` associado (store_owner por padrão).

**Request:**
```json
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha_segura_123",
  "password_confirmation": "senha_segura_123",
  "store_id": "uuid-da-loja"
}
```

**Response 201:**
```json
{
  "success": true,
  "message": "Conta criada com sucesso.",
  "data": {
    "user": { "id": 1, "name": "João Silva", "email": "joao@example.com" },
    "profile": { "id": "uuid", "role": "store_owner", "store_id": "uuid" },
    "token": "sanctum-token-abc123"
  }
}
```

**Erros:**
- `422` — email duplicado, senha fraca, store_id inválido
- `404` — store_id não encontrado

---

### `POST /api/v1/auth/login`

**Request:**
```json
{ "email": "joao@example.com", "password": "senha_segura_123" }
```

**Response 200:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "user": { "id": 1, "name": "João Silva", "email": "joao@example.com" },
    "profile": { "role": "store_owner", "store_id": "uuid", "is_active": true },
    "token": "sanctum-token-abc123"
  }
}
```

**Erros:**
- `401` — credenciais inválidas
- `403` — conta desativada (`profile.is_active = false`)

---

### `POST /api/v1/auth/logout`

Requer `Bearer token`. Revoga o token atual.

**Response 200:**
```json
{ "success": true, "message": "Logout realizado com sucesso.", "data": null }
```

---

### `GET /api/v1/auth/me`

Requer `Bearer token`. Retorna dados do usuário autenticado.

**Response 200:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "user": { "id": 1, "name": "João Silva", "email": "joao@example.com" },
    "profile": {
      "id": "uuid",
      "role": "store_owner",
      "store_id": "uuid",
      "is_active": true
    }
  }
}
```

---

### `POST /api/v1/auth/forgot-password`

Envia email com link de reset (válido por 1 hora).

**Request:**
```json
{ "email": "joao@example.com" }
```

**Response 200** (sempre, mesmo se email não existir — evita enumeração):
```json
{ "success": true, "message": "Se o e-mail existir, você receberá as instruções em breve.", "data": null }
```

---

### `POST /api/v1/auth/reset-password`

**Request:**
```json
{
  "token": "token-do-email",
  "email": "joao@example.com",
  "password": "nova_senha_123",
  "password_confirmation": "nova_senha_123"
}
```

**Response 200:**
```json
{ "success": true, "message": "Senha redefinida com sucesso.", "data": null }
```

**Erros:**
- `422` — token inválido ou expirado, senhas não conferem

---

## Regras de negócio

1. **Register** cria sempre com role `store_owner`. Criação de outros roles é feita via endpoint admin (fora do escopo desta spec).
2. **store_id** no register é obrigatório — no MVP todos os usuários são de loja.
3. Login retorna o profile junto para o frontend saber qual dashboard exibir.
4. **forgot-password** nunca revela se o email existe (proteção contra enumeração).
5. Token Sanctum não tem expiração fixa — revogado explicitamente no logout.
6. Usuário com `profile.is_active = false` não consegue logar.
7. Não expor `store_id` diretamente na resposta de `me` — está dentro de `profile`, que já é contexto interno. Aceitável aqui pois o usuário é dono dos dados.

---

## Segurança

- Senhas: mínimo 8 caracteres
- Rate limiting: `throttle:6,1` nas rotas de auth (6 tentativas por minuto)
- Reset token: usar o sistema nativo do Laravel (`password_reset_tokens`) — expiração 60min via `config/auth.php`
- Email SMTP via `MAIL_*` no `.env` — suporte a Mailtrap (dev) e qualquer SMTP produção

---

## Implementação esperada

```
Migration: 0001_01_01_000000_create_users_table.php ← já existe
FormRequests: RegisterRequest, LoginRequest, ForgotPasswordRequest, ResetPasswordRequest
Service: AuthService
Controller: AuthController (thin, delega ao Service)
Resource: UserResource, ProfileResource
Email: ResetPasswordMail (opcional — Laravel usa o padrão Notification)
Routes: api/v1/auth/* (sem middleware auth, exceto logout e me)
```

---

## Testes planejados

### Feature tests (AuthTest.php)

| Cenário | Método | Esperado |
|---------|--------|----------|
| Register com dados válidos | POST /auth/register | 201 + token |
| Register com email duplicado | POST /auth/register | 422 |
| Register com store_id inválido | POST /auth/register | 422 |
| Register com senha fraca (<8) | POST /auth/register | 422 |
| Login correto | POST /auth/login | 200 + token |
| Login com senha errada | POST /auth/login | 401 |
| Login com conta desativada | POST /auth/login | 403 |
| Logout autenticado | POST /auth/logout | 200 |
| Logout sem token | POST /auth/logout | 401 |
| Me autenticado | GET /auth/me | 200 + dados |
| Me sem token | GET /auth/me | 401 |
| Forgot-password email existente | POST /auth/forgot-password | 200 (genérico) |
| Forgot-password email inexistente | POST /auth/forgot-password | 200 (genérico) |
| Reset com token válido | POST /auth/reset-password | 200 |
| Reset com token expirado | POST /auth/reset-password | 422 |
