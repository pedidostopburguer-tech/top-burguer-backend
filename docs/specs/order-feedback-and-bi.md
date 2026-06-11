# Spec: Avaliação de Pedidos & Metadados de BI (Card 12 & Card 15)

**Domínio:** Order  
**Status:** approved  
**Branch:** feature/TB-017-order-feedback-and-bi  
**Criado em:** 2026-06-10  
**Autor:** João Pedro  

---

## Contexto e Motivação

Precisamos alinhar o banco de dados e a API do backend com o frontend do cardápio digital (doqui). Especificamente:
1. **Feedback do Consumidor (Card 12):** O cliente deve conseguir avaliar o pedido finalizado com uma nota (1 a 5) e comentário de texto.
2. **Métricas de BI / SLA (Card 15):** Precisamos registrar os timestamps de transição de status (`production_started_at` e `dispatched_at`) para calcular o tempo médio de preparo na cozinha.
3. **Mesa & Canal de Origem:** Precisamos registrar o canal do pedido (`delivery`, `mesa`, `balcao`) e o número da mesa (`table_number`), ao invés de buscar strings genéricas no endereço.

---

## Contrato da API

### `PATCH /api/v1/orders/{id}/feedback` (Público)

Insere ou atualiza a avaliação de um pedido finalizado pelo consumidor.

**Headers obrigatórios:**
- `X-Store-Slug: {slug}` — identifica o tenant

**Request body:**
```json
{
  "customer_phone": "11999999999",
  "rating": 5,
  "feedback_text": "Hambúrguer delicioso e entrega super rápida!"
}
```

**Response — sucesso (200):**
```json
{
  "success": true,
  "message": "Avaliação registrada com sucesso.",
  "data": {
    "id": 123,
    "rating": 5,
    "feedback_text": "Hambúrguer delicioso e entrega super rápida!"
  }
}
```

**Responses de erro esperados:**
| Status | Quando |
|--------|--------|
| 404 | Pedido não encontrado |
| 403 | Telefone informado não corresponde ao telefone do pedido |
| 422 | Pedido não está com status 'Finalizado' (bloqueia avaliar pedidos em produção/entrega) |
| 422 | Validação de rating falhou (deve ser entre 1 e 5) |

---

## Alterações no Schema (`orders` table)

Precisamos adicionar as seguintes colunas na tabela `orders`:
1. `rating` (integer, nullable, de 1 a 5)
2. `feedback_text` (text, nullable)
3. `production_started_at` (timestamp con timezone, nullable)
4. `dispatched_at` (timestamp con timezone, nullable)
5. `channel` (string/enum: `delivery`, `mesa`, `balcao`, default `delivery`)
6. `table_number` (string, nullable)

---

## Regras de Negócio

* **RN-01 (Transição de Status de Produção):** Quando o status do pedido é atualizado para `'Em produção'`, o timestamp `production_started_at` deve ser preenchido com a hora atual (`now`), se ainda não estiver preenchido.
* **RN-02 (Transição de Status de Despacho):** Quando o status do pedido é atualizado para `'Saiu para entrega'`, o timestamp `dispatched_at` deve ser preenchido com a hora atual (`now`), se ainda não estiver preenchido.
* **RN-03 (Transição de Status de Mesa/Servido):** Se o canal for `mesa`, e o status for atualizado para `'Finalizado'`, o timestamp `dispatched_at` deve ser preenchido com a hora atual (representando o momento em que a mesa foi servida), se ainda não estiver preenchido.
* **RN-04 (Segurança de Avaliação):** O endpoint público de feedback só aceita registrar a avaliação se o `customer_phone` enviado no body bater exatamente com o telefone cadastrado no pedido (somente números, ignorando formatações).
* **RN-05 (Estágio da Avaliação):** Um pedido só pode receber feedback se o seu status atual for `'Finalizado'`.

---

## Edge Cases

- [ ] Telefone com formatações diferentes (ex: `(11) 99999-9999` vs `11999999999`): o sistema deve normalizar limpando caracteres não-numéricos antes de comparar.
- [ ] Múltiplas avaliações: o consumidor pode alterar ou reenviar sua avaliação (sobrescreve o `rating` e `feedback_text` anteriores).
- [ ] Pedido já cancelado/recusado: não deve permitir receber avaliação (somente pedidos `'Finalizado'`).

---

## Testes Planejados

### Feature Tests (`tests/Feature/Order/FeedbackTest.php`)
```
it('registra avaliação do pedido com sucesso')
it('rejeita avaliação se o telefone do cliente não bater')
it('rejeita avaliação se o pedido não estiver finalizado')
it('permite sobrescrever a avaliação anterior')
it('não vaza dados de outro tenant')
```

### Unit Tests (`tests/Unit/Services/OrderServiceTest.php`)
```
it('updateStatus: define production_started_at ao mudar para Em produção')
it('updateStatus: define dispatched_at ao mudar para Saiu para entrega')
```

---

## Arquivos a Criar/Modificar

### Novos
- [ ] `database/migrations/{timestamp}_add_feedback_and_bi_columns_to_orders_table.php`
- [ ] `app/Http/Requests/Order/StoreOrderFeedbackRequest.php`
- [ ] `tests/Feature/Order/FeedbackTest.php`

### Modificados
- [ ] `app/Models/Order.php` (casts, fillable)
- [ ] `app/Services/OrderService.php` (inserir timestamps na transição de status)
- [ ] `app/Http/Controllers/Api/V1/OrderController.php` (novo endpoint)
- [ ] `app/Http/Resources/OrderResource.php` (incluir novas colunas de feedback, channel e table_number no retorno)
- [ ] `routes/api.php`
- [ ] `docs/DATABASE.md`

---

## Perguntas em Aberto

- Nenhuma pergunta em aberto para o alinhamento de banco.

---

## Exemplo de uso (curl)

```bash
curl -X PATCH http://localhost:8000/api/v1/orders/123/feedback \
  -H "Content-Type: application/json" \
  -H "X-Store-Slug: top-burguer" \
  -d '{
    "customer_phone": "11999999999",
    "rating": 5,
    "feedback_text": "Sensacional!"
  }'
```

---

## Histórico

| Data | Status | Nota |
|------|--------|------|
| 2026-06-10 | draft | Criação inicial |
