---
name: php-whmcs-module
description: >
  Patterns for the Nextcloud SaaS WHMCS server module (v3.1.7+).
  Use when: editing nextcloudsaas.php, hooks, Helper, SSHManager, Smarty templates, or PHPUnit tests.
  Don't use when: Nextcloud SaaS Manager host setup (manage.sh on server) — out of module scope.
---

# PHP WHMCS Module — nextcloudsaas

## Quick reference

| Task | Where |
|------|--------|
| Lifecycle (create/suspend/…) | `modules/servers/nextcloudsaas/nextcloudsaas.php` |
| Cron + DNS + emails | `includes/hooks/nextcloudsaas_hooks.php` |
| Domain resolution | `Helper::getDomain($params)` |
| Remote ops | `SSHManager::fromWhmcsParams($params)` |
| OCS password/quota | `NextcloudAPI` then occ fallback |
| Client UI | `templates/clientarea.tpl` |

## Add a new admin button

1. Implement `nextcloudsaas_myAction(array $params)` in `nextcloudsaas.php`
2. Register in `nextcloudsaas_AdminCustomButtonArray()`
3. Return HTML string or redirect; log via `Helper::log()`
4. Document in `USAGE.md`

## Add a PHPUnit test for Helper

1. Ensure `tests/bootstrap.php` stubs any WHMCS global needed
2. Add case to `tests/src/HelperTest.php` (or new `*Test.php` with `@covers`)
3. Run: `cd tests && composer install -q && ./vendor/bin/phpunit`

## Provision flow (read before changing CreateAccount)

1. `Helper::getDomain()` → validate
2. `checkDnsRecords()` or pending cron
3. `instanceExists()` fast-path vs `createInstance()`
4. Parse `.credentials`, `UpdateClientProduct`, custom fields
5. `nextcloudsaas_acceptOrderForService()`

## Docs
- `docs/ARCHITECTURE.md` — ADRs and module boundaries
- `docs/DATABASE.md` — WHMCS tables and custom fields
- `docs/integration-contracts.yaml` — manage.sh + OCS contracts
