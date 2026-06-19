---
index:
  version: "1.0"
  doc: roadmap
  status: aprovado
  cenario: LEGADO
  generated: "2026-06-19"
  sprints:
    - N1
    - N2
    - N3
    - N4
    - N5
    - N6
    - F1
---

# Roadmap Técnico — work-nc-whmcs

> Gerado em: 2026-06-18  
> Fase: Planejamento técnico (melhorias LEGADO v3.1.7)  
> Baseado em: REQUIREMENTS.md + ARCHITECTURE.md + DATABASE.md + integration-contracts.yaml  
> Status: Onda 2 concluída — backlog de execução vazio

---

## Resumo

| Métrica | Valor |
|---------|-------|
| Total de tarefas | 27 |
| Total de sprints | 7 |
| Tarefas P | 9 |
| Tarefas M | 18 |
| Tarefas G | 0 |
| Sprints concluídas | 7 (N1–N6, F1) |
| Sprints pendentes | 0 |

---

## Índice de Sprints

| Sprint | Categoria | Gate (resumo) | Status | Tasks | Módulos | Resumo | Linhas |
|--------|-----------|---------------|--------|-------|---------|--------|--------|
| N1 | N | PHPUnit verde com ≥15 casos Helper | concluída | 3 | tests, Helper | Expandir testes Helper | 40-95 |
| N2 | N | USAGE/README alinhados v3.1.7 | concluída | 3 | docs | Sync documentação operacional | 98-130 |
| N3 | N | PHPUnit ≥25 testes (Helper config + SSHManager stub) | concluída | 5 | tests, SSHManager | Testes SSHManager instanceExists | 132-175 |
| N4 | N | PHPUnit ≥32 testes (NextcloudAPI OCS stub) | concluída | 5 | tests, NextcloudAPI | Testes OCS createUser/getUser/password | 177-220 |
| N5 | N | PHPUnit ≥42 testes (Helper DB path + DNS) | concluída | 4 | tests, Helper | getDomain via Capsule + checkDnsRecords | 222-275 |
| N6 | N | PHPUnit ≥48 testes (OCS lifecycle) | concluída | 4 | tests, NextcloudAPI | disable/enable/delete + grupos | 277-325 |
| F1 | F | `security-review` CI verde ou skip documentado | concluída | 3 | ci | Corrigir workflow Merlin | 327-365 |

---

## Estratégia de Auditoria

> Modo: Manual

| Sprint | Review | Motivo |
|--------|--------|--------|
| N1 | skip | Testes + contrato Helper |
| N2 | skip | Docs only |
| N3 | skip | Testes com stub SSH |
| N4 | skip | Testes com stub OCS |
| N5 | skip | Testes Helper DB/DNS |
| N6 | skip | Testes OCS lifecycle |
| F1 | skip | CI workflow only |

---

## Grafo de Dependências

```
[Helper tests N1] ──► [Docs N2] ──► [SSHManager N3] ──► [NextcloudAPI N4]
                                                              │
                    ┌─────────────────────────────────────────┘
                    ▼
            [Helper DB/DNS N5] ──► [OCS lifecycle N6]
                    │
                    └──► [CI security-review F1]  (paralelo após N4)
```

---

## Sprint N1 — PHPUnit Helper expansion

> Categoria: N  
> Gate: `cd tests && ./vendor/bin/phpunit` passa com ≥15 testes; cobre getDomain, isValidDomain, parseCredentials, parseManageOutput, getContainerNames  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N1.1 — Testes isValidDomain + getDomain (params path) | `php-whmcs-module` | — |
| [x] | M | N1.2 — Testes parseCredentials + parseManageOutput | `php-whmcs-module` | — |
| [x] | P | N1.3 — Testes getContainerNames + formatQuota | `php-whmcs-module` | — |

**Notas técnicas N1.1:**

<details>
<summary>N1.1 — getDomain e isValidDomain</summary>

- **Arquivo(s)**: `tests/src/HelperTest.php`
- **Abordagem**: Testar `isValidDomain` com válidos/inválidos; `getDomain` com `$params['domain']` e `$params['customfields']` sem DB
- **Edge cases**: domínio com https://, www., trailing slash; custom field com acento
- **executor_prompt**: Add PHPUnit tests for Helper::isValidDomain and Helper::getDomain using $params array only (no Capsule). Cover domain cleaning and customfields fallback keys containing dom+inst.
</details>

**Notas técnicas N1.2:**

<details>
<summary>N1.2 — parseCredentials e parseManageOutput</summary>

- **Arquivo(s)**: `tests/src/HelperTest.php`
- **Abordagem**: Fixture string mínima estilo manage.sh .credentials v11.x (seção Nextcloud only)
- **executor_prompt**: Add tests for parseCredentials with sample .credentials content (Nextcloud section URL/User/Password) and parseManageOutput with KEY=VALUE lines. Assert keys nextcloud_url, nextcloud_user, nextcloud_pass populated.
</details>

---

## Sprint N2 — Documentação operacional sync

> Categoria: N  
> Gate: USAGE.md referencia v3.1.7 e arquitetura 3+8 containers; README §3 sem menção a 10 containers  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | N2.1 — Atualizar USAGE.md versão e arquitetura | `/dev doc` | N1 |
| [x] | P | N2.2 — Corrigir README §3 (10→3 containers) | `/dev doc` | — |
| [x] | P | N2.3 — Atualizar manifest docs hub + CHANGELOG entrada Beesy | `/dev doc` | N2.1 |

---

## Sprint N3 — PHPUnit SSHManager + Helper config

> Categoria: N  
> Gate: `cd tests && ./vendor/bin/phpunit` passa com ≥25 testes; SSHManager `instanceExists` coberto com stub  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | N3.1 — Testes formatQuotaForNextcloud + getServerConfig/getProductConfig | `php-whmcs-module` | N1 |
| [x] | M | N3.2 — TestableSSHManager stub + bootstrap SSHManager | `php-whmcs-module` | — |
| [x] | M | N3.3 — Testes porta SSH fallback (0→22) | `php-whmcs-module` | N3.2 |
| [x] | M | N3.4 — Testes instanceExists (exists/partial/missing) | `php-whmcs-module` | N3.2 |
| [x] | P | N3.5 — Atualizar ROADMAP + CHANGELOG | `/dev doc` | N3.4 |

---

## Sprint N4 — PHPUnit NextcloudAPI OCS stub

> Categoria: N  
> Gate: `cd tests && ./vendor/bin/phpunit` passa com ≥32 testes; `testConnection`, `createUser`, `getUser`, `changeUserPassword`, `getUserStorageInfo` cobertos com stub HTTP  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N4.1 — `request` protected + TestableNextcloudAPI + bootstrap | `php-whmcs-module` | N3 |
| [x] | M | N4.2 — Testes `testConnection` (success + OCS fail + cURL error) | `php-whmcs-module` | N4.1 |
| [x] | M | N4.3 — Testes `createUser` + `getUser` (success + OCS error) | `php-whmcs-module` | N4.1 |
| [x] | M | N4.4 — Testes `changeUserPassword`, `setUserQuota`, `getUserStorageInfo` | `php-whmcs-module` | N4.1 |
| [x] | P | N4.5 — Atualizar ROADMAP status + CHANGELOG | `/dev doc` | N4.4 |

**Notas técnicas N4.1:**

<details>
<summary>N4.1 — Seam de teste para OCS</summary>

- **Arquivo(s)**: `modules/servers/nextcloudsaas/lib/NextcloudAPI.php`, `tests/src/Stub/TestableNextcloudAPI.php`, `tests/bootstrap.php`
- **Abordagem**: Alterar `request()` de `private` para `protected` (sem mudança de comportamento); stub injeta JSON OCS sem cURL real
- **Edge cases**: resposta JSON inválida, statuscode ≠ 100, exceção de rede simulada
- **executor_prompt**: Add TestableNextcloudAPI extending NextcloudAPI overriding protected request(). Load NextcloudAPI in bootstrap. Mirror TestableSSHManager pattern from N3.
</details>

**Notas técnicas N4.2:**

<details>
<summary>N4.2 — testConnection</summary>

- **Arquivo(s)**: `tests/src/NextcloudAPITest.php`
- **Abordagem**: Mock capabilities response com `version.string`; assert success message contains version; OCS 997 → success false
- **executor_prompt**: Test testConnection() with stubbed OCS capabilities JSON (statuscode 100) and failure paths.
</details>

---

## Sprint N5 — Helper DB path + DNS contract

> Categoria: N  
> Gate: `cd tests && ./vendor/bin/phpunit` passa com ≥42 testes; `getDomain` caminho Capsule (serviceid/pid) e `checkDnsRecords` (ok + falha DNS) cobertos  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N5.1 — `MockCapsule` configurável + `NullQuery::value()` | `php-whmcs-module` | N4 |
| [x] | M | N5.2 — Testes `getDomain` via tblcustomfields (DB fallback) | `php-whmcs-module` | N5.1 |
| [x] | M | N5.3 — Testes `getRequiredDomains` + `checkDnsRecords` (hostname inválido → `all_ok` false) | `php-whmcs-module` | — |
| [x] | P | N5.4 — Atualizar ROADMAP status + CHANGELOG | `/dev doc` | N5.3 |

**Notas técnicas N5.1:**

<details>
<summary>N5.1 — Mock Capsule para WHMCS</summary>

- **Arquivo(s)**: `tests/src/Stub/MockCapsule.php`, `tests/src/Stub/NullQuery.php`, `tests/bootstrap.php`
- **Abordagem**: Substituir eval estático por factory que aceita fixtures por tabela; `get()` retorna rows com `id`/`fieldname`; `value()` retorna string do custom field
- **Edge cases**: fieldname sem match dom+inst; relid errado; exceção Capsule → retorna ''
- **executor_prompt**: Create MockCapsule test double for WHMCS\Database\Capsule with configurable table fixtures. Extend NullQuery with value() method. Wire bootstrap to use MockCapsule when tests call MockCapsule::seed(). Do not change Helper.php production logic.
</details>

**Notas técnicas N5.2:**

<details>
<summary>N5.2 — getDomain DB path</summary>

- **Arquivo(s)**: `tests/src/HelperTest.php`
- **Abordagem**: Seed tblcustomfields + tblcustomfieldsvalues; chamar `getDomain(['serviceid'=>1,'pid'=>10])` sem domain/customfields no params
- **executor_prompt**: Add PHPUnit tests for Helper::getDomain DB fallback path using MockCapsule fixtures. Cover fieldname with accents, fuzzy dom+inst match, and empty when no matching field.
</details>

---

## Sprint N6 — NextcloudAPI OCS lifecycle

> Categoria: N  
> Gate: `cd tests && ./vendor/bin/phpunit` passa com ≥48 testes; `disableUser`, `enableUser`, `deleteUser`, `addUserToGroup` cobertos com stub  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N6.1 — Testes `disableUser` + `enableUser` (success + OCS fail) | `php-whmcs-module` | N4 |
| [x] | M | N6.2 — Testes `deleteUser` + `createGroup` | `php-whmcs-module` | N4 |
| [x] | M | N6.3 — Testes `addUserToGroup` + `listUsers` com search/limit | `php-whmcs-module` | N4 |
| [x] | P | N6.4 — Atualizar ROADMAP status + CHANGELOG | `/dev doc` | N6.3 |

**Notas técnicas N6.1:**

<details>
<summary>N6.1 — disable/enable user</summary>

- **Arquivo(s)**: `tests/src/NextcloudAPITest.php`
- **Abordagem**: Reutilizar `TestableNextcloudAPI`; assert endpoints PUT `/disable` e `/enable` via mensagens de sucesso
- **executor_prompt**: Add NextcloudAPITest cases for disableUser() and enableUser() success and OCS failure (statuscode != 100). Use existing ocsSuccess/ocsFailure helpers.
</details>

---

## Sprint F1 — CI security-review fix

> Categoria: F  
> Gate: workflow `beesy-pr-security-review.yml` passa em PR de teste OU falha graciosamente com `continue-on-error` quando `ANTHROPIC_API_KEY` ausente; step Register CI Issues não quebra por `git: not found`  
> review: skip  
> Status: concluída

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | F1.1 — Corrigir comando Merlin (`merlin review` sem `--focus` inválido) | `ci-automations` | — |
| [x] | M | F1.2 — Skip condicional se secret ausente + remover push git do container | `ci-automations` | F1.1 |
| [x] | P | F1.3 — Documentar em README §CI + CHANGELOG | `/dev doc` | F1.2 |

**Notas técnicas F1.1:**

<details>
<summary>F1.1 — Merlin CLI compat</summary>

- **Arquivo(s)**: `.github/workflows/beesy-pr-security-review.yml`
- **Abordagem**: Remover `--focus security` (CLI Merlin atual não suporta); validar com `merlin review --help` na imagem do container
- **executor_prompt**: Fix beesy-pr-security-review.yml: replace `merlin review --focus security` with supported Merlin invocation per container image help output. Add if guard to skip review when ANTHROPIC_API_KEY is empty.
</details>

---

## Histórico

| Data | Alteração |
|------|-----------|
| 2026-06-18 | Roadmap inicial — sprints N1 (testes) e N2 (docs) via Rock campanha setup-beesy |
| 2026-06-18 | Sprint N3 — SSHManager stub tests + Helper config/quota (26 testes) |
| 2026-06-18 | Sprint N4 planejada — NextcloudAPI OCS stub tests (gate ≥32) |
| 2026-06-18 | Sprint N4 — NextcloudAPI OCS stub tests (38 testes) |
| 2026-06-19 | Onda 2 planejada — sprints N5 (Helper DB/DNS), N6 (OCS lifecycle), F1 (CI security-review) |
| 2026-06-19 | Onda 2 concluída — N5 (42 testes), N6 (50 testes), F1 (CI Merlin skip) |
