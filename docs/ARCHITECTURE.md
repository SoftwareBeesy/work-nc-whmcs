---
index:
  version: "1.0"
  doc: architecture
  status: aprovada
  cenario: LEGADO
  product_version: "3.1.7"
  generated: "2026-06-18"
  baseado_em: docs/REQUIREMENTS.md
  sections:
    - visao-geral
    - stack
    - estrutura-pastas
    - diagrama-modulos
    - adrs
    - integracoes
    - seguranca
    - dados
    - observabilidade
    - decisoes-por-modulo
    - artefatos
---

# Arquitetura — work-nc-whmcs (Módulo Nextcloud SaaS WHMCS)

> Gerado em: 2026-06-18  
> Fase: 3 — Arquitetura de Solução  
> Status: Aprovada (baseline as-is v3.1.7)  
> Baseado em: `docs/REQUIREMENTS.md`

---

## 1. Visão Geral

**Tipo**: Módulo monolítico WHMCS (server module + hooks) com integração remota via SSH  
**Perfil Beesy**: `php-whmcs`  
**Justificativa**: O produto é um **plugin de provisionamento** para WHMCS — não é uma aplicação web autônoma. Toda a UI vive no WHMCS (admin + client area); a lógica de negócio executa no contexto PHP do WHMCS e delega operações de infraestrutura ao `manage.sh` no host Nextcloud via SSH. Microserviços ou frameworks full-stack (Laravel/NestJS) seriam over-engineering e incompatíveis com o modelo de extensão WHMCS.

**Cenário LEGADO**: documentação descreve o sistema **como está em produção** (v3.1.7), servindo de baseline para manutenção e sprints futuras — não propõe reescrita.

### 1.1 Topologia de runtime

```
[Cliente / Admin] ──HTTPS──► [WHMCS + PHP]
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
              [Hooks cron]   [Module API]   [Smarty tpl]
                    │             │             │
                    └─────────────┼─────────────┘
                                  │ SSH (phpseclib3)
                                  ▼
                    [Host Nextcloud SaaS Manager]
                    manage.sh + Docker + Traefik
                    ├── 3 containers/<cliente> (app, cron, harp)
                    └── 8 shared-* (db, redis, collabora, …)
```

---

## 2. Stack Tecnológica

| Camada | Tecnologia | Versão | Justificativa |
|--------|-----------|--------|---------------|
| Plataforma | WHMCS Server Module | — | Contrato nativo de provisionamento (`CreateAccount`, `Suspend`, etc.) |
| Linguagem | PHP | 7.4+ (compat WHMCS) | Requisito do ecossistema WHMCS; sem framework adicional |
| SSH | phpseclib3 | ~3.0 | PHP puro; não exige extensão `ssh2`; fallback documentado para ssh2/sshpass |
| HTTP (Nextcloud) | cURL | built-in | Cliente OCS API (`NextcloudAPI`) |
| Templates UI | Smarty (`.tpl`) | WHMCS | Padrão client area / error do WHMCS |
| Persistência | MySQL/MariaDB (WHMCS) | — | Tabelas nativas (`tblhosting`, custom fields, orders) — módulo não define schema próprio |
| Infra remota | Docker + manage.sh v11.x | v11.3+ | Orquestração real das instâncias Nextcloud |
| Proxy / SSL | Traefik + Let's Encrypt | — | No host Nextcloud (fora do módulo) |
| Testes | PHPUnit | 10.5 | Suite isolada em `tests/` (vendor separado do módulo) |
| CI/CD | GitHub Actions (Beesy) | — | `validation-gate` (lint + PHPUnit); release por tag `vX.Y.Z` |
| Observabilidade | WHMCS Module Log + Activity Log | — | `Helper::log()` + `logActivity()` nos hooks |

**Fora da stack do módulo** (host Nextcloud): MariaDB compartilhado (`shared-db`), Redis (`shared-redis`), Collabora, Talk HPB, TURN — operados pelo Nextcloud SaaS Manager.

---

## 3. Estrutura de Pastas

```
work-nc-whmcs/
├── modules/servers/nextcloudsaas/     # Módulo WHMCS (deploy na instalação)
│   ├── nextcloudsaas.php              # Entry point — lifecycle + admin/client commands
│   ├── hooks.php                      # Loader local (se aplicável)
│   ├── whmcs.json                     # Metadados + versão do módulo
│   ├── lib/
│   │   ├── Helper.php                 # Utilitários, DNS, parsing, constantes de arquitetura
│   │   ├── SSHManager.php             # SSH + manage.sh + occ + docker
│   │   └── NextcloudAPI.php           # OCS REST (users, quota, password)
│   ├── templates/
│   │   ├── clientarea.tpl             # Painel do assinante
│   │   └── error.tpl                  # Erros na client area
│   └── vendor/                        # phpseclib3 (commitado no repo)
├── includes/hooks/
│   └── nextcloudsaas_hooks.php        # Cron DNS, checkout, AcceptOrder, e-mails
├── tests/
│   ├── composer.json                  # PHPUnit isolado
│   ├── bootstrap.php
│   └── src/HelperTest.php             # Contrato público Helper (1 DNS, 3+8 containers)
├── docs/
│   ├── REQUIREMENTS.md
│   ├── ARCHITECTURE.md                # Este arquivo
│   └── sistema/                       # Hub de documentação
└── .github/workflows/                 # CI Beesy (validation-gate, security, etc.)
```

**Deploy**: copiar `modules/servers/nextcloudsaas/` e `includes/hooks/nextcloudsaas_hooks.php` para a instalação WHMCS do provedor.

---

## 4. Diagrama de Módulos

```
                    ┌──────────────────────────────────────┐
                    │           WHMCS Core                 │
                    │  (orders, billing, cron, templates)  │
                    └──────────────┬───────────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         │                         │                         │
         ▼                         ▼                         ▼
┌─────────────────┐    ┌──────────────────────┐    ┌─────────────────┐
│ hooks           │    │ nextcloudsaas.php    │    │ templates       │
│ (event-driven)  │    │ (module callbacks)   │    │ (Smarty UI)     │
└────────┬────────┘    └──────────┬───────────┘    └─────────────────┘
         │                        │
         │         ┌──────────────┼──────────────┐
         │         │              │              │
         │         ▼              ▼              ▼
         │    ┌─────────┐  ┌────────────┐  ┌──────────────┐
         └───►│ Helper  │  │ SSHManager │  │ NextcloudAPI │
              └─────────┘  └─────┬──────┘  └──────┬───────┘
                                 │ SSH             │ HTTPS OCS
                                 ▼                 ▼
                          ┌─────────────────────────────┐
                          │ Host: manage.sh + Docker    │
                          │ /opt/nextcloud-customers/   │
                          └─────────────────────────────┘
```

### 4.1 Assessment de Módulos

| Módulo | Complexidade | Risco | Flag | Depende de | Desbloqueia | Ordem |
|--------|-------------|-------|------|------------|-------------|-------|
| Helper | 1 — utilitários | 1 — interno | foundation | — | todos | 1 |
| SSHManager | 3 — SSH + manage.sh + docker | 3 — credenciais + remoto | complexo | Helper | lifecycle, admin cmds | 2 |
| NextcloudAPI | 2 — HTTP OCS | 2 — credenciais admin | — | Helper | ChangePassword | 2 |
| nextcloudsaas.php | 3 — orquestração WHMCS | 2 — billing side-effects | complexo | Helper, SSHManager, API | hooks, UI | 3 |
| hooks | 2 — eventos WHMCS | 2 — cron + orders | — | Helper, module | provisionamento auto | 3 |
| templates | 1 — apresentação | 1 — UX | — | nextcloudsaas.php | — | 4 |
| tests | 1 — contrato | 1 — regressão | foundation | Helper | CI | transversal |

**Sequência recomendada para evolução**: Helper (contratos estáveis) → SSHManager (integração crítica) → hooks (automação) → nextcloudsaas.php (orquestração) → templates (UX). Alterações em `SSHManager` exigem validação em host de staging com manage.sh real.

---

## 5. Decisões Técnicas (ADRs)

### ADR-001: PHP puro como módulo WHMCS servers (sem Laravel/Symfony)

- **Status**: Aprovada
- **Contexto**: WHMCS carrega módulos de servidor como funções globais `nextcloudsaas_*` em um único arquivo PHP; não há bootstrap de framework.
- **Decisão**: Manter PHP procedural/OO leve com namespace `NextcloudSaaS` nas libs.
- **Alternativas consideradas**:
  1. Laravel package — descartada: incompatível com lifecycle WHMCS e deploy em `modules/servers/`.
  2. Microserviço sidecar HTTP — descartada: complexidade operacional; SSH direto já funciona.
  3. Status quo WHMCS module — **escolhida**.
- **Trade-offs aceitos**: Sem DI container, sem ORM; testabilidade limitada a unidades puras (Helper).
- **Condição de reversão**: Se WHMCS oficialmente suportar módulos PSR-4 com autoload de app completa.
- **Consequências**: Deploy simples (copiar pasta); testes focados em Helper e mocks de SSH.

### ADR-002: phpseclib3 como cliente SSH principal

- **Status**: Aprovada
- **Contexto**: Nem todo servidor WHMCS tem extensão `php-ssh2`; operações `manage.sh create` podem levar minutos.
- **Decisão**: phpseclib3 em `vendor/` do módulo; timeout default 300s; `sudo -n` para NOPASSWD.
- **Alternativas consideradas**:
  1. Extensão ssh2 nativa — descartada como única opção (disponibilidade inconsistente).
  2. Exec local `ssh`/`sshpass` — mantida como fallback secundário no SSHManager.
  3. phpseclib3 — **escolhida** como primary.
- **Trade-offs aceitos**: Vendor commitado aumenta tamanho do repo; atualizações manuais de segurança.
- **Condição de reversão**: Vulnerabilidade crítica sem patch rápido em phpseclib.
- **Consequências**: `composer.json` apenas em `modules/servers/nextcloudsaas/` para runtime.

### ADR-003: Delegação total de provisionamento ao manage.sh v11.x

- **Status**: Aprovada
- **Contexto**: Nextcloud SaaS Manager evoluiu para arquitetura compartilhada (3+8 containers); lógica Docker/Traefik não deve ser duplicada no WHMCS.
- **Decisão**: Módulo chama `manage.sh create|stop|start|backup|remove|update` via SSH; lê `.credentials` e `docker-compose.yml` para metadados.
- **Alternativas consideradas**:
  1. Docker API direta do WHMCS — descartada: duplicaria manage.sh e quebraria em upgrades do manager.
  2. API REST no host — descartada: não existe no produto atual.
  3. manage.sh CLI — **escolhida**.
- **Trade-offs aceitos**: Acoplamento à interface CLI do manager; breaking changes em manage.sh exigem release coordenada.
- **Condição de reversão**: Manager passar a expor API estável versionada.
- **Consequências**: Constantes `Helper::CONTAINER_SUFFIXES` e `SHARED_CONTAINERS` documentam contrato v11.x.

### ADR-004: Provisionamento assíncrono via cron WHMCS (DNS gate)

- **Status**: Aprovada
- **Contexto**: Checkout não deve bloquear minutos enquanto DNS do cliente propaga; cliente precisa configurar registro A.
- **Decisão**: `CreateAccount` falha gracefully se DNS incorreto; hook `AfterCronJob` revalida a cada ~5 min; timeout 3 dias com alerta ao admin.
- **Alternativas consideradas**:
  1. Provisionamento síncrono no checkout — descartada: UX ruim e timeouts HTTP.
  2. Fila externa (Redis/Rabbit) — descartada: infra extra desnecessária; cron WHMCS já existe.
  3. Cron + ModuleCreate — **escolhida**.
- **Trade-offs aceitos**: Latência até 5 min após DNS correto; dependência do cron WHMCS estar ativo.
- **Condição de reversão**: Volume de pedidos exigir fila dedicada com retry/backoff avançado.
- **Consequências**: Botão **Provisionar Agora** (v3.1.5) como escape hatch síncrono.

### ADR-005: Resolução de domínio em cascata (`Helper::getDomain`)

- **Status**: Aprovada
- **Contexto**: Pedidos criados pelo admin (`Add New Order`) não disparam `AfterShoppingCartCheckout`; Custom Field pode ter nomes com/sem acento.
- **Decisão**: Cascata: `$params['domain']` → custom fields → query DB; hook `AcceptOrder` espelha checkout.
- **Alternativas consideradas**:
  1. Apenas `tblhosting.domain` — descartada: campo vazio em pedidos admin.
  2. Custom Field único hardcoded — descartada: variações de encoding/nome.
  3. Cascata + `nextcloudsaas_findDomainCustomField()` — **escolhida** (v3.1.7).
- **Trade-offs aceitos**: Queries extras ao DB; convenção de nome do Custom Field ainda recomendada.
- **Condição de reversão**: WHMCS passar domínio sempre em `$params['domain']` em todos os fluxos.
- **Consequências**: Testes de integração futuros devem cobrir checkout vs admin order.

### ADR-006: Perfil Beesy `php-whmcs`

- **Status**: Aprovada (resolve dúvida #1 de REQUIREMENTS.md)
- **Contexto**: Projeto não é Laravel nem app Node; CI já detecta `php-whmcs` no validation-gate.
- **Decisão**: Registrar `perfil: php-whmcs` em `.cursorsession`; não criar skill Laravel/NestJS.
- **Alternativas consideradas**:
  1. `perfil: null` — descartada: perde detecção automática de stack no Beesy.
  2. Skill custom `php-whmcs-module` — adiada para `/arquiteto padroes` se necessário.
- **Consequências**: Sprints futuras usam validação PHP lint + PHPUnit, não Pint/PHPStan Laravel.

---

## 6. Integrações

| Serviço | Propósito | Abordagem | Fallback |
|---------|-----------|-----------|----------|
| WHMCS Core | Billing, orders, custom fields, e-mail | `localAPI`, Capsule DB, hooks | Module Log + mensagem admin |
| SSH / manage.sh v11.x | Lifecycle instância Nextcloud | phpseclib3 + `sudo -n` | Mensagens enriquecidas (`testConnection`) |
| Nextcloud OCS API | Change password, quotas, users | cURL Basic auth | `docker exec … occ` via SSHManager |
| DNS público | Validar registro A do cliente | `dns_get_record` / Helper | Cron revalida; timeout 3 dias |
| Docker (remoto) | Status containers, logs, HaRP key | SSH `docker ps`, `docker exec` | Leitura de `docker-compose.yml` |

### 6.1 Mapeamento WHMCS → manage.sh

| Callback WHMCS | SSH / manage.sh | Notas |
|----------------|-----------------|-------|
| `CreateAccount` | `create` (+ fast-path `instanceExists`) | Idempotente v3.1.5+ |
| `SuspendAccount` | `stop` | Containers dedicados apenas |
| `UnsuspendAccount` | `start` | |
| `TerminateAccount` | `backup` + `remove` | Backup obrigatório antes |
| `Renew` | status + start se parado | |
| `ChangePackage` | `occ` quota admin + default | |
| `ChangePassword` | OCS API → occ fallback | Atualiza `.credentials` |

### 6.2 Hooks WHMCS

| Hook | Responsabilidade |
|------|------------------|
| `ShoppingCartValidateCheckout` | Valida domínio no checkout |
| `AfterShoppingCartCheckout` | Copia domínio → `tblhosting.domain` |
| `AcceptOrder` | Mesmo para pedidos criados pelo admin |
| `AfterCronJob` | DNS pendente → `ModuleCreate` + `AcceptOrder` |
| `AfterModuleCreate/Suspend/…` | Activity log / side-effects pós-ação |
| `ClientAreaPageProductDetails` | Injeta dados na client area |
| `ClientAreaHeadOutput` | CSS/JS customizado |

---

## 7. Segurança

### 7.1 Classificação de Dados

| Dado | Sensibilidade | Módulo responsável | Proteção |
|------|--------------|-------------------|----------|
| Senha SSH do servidor WHMCS | Alta | SSHManager | Armazenada criptografada pelo WHMCS (server password) |
| Senha admin Nextcloud | Alta | nextcloudsaas.php, hooks | Custom fields + `tblhosting.password`; e-mail ao cliente |
| HaRP Shared Key (`HP_SHARED_KEY`) | Alta | SSHManager | **Somente painel admin** (v3.1.7); lida de compose/env |
| Domínio do cliente | Média | Helper, hooks | Validação regex; sem PII direto |
| Logs manage.sh | Média | SSHManager | Module Log WHMCS; não expor ao cliente |
| URLs shared-* (Collabora, TURN) | Baixa | Helper | Hostnames globais; não são secrets |

### 7.2 Fronteiras de Confiança

| Origem | Destino | Protocolo | Auth | Validação | Rate limit |
|--------|---------|-----------|------|-----------|------------|
| Internet | WHMCS | HTTPS | Sessão WHMCS | WHMCS core | WHMCS |
| WHMCS PHP | Host Nextcloud | SSH:22 | Password ou chave | Comandos allowlist (`manage.sh`) | Timeout 300s |
| WHMCS PHP | Nextcloud instância | HTTPS OCS | Basic (admin) | SSL verify (configurável) | cURL timeout 30s |
| WHMCS PHP | DNS resolver | UDP/TCP 53 | — | `isValidDomain` + A record check | Cron 5 min |
| Admin WHMCS | Module Commands | HTTPS | Admin ACL WHMCS | `nextcloudsaas_*` callbacks | — |

### 7.3 Top 3 Vetores de Ataque

1. **Compromisso credencial SSH no WHMCS** → Impacto: controle total do host Nextcloud → Mitigação: chave SSH dedicada com comando restrito; NOPASSWD mínimo; auditoria Module Log.
2. **Injeção em comandos SSH** → Impacto: RCE no host → Mitigação: `clientName` derivado de regras WHMCS; sem concatenação livre de input do cliente em shell.
3. **Exposição de credenciais na client area** → Impacto: vazamento HaRP/admin → Mitigação: v3.1.7 remove HaRP do cliente; templates sem URLs internas shared-*.

### 7.4 Requisitos de Segurança por Módulo

| Módulo | Auth requerido? | Rate limit? | Input validation crítico? | Dados sensíveis? |
|--------|----------------|-------------|--------------------------|------------------|
| hooks | WHMCS interno | Cron natural | Domínio (checkout) | E-mail com senha |
| nextcloudsaas.php | Admin/client WHMCS | — | `$params`, custom fields | Senhas, credenciais |
| SSHManager | Credencial servidor | Timeout | `clientName`, comandos | SSH key, output `.credentials` |
| NextcloudAPI | Admin NC | HTTP timeout | Endpoints OCS | Admin password |
| Helper | — | — | Domínio, DNS | Parsing `.credentials` |
| templates | Client session | — | Escape Smarty | Não exibir secrets internos |

---

## 8. Banco de Dados e Infraestrutura de Dados

> Gerado pelo Arquiteto de Dados (Fase 4) — detalhamento completo em `docs/DATABASE.md`.

### 8.1 BD Principal

- **Escolhido**: MariaDB / MySQL (banco nativo do WHMCS)
- **Justificativa**: WHMCS exige MySQL/MariaDB; módulo server padrão consome `tblhosting`, custom fields e `localAPI` sem schema próprio. Projeto LEGADO — manter engine existente (database-recommendation: *legado MySQL → manter*).

### 8.2 Tier de Infraestrutura

- **Tier**: 1 — Single Node
- **Justificativa**: Carga típica de hoster WHMCS (centenas–milhares de serviços); módulo adiciona writes esporádicos no provisionamento e leituras no cron DNS (~5 min). HA do banco WHMCS é decisão de infra do provedor, não do módulo.
- **Componentes**:

| Componente | Tecnologia | Config |
|------------|-----------|--------|
| BD Primary (WHMCS) | MariaDB 10.6+ / MySQL 8 | Single node; backup mysqldump/WHMCS |
| BD Nextcloud (remoto) | MariaDB `shared-db` | Operado pelo manager v11.x — fora do módulo |
| Connection Pool | Built-in WHMCS / Capsule | Sem pool customizado no módulo |
| Cache | — (módulo) | `shared-redis` apenas no host NC |
| Failover | Manual (Tier 1) | Replicação MariaDB opcional no hoster |

### 8.3 Composição

- **Nível**: 1 — Complementar externo
- **Bancos / stores complementares**:

| Store | Propósito | Padrão de sync |
|-------|-----------|----------------|
| Filesystem SSH (`/opt/nextcloud-customers/`) | Credenciais, compose, volumes | Escrito por `manage.sh`; lido via SSHManager |
| MariaDB `shared-db` (remoto) | Dados Nextcloud multi-tenant | Gerido pelo manager — módulo não acessa SQL direto |
| Module Log WHMCS | Diagnóstico técnico | Append via `Helper::log()` |

- **Justificativa**: Polyglot ou CQRS seria over-engineering; estado de negócio já cabe no WHMCS + artefatos remotos.

### 8.4 Segurança de Dados

- **Conexão app-BD**: TLS recomendado na instalação WHMCS (configuração do hoster)
- **Credenciais**: Senhas em `tblhosting` / `tblservers` criptografadas pelo WHMCS; SSH no server record
- **Encryption at rest**: Responsabilidade do provedor (volume WHMCS + host Nextcloud)
- **Backups**: Backup WHMCS (inclui custom fields + orders) + `manage.sh backup` antes de terminate

---

## 9. Observabilidade

| Canal | O quê | Onde |
|-------|-------|------|
| Module Log | Ações SSH, fallbacks HaRP, erros API | WHMCS → Utilities → Logs → Module Log |
| Activity Log | Orders aceitos, DNS timeout, provisionamento | WHMCS Activity Log |
| Admin UI | Ver Logs, Ver Logs Talk Recording, Verificar Estado | Botões customizados |
| CI | `php -l`, PHPUnit Helper | GitHub Actions `validation-gate` |
| Métricas formais | — | **Não implementado** — candidato ROADMAP (Sentry, etc.) |

**Tracing distribuído**: não aplicável (monólito WHMCS + SSH síncrono).

---

## 10. Decisões Técnicas por Módulo

### Helper (`lib/Helper.php`)

**Anti-patterns a evitar:**
- Hardcodar hostnames Collabora/TURN por cliente — arquitetura v11.x usa globais.
- Assumir 10 containers por instância — contrato atual é 3 (`CONTAINER_SUFFIXES`).

**Decisões de implementação:**
- Constantes `CONTAINER_SUFFIXES` e `SHARED_CONTAINERS` são contrato público testado em PHPUnit.
- `getDomain()` deve permanecer pura o suficiente para testes sem WHMCS bootstrapped.
- `parseCredentials()` tolerante a formatos v11.x (seção HaRP opcional).

**Edge cases conhecidos:**
- Custom Field sem acento → `nextcloudsaas_findDomainCustomField()` em hooks (v3.1.7).
- Domínio com `https://` ou trailing slash → normalização em `getDomain()`.

**Integrações críticas:**
- Consumido por SSHManager, nextcloudsaas.php e hooks.

---

### SSHManager (`lib/SSHManager.php`)

**Anti-patterns a evitar:**
- Executar `manage.sh create` sem verificar `instanceExists()` — causa duplicação ou erro opaco.
- Considerar `.env` sozinho como instância completa — v3.1.7 exige `.credentials` + container `app`.

**Decisões de implementação:**
- `fromWhmcsParams()` centraliza leitura de server/product config.
- `runManage()` encapsula `sudo -n` + caminho do script.
- `getHarpSharedKey()` cascata: grep compose → `docker exec printenv`.
- Porta SSH: fallback 22 se WHMCS enviar 0/vazio.

**Edge cases conhecidos:**
- Ubuntu 24.04 `PasswordAuthentication no` → mensagem guiada em `testConnection()`.
- Provisionamento parcial (dir sem `.credentials`) → flag `partial` em `instanceExists()`.

**Integrações críticas:**
- Único ponto de saída para host Nextcloud; mudanças aqui afetam todo lifecycle.

---

### NextcloudAPI (`lib/NextcloudAPI.php`)

**Anti-patterns a evitar:**
- Usar API OCS para operações que manage.sh/occ fazem melhor em batch — preferir SSH para quotas em ChangePackage.

**Decisões de implementação:**
- cURL com `OCS-APIRequest: true` e `format=json`.
- ChangePassword: API primeiro, `occ user:resetpassword` como fallback.

**Edge cases conhecidos:**
- SSL ainda não provisionado (DNS recém-configurado) → fallback SSH.

---

### nextcloudsaas.php (orquestrador)

**Anti-patterns a evitar:**
- Retornar erro fatal quando instância já existe — fast-path deve reutilizar credenciais (v3.1.5).
- Deixar Order em Pending após CreateAccount bem-sucedido — chamar `nextcloudsaas_acceptOrderForService()`.

**Decisões de implementação:**
- Funções globais `nextcloudsaas_*` seguem contrato WHMCS (não renomear).
- `AdminCustomButtonArray` / `ClientAreaCustomButtonArray` registram botões sem lógica pesada nos arrays.
- `CreateAccount` idempotente + logging extensivo.

**Edge cases conhecidos:**
- Produto FREE ($0) com autosetup desligado → AcceptOrder no fluxo síncrono.
- Falha de quota no fast-path → warning log, não abort (v3.1.5).

---

### hooks (`includes/hooks/nextcloudsaas_hooks.php`)

**Anti-patterns a evitar:**
- Duplicar lógica de domínio — usar `Helper::getDomain` e `nextcloudsaas_findDomainCustomField`.
- Enviar e-mail com URLs de infra global Collabora/TURN ao cliente — removido v3.1.3.

**Decisões de implementação:**
- `AfterCronJob` processa serviços pendentes com DNS válido.
- `nextcloudsaas_cronProcessPendingService()` isolada para testabilidade futura.
- E-mail HTML via funções builder dedicadas.

**Edge cases conhecidos:**
- DNS incorreto por 3 dias → `nextcloudsaas_notifyAdminDnsTimeout`.
- Múltiplos serviços pendentes no mesmo cron → processar sequencialmente (evitar thundering herd SSH).

---

### templates (`templates/*.tpl`)

**Anti-patterns a evitar:**
- Exibir HaRP Shared Key ao assinante (v3.1.7).
- Listar 10 containers — UI deve refletir 3 dedicados.

**Decisões de implementação:**
- Variáveis injetadas por `nextcloudsaas_ClientArea()` — lista explícita (sem vazar internals).
- `error.tpl` para falhas de conexão/API com mensagem amigável.

---

### tests (`tests/`)

**Anti-patterns a evitar:**
- Acoplar testes ao vendor do módulo — composer separado em `tests/`.
- Mockar SSH em testes unitários do Helper — Helper deve permanecer testável sem rede.

**Decisões de implementação:**
- HelperTest valida contrato arquitetural (1 DNS, 3 containers, 8 shared).
- Expansão futura: SSHManager com stub de SSH2 (ROADMAP).

---

## 11. Artefatos Gerados

- [x] `docs/ARCHITECTURE.md` (este arquivo — `/arquiteto planejar`)
- [x] Seção "Decisões Técnicas por Módulo" (alimenta `/pmo plan` → ROADMAP.md)
- [x] Perfil Beesy `php-whmcs` (ADR-006)
- [x] Secão 8 detalhada + `docs/DATABASE.md` (`/arquiteto dados`)
- [ ] `.cursor/rules/*.mdc` (`/arquiteto padroes`)
- [ ] `docs/ROADMAP.md` (`/pmo plan`)
- [ ] `.github/workflows` projeto-específico além dos robots Beesy (`/devops planejar`)

---

## Histórico de Revisões

| Data | Versão | Alteração | Autor |
|------|--------|-----------|-------|
| 2026-06-18 | 1.0 | Proposta inicial — baseline as-is v3.1.7 | Arquiteto de Soluções (Beesy) |
