---
index:
  version: "1.0"
  doc: database
  status: aprovado
  cenario: LEGADO
  product_version: "3.1.7"
  generated: "2026-06-18"
  baseado_em:
    - docs/REQUIREMENTS.md
    - docs/ARCHITECTURE.md
---

# Database — work-nc-whmcs (Módulo Nextcloud SaaS WHMCS)

> Gerado em: 2026-06-18  
> Fase: 4 — Arquitetura de Dados  
> Cenário: LEGADO (sem migrations próprias — consumo do schema WHMCS + artefatos remotos)

---

## 1. Decisões

| Decisão | Escolha | Alternativa descartada | Motivo |
|---------|---------|------------------------|--------|
| BD principal (WHMCS) | **MariaDB / MySQL** (nativo WHMCS) | PostgreSQL, SQLite | WHMCS exige MySQL/MariaDB; módulo não controla engine |
| Tier de infra (WHMCS) | **Tier 1 — Single Node** | Tier 2 (replicas), Tier 3 (sharding) | Volume típico de hoster WHMCS; módulo não adiciona carga OLAP |
| Composição | **Nível 1 — Complementar externo** | Nível 2 CQRS, Nível 3 polyglot | Estado no WHMCS DB + filesystem SSH remoto; sem cache/fila própria no módulo |
| Schema do módulo | **Zero migrations** | Tabelas `mod_nextcloudsaas_*` | Padrão WHMCS server modules; reuso de `tblhosting`, custom fields, Module Log |
| BD Nextcloud (remoto) | **MariaDB `shared-db`** (manager v11.x) | BD por cliente | Fora do escopo do módulo; operado pelo Nextcloud SaaS Manager |

**Recomendação aplicada** (database-recommendation §1): *Projeto legado MySQL → manter MariaDB* + equipe PHP/WHMCS.

---

## 2. Modelo de Dados Lógico

O módulo não define entidades próprias em SQL. O modelo abaixo mapeia **conceitos de negócio** → **artefatos WHMCS / remotos**.

### 2.1 Entidades

| Entidade lógica | Persistência | Descrição | Relações principais |
|-----------------|-------------|-----------|---------------------|
| Serviço Nextcloud | `tblhosting` | Assinatura do cliente (domínio, status, credenciais) | `server` → `tblservers`; `orderid` → `tblorders` |
| Servidor de hospedagem | `tblservers` | Host SSH + IP para DNS e provisionamento | 1:N serviços (`tblhosting.server`) |
| Produto / plano | `tblproducts` + `configoption1..9` | Quota, max users, SSH key path, prefixo cliente, hostnames globais | 1:N serviços |
| Domínio da instância | Custom Field + `tblhosting.domain` | Hostname público do Nextcloud | Copiado por hooks checkout/AcceptOrder |
| Metadados DNS/cron | `tblhosting.notes` | Marcadores `[NextcloudSaaS]` para timeout e auto-provision | Texto append-only |
| Credenciais NC | `tblhosting.password` + Custom Fields | URL, client name, senha admin (criptografada WHMCS) | Preenchido no CreateAccount |
| Pedido | `tblorders` | Ciclo Pending → Active pós-provisionamento | Ligado via `tblhosting.orderid` |
| Cliente WHMCS | `tblclients` | E-mail para provisionamento e alertas DNS | 1:N serviços |
| Instância remota | Filesystem SSH | `.credentials`, `.env`, `docker-compose.yml`, volumes | 1:1 com serviço provisionado |
| Log técnico | Module Log WHMCS | Diagnóstico SSH/API (`Helper::log`) | Por `serviceid` implícito na mensagem |

### 2.2 Diagrama ER (lógico — WHMCS + remoto)

```
┌─────────────┐       ┌──────────────┐       ┌─────────────┐
│ tblclients  │ 1───N │  tblhosting  │ N───1 │ tblservers  │
└─────────────┘       └──────┬───────┘       └─────────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
      ┌──────────────┐ ┌───────────┐ ┌─────────────────────┐
      │ tblorders    │ │tblproducts│ │tblcustomfieldsvalues│
      │ (status)     │ │configopt* │ │ + tblcustomfields   │
      └──────────────┘ └───────────┘ └─────────────────────┘

                             │ SSH (provisionado)
                             ▼
              ┌──────────────────────────────────┐
              │ /opt/nextcloud-customers/<id>/   │
              │  .credentials  .env  compose     │
              └──────────────────────────────────┘
                             │
                             ▼
              ┌──────────────────────────────────┐
              │ shared-db (MariaDB) — manager    │  ← fora do WHMCS
              └──────────────────────────────────┘
```

### 2.3 Tabelas WHMCS — colunas utilizadas

#### `tblhosting`

| Coluna | Escrita | Leitura | Notas |
|--------|---------|---------|-------|
| `id` | — | ✓ | `serviceid` em todo o módulo |
| `userid` | — | ✓ | Cliente para e-mails |
| `orderid` | — | ✓ | `acceptOrderForService()` |
| `packageid` / `pid` | — | ✓ | Produto + custom fields |
| `server` | — | ✓ | FK → `tblservers` |
| `domain` | ✓ hooks, CreateAccount | ✓ | Fonte primária após sync do Custom Field |
| `domainstatus` | ✓ cron, CreateAccount | ✓ | `Pending`, `Active`, etc. |
| `username` | ✓ hooks | ✓ | Hardcoded `admin` para Nextcloud |
| `password` | ✓ CreateAccount, ChangePassword | ✓ | Senha admin NC (encrypted WHMCS) |
| `notes` | ✓ hooks cron | ✓ | Metadados `[NextcloudSaaS]` (ver §2.4) |

#### `tblservers`

| Coluna | Uso |
|--------|-----|
| `id` | Referenciado por `tblhosting.server` |
| `type` | Filtro `nextcloudsaas` em queries de IP |
| `ipaddress`, `hostname` | Validação DNS e SSH host |
| `username`, `password` | Credenciais SSH (password encrypted) |
| `accesshash` | Fallback para caminho chave SSH |
| `port` | Porta SSH (fallback 22 no módulo) |
| `disabled` | Servidores ativos apenas |

#### `tblcustomfields` / `tblcustomfieldsvalues`

| Custom Field (nome canônico) | Obrigatório | Populado por | Finalidade |
|------------------------------|-------------|--------------|------------|
| **Domínio da Instância** | Sim | Cliente checkout / admin | Input; copiado para `domain` |
| **Client Name** | Não | CreateAccount | Identificador `manage.sh` |
| **Nextcloud URL** | Não | CreateAccount | URL pública pós-provisionamento |
| **Collabora URL** | Não | Legado v2.x (opcional) | Pode existir no produto; UI cliente removida v3.1.3 |
| **Signaling URL** | Não | Legado v2.x (opcional) | Idem |

**Lookup robusto** (v3.1.7): `nextcloudsaas_findDomainCustomField($productId)` — cascata acentos → sem acentos → `LOWER(fieldname) LIKE '%dom%' AND '%inst%'`.

#### `tblproducts` — config options do módulo

| Config option | Campo | Default típico | Uso |
|---------------|-------|----------------|-----|
| 1 | Quota (GB) | 10 | ChangePackage, create |
| 2 | Máx. utilizadores | 5 | Informativo / manage.sh |
| 3 | Collabora | on | Sempre on na v11.x |
| 4 | Talk HPB | on | Sempre on na v11.x |
| 5 | Caminho chave SSH | vazio | `SSHManager` |
| 6 | Prefixo cliente | vazio | Nome no `manage.sh` |
| 7–9 | Hostnames globais | vazio | Override Collabora/Signaling/TURN |

### 2.4 Metadados em `tblhosting.notes`

Formato texto livre com marcadores append-only:

| Marcador | Quando gravado | Uso |
|----------|----------------|-----|
| `[NextcloudSaaS] dns_check_start=YYYY-MM-DD HH:MM:SS` | Checkout / início verificação DNS | Calcular timeout 3 dias |
| `[NextcloudSaaS] dns_expired=true` | DNS incorreto após 3 dias | Parar cron; notificar admin |
| `[NextcloudSaaS] auto_provisioned=YYYY-MM-DD HH:MM:SS` | Provisionamento via cron | Auditoria |

### 2.5 Dados remotos (host Nextcloud)

| Artefato | Caminho | Formato | Consumido por |
|----------|---------|---------|---------------|
| Diretório instância | `/opt/nextcloud-customers/<clientName>/` | FS | SSHManager |
| Credenciais parseadas | `.credentials` | INI/seções | `Helper::parseCredentials()` |
| Variáveis ambiente | `.env` | KEY=VAL | `getEnv()`, fallback credenciais |
| HaRP shared key | `docker-compose.yml` / container env | `HP_SHARED_KEY=` | `getHarpSharedKey()` |
| Uso disco | volumes/data | `du -sh` | ClientArea, UsageUpdate |

### 2.6 Convenções — escopo LEGADO WHMCS

As convenções Beesy (`database-conventions.md`: UUID PK, soft delete, timestamps) **não se aplicam** às tabelas core WHMCS — são schema proprietário WHMCS.

Para evoluções futuras **se** o projeto criar tabelas próprias:

- Prefixo sugerido: `mod_nextcloudsaas_`
- PK: `INT UNSIGNED AUTO_INCREMENT` (padrão WHMCS) ou UUID apenas se integração externa exigir
- Sempre `created_at` / `updated_at` em tabelas novas
- Nunca duplicar dados já em `tblhosting` ou custom fields

---

## 3. Infraestrutura de Dados

### 3.1 Topologia (Tier 1 — WHMCS)

```
┌─────────────────────────────────────┐
│  Servidor WHMCS                     │
│  ┌───────────────────────────────┐  │
│  │ MariaDB/MySQL (single node)   │  │
│  │  tblhosting, tblservers, …    │  │
│  └───────────────────────────────┘  │
│  PHP + módulo nextcloudsaas         │
└──────────────┬──────────────────────┘
               │ SSH
               ▼
┌─────────────────────────────────────┐
│  Host Nextcloud SaaS Manager        │
│  Docker + shared-db (MariaDB)       │
│  /opt/nextcloud-customers/          │
└─────────────────────────────────────┘
```

### 3.2 Connection pooling

| Camada | Mecanismo | Notas |
|--------|-----------|-------|
| WHMCS → MySQL | Pool interno WHMCS / Capsule | Módulo usa `Capsule::table()` — sem pool customizado |
| Módulo → remoto | N/A (SSH, não JDBC) | Uma conexão SSH por operação típica |
| Nextcloud app → DB | `shared-db` Docker network | Gerido pelo manager — fora do módulo |

### 3.3 Cache

| Cache | Existe? | Notas |
|-------|---------|-------|
| Redis no módulo WHMCS | Não | WHMCS pode usar Redis opcionalmente — transparente ao módulo |
| Redis Nextcloud (`shared-redis`) | Sim (remoto) | Cache de arquivos NC — não acessado pelo módulo WHMCS |
| Cache DNS | Não | `dns_get_record` a cada verificação cron |

### 3.4 Read/Write splitting

Não aplicável — Tier 1, workload WHMCS padrão. Replicação MariaDB é decisão de infra do provedor, não do módulo.

---

## 4. Segurança

| Item | Config / prática |
|------|------------------|
| Usuário BD WHMCS | Usuário dedicado WHMCS (instalação padrão) — módulo herda permissões |
| Senhas em repouso | WHMCS encrypt (`tblhosting.password`, `tblservers.password`); `localAPI('DecryptPassword')` só no envio de e-mail |
| TLS app-BD | Recomendado na instalação WHMCS (MySQL SSL) — configuração do hoster |
| Encryption at rest | Responsabilidade do provedor (volume WHMCS + backups) |
| PII | E-mail cliente (`tblclients`), domínio, credenciais NC — LGPD: responsabilidade do provedor |
| Backups WHMCS | mysqldump / backup WHMCS — **deve incluir custom fields e orders** |
| Backups Nextcloud | `manage.sh backup` antes de terminate; backups host — fora do módulo |
| PITR | Opcional no MariaDB do hoster — não requisito do módulo |
| Teste de restore | Procedimento operacional do provedor |
| Module Log | Não logar senhas em claro; `Helper::log()` para metadados técnicos |

---

## 5. Operações de Dados (sem migrations)

### 5.1 Fluxos de escrita

| Evento | Tabelas/campos afetados |
|--------|-------------------------|
| Checkout cliente | `tblcustomfieldsvalues`, `tblhosting.domain`, `tblhosting.notes` |
| Accept Order (admin) | Idem |
| CreateAccount sucesso | `tblhosting.password`, custom fields, `domainstatus`, `tblorders.status` |
| Cron DNS OK | `ModuleCreate` → mesmas + `notes` auto_provisioned |
| ChangePassword | `tblhosting.password`, `.credentials` remoto |
| DNS timeout 3d | `notes` dns_expired, e-mail admin |

### 5.2 APIs de acesso no código

| Padrão | Onde | Uso |
|--------|------|-----|
| `Capsule::table()` | Helper, hooks, nextcloudsaas.php | Queries diretas |
| `localAPI()` | hooks, nextcloudsaas.php | ModuleCreate, AcceptOrder, UpdateClientProduct, SendEmail |
| `Helper::getDomain()` | CreateAccount, validações | Leitura unificada domínio |

### 5.3 Sprint 1 — implicações

**Não há migration inicial** neste projeto LEGADO. Sprints de evolução devem:

1. Preservar compatibilidade com custom fields existentes em produção
2. Evitar novas colunas em tabelas WHMCS core
3. Se precisar de estado extra: preferir `notes` estruturado ou nova tabela `mod_*` via migration WHMCS addon (decisão futura no ROADMAP)

---

## 6. Queries de referência (diagnóstico)

```sql
-- Serviços Nextcloud pendentes (simplificado — espelha lógica do cron)
SELECT h.id, h.domain, h.domainstatus, h.notes, s.ipaddress
FROM tblhosting h
JOIN tblproducts p ON p.id = h.packageid
JOIN tblservers s ON s.id = h.server
WHERE p.servertype = 'nextcloudsaas'
  AND h.domainstatus = 'Pending';

-- Custom field domínio para um serviço
SELECT cf.fieldname, cfv.value
FROM tblcustomfields cf
JOIN tblcustomfieldsvalues cfv ON cfv.fieldid = cf.id
WHERE cf.type = 'product'
  AND cf.relid = :productId
  AND cfv.relid = :serviceId;
```

---

## Histórico de Revisões

| Data | Versão | Alteração | Autor |
|------|--------|-----------|-------|
| 2026-06-18 | 1.0 | Baseline LEGADO — WHMCS MariaDB + dados remotos SSH | Arquiteto de Dados (Beesy) |
