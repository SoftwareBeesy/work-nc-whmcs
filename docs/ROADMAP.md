---
index:
  version: "1.0"
  doc: roadmap
  status: aprovado
  cenario: LEGADO
  generated: "2026-06-18"
  sprints:
    - N1
    - N2
    - N3
    - N4
---

# Roadmap Técnico — work-nc-whmcs

> Gerado em: 2026-06-18  
> Fase: Planejamento técnico (melhorias LEGADO v3.1.7)  
> Baseado em: REQUIREMENTS.md + ARCHITECTURE.md + DATABASE.md + integration-contracts.yaml  
> Status: Em execução

---

## Resumo

| Métrica | Valor |
|---------|-------|
| Total de tarefas | 16 |
| Total de sprints | 4 |
| Tarefas P | 6 |
| Tarefas M | 10 |
| Tarefas G | 0 |

---

## Índice de Sprints

| Sprint | Categoria | Gate (resumo) | Status | Tasks | Módulos | Resumo | Linhas |
|--------|-----------|---------------|--------|-------|---------|--------|--------|
| N1 | N | PHPUnit verde com ≥15 casos Helper | concluída | 3 | tests, Helper | Expandir testes Helper | 40-95 |
| N2 | N | USAGE/README alinhados v3.1.7 | concluída | 3 | docs | Sync documentação operacional | 98-130 |
| N3 | N | PHPUnit ≥25 testes (Helper config + SSHManager stub) | concluída | 5 | tests, SSHManager | Testes SSHManager instanceExists | 132-175 |
| N4 | N | PHPUnit ≥32 testes (NextcloudAPI OCS stub) | concluída | 5 | tests, NextcloudAPI | Testes OCS createUser/getUser/password | 177-220 |

---

## Estratégia de Auditoria

> Modo: Manual

| Sprint | Review | Motivo |
|--------|--------|--------|
| N1 | skip | Testes + contrato Helper |
| N2 | skip | Docs only |
| N3 | skip | Testes com stub SSH |
| N4 | skip | Testes com stub OCS |

---

## Grafo de Dependências

```
[Helper tests N1] ──► [Docs sync N2] ──► [SSHManager tests N3] ──► [NextcloudAPI tests N4]
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

## Histórico

| Data | Alteração |
|------|-----------|
| 2026-06-18 | Roadmap inicial — sprints N1 (testes) e N2 (docs) via Rock campanha setup-beesy |
| 2026-06-18 | Sprint N3 — SSHManager stub tests + Helper config/quota (26 testes) |
| 2026-06-18 | Sprint N4 planejada — NextcloudAPI OCS stub tests (gate ≥32) |
| 2026-06-18 | Sprint N4 — NextcloudAPI OCS stub tests (38 testes) |
