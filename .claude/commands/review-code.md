# Comando: /review-code

Faz code review de um arquivo ou PR contra os padrões do Top Burguer Backend.

## Argumento
$ARGUMENTS — arquivo(s) ou descrição do que revisar (ex: "app/Services/OrderService.php" ou "PR da feature de cupons")

## Checklist de revisão

### Arquitetura
- [ ] Controller está thin? (sem lógica de negócio)
- [ ] Toda lógica de negócio está no Service?
- [ ] Service não acessa `request()` diretamente?
- [ ] Repository só tem queries, sem regras de negócio?

### Multi-tenancy
- [ ] Model de dados de loja usa `use BelongsToTenant`?
- [ ] Não há filtragem manual de `store_id` em queries de Models com o trait?
- [ ] `store_id` não é exposto na resposta da API?

### Qualidade de código
- [ ] Sem `dd()`, `dump()`, `var_dump()`
- [ ] Operações multi-tabela usam `DB::transaction()`?
- [ ] Exceptions tipadas em vez de retornar `false`?
- [ ] `$fillable` definido no Model?
- [ ] Tipos de retorno declarados nas funções?
- [ ] Migration tem `down()`?

### API
- [ ] FormRequest criado para validação?
- [ ] Mensagens de erro em português?
- [ ] Resource criado para a resposta?
- [ ] Resposta segue envelope `{ success, message, data }`?

### Testes
- [ ] Feature test cobre o caminho feliz?
- [ ] Feature test cobre pelo menos um caminho de erro?
- [ ] Unit test do Service existe?
- [ ] Factory cobre todos os campos necessários?

## Formato do feedback

Para cada problema encontrado:
```
❌ [Arquivo:Linha] Problema — Explicação de por que fere o padrão
✅ Sugestão: código corrigido
```

Para cada coisa boa:
```
✅ [Arquivo] Bem feito — por quê está correto
```

Ao final, dar um resumo: **aprovado**, **aprovado com ajustes menores**, ou **necessita revisão** com a lista de bloqueadores.
