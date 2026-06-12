# Deploy e Infraestrutura — Doqui Backend

Documento de referência sobre o ambiente de produção: servidor, acessos, segurança,
backups, monitoramento e pendências. Mantenha atualizado a cada mudança de infra.

---

## 1. Servidor

| Item | Valor |
|------|-------|
| Provedor | Vultr Cloud Compute (região São Paulo) |
| Instância | `doqui-backend-01` |
| IP público | `216.238.124.209` |
| SO | Ubuntu 24.04 LTS |
| Stack | Docker Compose: `tb_app` (Laravel/PHP 8.4-fpm), `tb_nginx`, `tb_pgsql` (Postgres 16), `tb_redis` |
| Diretório do projeto | `/var/www/doqui-backend` |

Verificação rápida de saúde:
```bash
curl http://216.238.124.209/api/v1/store
curl http://216.238.124.209/api/v1/products
```

---

## 2. Acesso SSH

- Usuário de deploy: `doqui` (sudo + grupo `docker`)
- Login root via SSH: **desabilitado**
- Autenticação por senha: **desabilitada** (somente chave pública)
- Chave local: `C:\Users\joaop\.ssh\id_ed25519_doqui`
- Alias configurado em `~/.ssh/config` (Windows):

```
Host doqui-backend
    HostName 216.238.124.209
    User doqui
    IdentityFile C:\Users\joaop\.ssh\id_ed25519_doqui
```

Conectar: `ssh doqui-backend`

**Recuperação de emergência** (se SSH ficar inacessível): painel Vultr → instância →
"View Console" (noVNC) → login como `root` com a senha exibida na página da instância
(ícone de revelar senha).

---

## 3. Segurança — checklist de hardening

| Item | Status | Detalhe |
|------|--------|---------|
| SSH hardening | ✅ | `PermitRootLogin no`, `PasswordAuthentication no` em `/etc/ssh/sshd_config` **e** em `/etc/ssh/sshd_config.d/50-cloud-init.conf` (cloud-init sobrescreve o principal por `Include` no topo do arquivo) |
| fail2ban | ✅ | Jail `sshd` ativo, configuração default |
| Firewall (ufw) | ✅ | Portas 22, 80, 443 liberadas |
| Permissões `.env` | ✅ | `/var/www/doqui-backend/.env` → dono `linuxuser` (UID 1000, = `www` dentro do container), `chmod 600`. `doqui` foi adicionado ao grupo `linuxuser` e o arquivo está `640` para o `docker compose` (que roda como `doqui`) conseguir ler as variáveis para interpolação do `docker-compose.yml` |
| Backup automático Postgres | ✅ | Ver seção 4 |
| Monitoramento | ✅ | Ver seção 5 |
| Rate limiting Nginx | ✅ | Ver seção 6 |
| HTTPS / Let's Encrypt | ⏳ pendente | Depende do DNS do domínio (seção 7) |

---

## 4. Backup do banco

- Script: `/usr/local/bin/backup-doqui-db.sh`
- Destino: `/var/backups/doqui-postgres/top_burguer_YYYYMMDD_HHMMSS.sql.gz`
- Retenção: 7 dias (apaga backups mais antigos automaticamente)
- Agendamento (`crontab -e` do usuário `doqui`):
  ```
  0 3 * * * /usr/local/bin/backup-doqui-db.sh >> /var/log/doqui-backup.log 2>&1
  ```

Restaurar um backup:
```bash
cd /var/www/doqui-backend
gunzip -c /var/backups/doqui-postgres/top_burguer_YYYYMMDD_HHMMSS.sql.gz | docker compose exec -T pgsql psql -U top_burguer top_burguer
```

---

## 5. Monitoramento

- UptimeRobot, monitor HTTP em `http://216.238.124.209/api/v1/store`
- Alertas configurados por e-mail (conta UptimeRobot do João)
- **TODO**: quando o domínio estiver ativo, trocar a URL monitorada para
  `https://api.doqui.com.br/api/v1/store`

---

## 6. Rate limiting (Nginx)

Configurado em `docker/nginx/default.conf`:

- `/api/v1/auth/(login|register|password)`: zona `auth`, ~5 req/min por IP, burst 3
- Demais rotas: zona `general`, 10 req/s por IP, burst 20
- Excedente retorna `429 Too Many Requests`

Aplicar mudanças nesse arquivo:
```bash
cd /var/www/doqui-backend
docker compose restart nginx
```

---

## 7. Domínio e HTTPS — pendente

- Domínio **doqui.com.br** registrado no **Registro.br** (registrado em 2026-06-12, ainda
  sem DNS configurado para o backend)

### Próximos passos (quando formos configurar)

1. **DNS (Registro.br)**: criar registro `A` para `api.doqui.com.br` → `216.238.124.209`
2. **Frontend (Vercel)**: o domínio raiz/`www`/subdomínios de loja apontam para a Vercel
   (frontend ainda usa Supabase — ver seção 8, migração pendente)
3. **Certificado**: instalar `certbot` no servidor e gerar certificado Let's Encrypt para
   `api.doqui.com.br`, configurar Nginx para HTTPS + redirect HTTP→HTTPS
4. **Laravel `.env` produção**: atualizar `APP_URL=https://api.doqui.com.br` e
   `FRONTEND_URL=https://doqui.com.br` (usado em `config/cors.php` para CORS/Sanctum)
5. **UptimeRobot**: atualizar monitor para a URL HTTPS

---

## 8. Frontend (top-burguer) — migração pendente

⚠️ **Importante**: o frontend (`C:\Projects\top-burguer`, hospedado na Vercel) ainda
consome **Supabase**, não a API Laravel deste backend (`.env.example` do frontend só tem
variáveis `VITE_SUPABASE_*`).

"Plugar o front com o back" é uma migração grande: trocar toda a camada de acesso a dados
do frontend (Supabase client) para chamadas à API Laravel (`/api/v1/...`), incluindo:

- Autenticação (Supabase Auth → Sanctum)
- CRUD de produtos, mesas, pedidos, cupons etc.
- Resolução de tenant (`X-Store-Slug` ou subdomínio — ver `IdentifyTenant` middleware)
- Variáveis de ambiente do frontend (nova `VITE_API_URL` apontando para
  `https://api.doqui.com.br/api/v1`)

**Recomendação**: tratar como feature própria — escrever spec em
`docs/specs/frontend-api-migration.md` (ou similar) antes de implementar, dado o tamanho
e risco de regressão.
