# Spec-Driven Development — Top Burguer Backend

> **Princípio:** nenhuma feature começa com código. Começa com uma spec.

---

## Por que Spec-Driven?

Sem spec, a IA (e o dev) constrói o que *acha* que foi pedido. Com spec, você e a IA concordam no contrato antes de escrever uma linha de código. Isso elimina:
- Refactoring de lógica que nunca foi discutida
- APIs que o frontend vai precisar quebrar depois
- Testes que cobrem o que foi implementado, não o que era esperado

---

## O fluxo

```
1. SPEC (draft)
   └─ Descrever o que a feature faz, o contrato da API, as regras e os edge cases
   
2. REVIEW (approved)
   └─ Revisar a spec com o time (ou com a IA: /review-code da spec)
   └─ Responder as "Perguntas em Aberto"
   └─ Mudar Status: draft → approved
   
3. IMPLEMENT
   └─ Usar /new-feature apontando para a spec
   └─ A IA implementa na ordem: Migration → Model → Repository → Service → Controller → Tests
   
4. VALIDATE
   └─ Todos os testes passando
   └─ Testar manualmente com curl/Insomnia os cenários da spec
   └─ Mudar Status: approved → implemented
```

---

## Criando uma spec

Use o slash command no Claude Code:
```
/new-spec listagem de pedidos por número de telefone do cliente
```

Ou copie o template manualmente:
```bash
cp docs/specs/_TEMPLATE.md docs/specs/{dominio}-{feature}.md
```

Convenção de nome: `{dominio}-{acao-kebab-case}.md`
```
order-listagem-por-telefone.md
product-upload-imagem.md
coupon-validacao-no-checkout.md
store-horario-automatico.md
```

---

## Implementando a partir de uma spec

```
/new-feature docs/specs/order-listagem-por-telefone.md
```

A IA vai:
1. Ler a spec completa
2. Verificar se o Status é `approved`
3. Implementar na ordem correta (Migration → Model → ... → Tests)
4. Rodar os testes
5. Marcar a spec como `implemented`

---

## Status de uma spec

| Status | Significado |
|--------|-------------|
| `draft` | Em elaboração — ainda não pode ser implementada |
| `approved` | Revisada e aprovada — pode ser implementada |
| `implemented` | Código feito, testes passando |
| `deprecated` | Feature removida ou substituída |

---

## Specs existentes

| Spec | Domínio | Status |
|------|---------|--------|
| [store-perfil-publico.md](specs/store-perfil-publico.md) | Store | implemented |

---

## Dicas de escrita de spec

**Seja específico no contrato.** Em vez de "retorna os dados do pedido", escreva o JSON exato de resposta. A IA implementará exatamente o que você escreveu.

**Edge cases são obrigatórios.** Cada edge case vira um teste. Se você não pensar neles na spec, a IA vai ignorá-los.

**Perguntas em aberto bloqueiam implementação.** Se tem dúvida, registre na seção "Perguntas em Aberto" e resolva antes de mover para `approved`.

**Uma spec por endpoint ou grupo coeso.** Não misture "criar produto" e "atualizar estoque" na mesma spec.
