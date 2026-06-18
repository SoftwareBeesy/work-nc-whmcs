---
index:
  version: "1.0"
  doc: requirements
  status: aprovado
  cenario: LEGADO
  product_version: "3.1.7"
  generated: "2026-06-18"
  sections:
    - visao-geral
    - stakeholders
    - usuarios-personas
    - features-mvp
    - integracoes
    - requisitos-nao-funcionais
    - fora-de-escopo
    - premissas
    - duvidas-abertas
    - mapa-do-legado
---

# Requisitos — work-nc-whmcs (Módulo Nextcloud SaaS WHMCS)

> Gerado em: 2026-06-18  
> Status: Aprovado (baseline as-is v3.1.7)

---

## 1. Visão Geral

**Descrição**: Módulo de servidor WHMCS que provisiona e gere instâncias Nextcloud como produto SaaS, integrando-se ao Nextcloud SaaS Manager (`manage.sh` v11.x) via SSH.

**Problema que resolve**: Provedores que vendem Nextcloud via WHMCS precisam provisionar, suspender, reativar e operar instâncias sem intervenção manual por SSH em cada pedido.

**Para quem**: Hosters e provedores que utilizam WHMCS + Nextcloud SaaS Manager em arquitetura compartilhada (3 containers dedicados por cliente + 8 serviços globais `shared-*`).

**Objetivo de negócio**: Automatizar o ciclo de vida SaaS completo — do checkout com domínio customizado até credenciais entregues e operação contínua — com mínima intervenção do admin.

**Cenário**: LEGADO (produto funcional v3.1.7 documentado as-is; esta fase Beesy prepara artefatos para manutenção e evolução).

**Objetivo desta documentação**: Registrar o comportamento atual do produto e estabelecer baseline para `ARCHITECTURE.md`, `ROADMAP.md` e sprints futuras.

---

## 2. Stakeholders

| Nome / Papel | Tipo | Prioridade | O que importa para ele |
|---|---|---|---|
| Admin do provedor | Usuário + operador | Alta | Provisionar sem SSH manual; destravar Orders Pending; diagnosticar DNS/SSH |
| Cliente assinante | Usuário final | Alta | Contratar, configurar DNS, receber credenciais, acessar Nextcloud |
| Sistema WHMCS (cron/hooks) | Automatizador | Alta | Fechar ciclo Pending→Active; provisionar quando DNS estiver correto |
| Defensys / maintainer | Patrocinador técnico | Alta | Compatibilidade com manage.sh v11.x; releases e CI estáveis |

---

## 3. Usuários e Personas

| Persona | Contexto | Principal frustração (hoje) | Objetivo | Nível técnico |
|---|---|---|---|---|
| Ricardo, admin NOC | Opera WHMCS e servidores Nextcloud | Orders presos em Pending após DNS corrigido; SSH falhando silenciosamente | Provisionar e operar instâncias pelo painel WHMCS | Técnico |
| Marina, cliente PME | Contrata plano Nextcloud para a empresa | Não sabe configurar registro DNS A; não vê status do provisionamento | Apontar domínio, receber e-mail com acesso e usar Nextcloud | Semi-técnico |
| Cron WHMCS | Processo automatizado | Hooks inconsistentes entre checkout do cliente e pedido criado pelo admin | Executar `ModuleCreate` e `AcceptOrder` sem duplicar instâncias | Sistema |

---

## 4. Features (MVP = baseline v3.1.7)

Todas as features abaixo são **Must-have** — já implementadas e em produção.

### Feature 1: Provisionamento automático com verificação DNS

- **Persona**: Admin do provedor, Cliente assinante, Cron WHMCS
- **Prioridade**: Must-have

**Fluxo principal:**
1. Cliente informa domínio no Custom Field **Domínio da Instância** no checkout (ou admin preenche antes de Accept Order).
2. Hook copia domínio para `tblhosting.domain`.
3. `CreateAccount` verifica registro DNS A apontando para IP do servidor WHMCS.
4. Se DNS incorreto, serviço permanece pendente; cron `AfterCronJob` revalida a cada ~5 minutos.
5. Quando DNS estiver correto, cron executa `ModuleCreate`; instância é criada via `manage.sh create`.
6. Módulo lê `.credentials`, atualiza WHMCS, envia e-mail ao cliente e aceita Order (`Pending → Active`).

**Fluxo alternativo:**
- Admin clica **Provisionar Agora** para reexecutar `CreateAccount` idempotentemente sem esperar cron.

**Fluxo de exceção:**
- DNS incorreto após 3 dias: cron para de verificar e notifica admin.
- Instância já existe no servidor: fast-path reutiliza credenciais existentes (v3.1.5+).

**Regras de negócio:**
- Apenas **1 registro DNS A** por cliente (domínio principal).
- Collabora, Talk HPB e TURN usam hostnames globais — sem DNS por cliente.
- Username Nextcloud admin é sempre `admin` (hardcoded).

**Critérios de aceite:**
- [ ] Com DNS A correto, cron provisiona em até 5 minutos sem ação manual
- [ ] Order passa de Pending para Active após provisionamento bem-sucedido
- [ ] Cliente recebe e-mail com URL, usuário `admin` e senha
- [ ] Se instância já existe, módulo reutiliza `.credentials` sem erro fatal
- [ ] Botão **Provisionar Agora** destrava serviço pendente

---

### Feature 2: Ciclo de vida WHMCS padrão

- **Persona**: Admin do provedor, Sistema WHMCS
- **Prioridade**: Must-have

**Fluxo principal:**
1. WHMCS dispara ação conforme billing (pagamento, cancelamento, upgrade).
2. Módulo executa comando `manage.sh` correspondente via SSH.

**Ações mapeadas:**

| Ação WHMCS | Comando manage.sh | Comportamento |
|---|---|---|
| Suspend | `stop` | Para containers dedicados do cliente |
| Unsuspend | `start` | Reinicia containers dedicados |
| Terminate | `backup` + `remove` | Backup completo antes de remoção permanente |
| Renew | verifica + reinicia se necessário | Garante instância ativa |
| ChangePackage | `occ` quota admin + default | Altera quota de disco |
| ChangePassword | API OCS (fallback SSH occ) | Atualiza senha admin + `.credentials` |

**Critérios de aceite:**
- [ ] Suspend para containers do cliente; Unsuspend restaura operação
- [ ] Terminate executa backup antes de `remove`
- [ ] ChangePackage aplica quota ao admin e define default para novos usuários

---

### Feature 3: Integração SSH com manage.sh

- **Persona**: Admin do provedor (indiretamente)
- **Prioridade**: Must-have

**Fluxo principal:**
1. Módulo obtém credenciais do servidor WHMCS (password ou chave SSH em config option).
2. `SSHManager` conecta via phpseclib3 com fallback porta 22.
3. Comandos executados com `sudo -n` (NOPASSWD).

**Fluxo de exceção:**
- Falha de conexão: mensagens enriquecidas (auth, timeout, PasswordAuthentication) — v3.1.1+.

**Regras de negócio:**
- Caminho base das instâncias: `/opt/nextcloud-customers/<cliente>/`.
- Prefixo opcional de nome de cliente via config option do módulo.

**Critérios de aceite:**
- [ ] Test Connection no WHMCS retorna sucesso com credenciais válidas
- [ ] Erros SSH exibem dica acionável ao admin

---

### Feature 4: Custom Fields e sincronização de domínio

- **Persona**: Admin do provedor, Cliente assinante
- **Prioridade**: Must-have

**Fluxo principal:**
1. Campo obrigatório **Domínio da Instância** no produto WHMCS.
2. Hook `AfterShoppingCartCheckout` copia valor para `tblhosting.domain`.
3. Hook `AcceptOrder` faz o mesmo para pedidos criados pelo admin.
4. `Helper::getDomain()` resolve domínio em cascata (params, custom fields, DB).

**Regras de negócio:**
- Sem Custom Field **Domínio da Instância**, `CreateAccount` aborta com mensagem específica.
- Lookup tolerante a acentos/case/encoding (v3.1.7).

**Critérios de aceite:**
- [ ] Checkout do cliente popula domínio automaticamente
- [ ] Pedido criado pelo admin funciona após preencher Custom Field antes de Accept Order

---

### Feature 5: Painel administrativo (Module Commands)

- **Persona**: Admin do provedor
- **Prioridade**: Must-have

**Botões disponíveis:**
- Provisionar Agora, Verificar Estado, Verificar DNS, Serviços Compartilhados
- Reiniciar Instância, Fazer Backup, Atualizar Instância
- Testar Conexão SSH, Testar API Nextcloud
- Ver Credenciais, Ver Logs, Ver Logs Talk Recording
- Listar Instâncias do Servidor

**Critérios de aceite:**
- [ ] Verificar Estado mostra status dos 3 containers dedicados
- [ ] Verificar DNS exibe tabela com IP esperado vs resolvido
- [ ] Ver Credenciais exibe painel HTML (inclui HaRP Shared Key no admin)

---

### Feature 6: Área do cliente

- **Persona**: Cliente assinante
- **Prioridade**: Must-have

**Fluxo principal:**
1. Cliente acessa serviço no WHMCS.
2. Template `clientarea.tpl` exibe status, DNS necessário, uso de disco, credenciais e links.

**Regras de negócio:**
- HaRP Shared Key **não** é exibida ao cliente (v3.1.7) — credencial interna AppAPI.
- Serviços globais `shared-*` listados como contexto, sem URLs de infra interna.

**Critérios de aceite:**
- [ ] Cliente vê aviso DNS enquanto serviço pendente
- [ ] Links de acesso ao Nextcloud funcionam após provisionamento
- [ ] Barra de uso de disco reflete `du -sh` no servidor

---

### Feature 7: Notificações e timeouts

- **Persona**: Cliente assinante, Admin do provedor
- **Prioridade**: Must-have

**Fluxo principal:**
1. Após provisionamento automático, e-mail HTML com credenciais é enviado ao cliente.
2. Se DNS não configurado em 3 dias, admin recebe notificação.

**Critérios de aceite:**
- [ ] E-mail de provisionamento contém URL, usuário e senha
- [ ] Timeout de 3 dias interrompe verificação DNS e alerta admin

---

### Feature 8: Qualidade e release

- **Persona**: Defensys / maintainer
- **Prioridade**: Must-have

**Componentes:**
- PHPUnit 10.5 em `tests/` — contrato Helper (1 DNS, 3 containers, 8 shared-services)
- CI GitHub Actions: `php -l` em PRs; release ZIP automático em tag `vX.Y.Z`
- `whmcs.json::version` deve bater com tag de release

**Critérios de aceite:**
- [ ] Suite PHPUnit passa localmente e em CI
- [ ] Tag `v3.1.7` gera release com ZIP instalável na raiz WHMCS

---

## 5. Integrações

| Sistema | Tipo | Direção | Autenticação | Fallback |
|---|---|---|---|---|
| WHMCS (módulo + hooks) | SDK interno | Bidirecional | Server module API + `localAPI` | Module Log + mensagem admin |
| SSH / manage.sh v11.x | CLI remoto | Saída | Password ou chave SSH (phpseclib3) | Mensagens enriquecidas por sintoma |
| Nextcloud OCS API | REST | Saída | Basic auth (admin) | `docker exec occ` via SSH |
| DNS público | Lookup | Entrada | `dns_get_record` / Helper | Cron revalida; timeout 3 dias |

**Autenticação do sistema**: credenciais do servidor WHMCS para SSH; credenciais Nextcloud armazenadas em custom fields e password do serviço WHMCS. HaRP Shared Key visível apenas no painel admin.

---

## 6. Requisitos Não-Funcionais

| Categoria | Requisito | Meta |
|---|---|---|
| Compatibilidade | manage.sh e arquitetura compartilhada | v11.3+; 3 containers dedicados + 8 `shared-*` |
| DNS | Registros por cliente | 1 registro A (domínio principal) |
| Segurança | SSH e credenciais | sudo NOPASSWD; HaRP Shared Key só no admin |
| Performance | Provisionamento | Assíncrono via cron (não bloquear checkout) |
| Disponibilidade | Dependência de serviços shared | Instância depende dos 8 `shared-*` UP no host |
| Compliance | LGPD | Dados de clientes em WHMCS + servidor Nextcloud; responsabilidades do provedor |
| Manutenibilidade | Testes e CI | PHPUnit + lint PHP + release automatizado |

---

## 7. Fora de Escopo

- Instalação e operação do **Nextcloud SaaS Manager** no host (`setup-shared.sh`, Traefik, containers `shared-*`)
- Desenvolvimento de features do **core Nextcloud**
- Billing, gateway de pagamento e faturamento (responsabilidade nativa do WHMCS)
- UI multi-servidor WHMCS unificada (cada server WHMCS = um host Nextcloud)
- Redesign visual do tema WHMCS além dos templates do módulo
- Gestão de múltiplos datacenters / failover entre hosts

> Definido junto com o usuário na fase de planejamento Beesy (baseline as-is).

---

## 8. Premissas

- O host Nextcloud já possui `manage.sh` v11.3+ instalado em `/opt/nextcloud-customers` com `setup-shared.sh` executado.
- Traefik está configurado como proxy reverso com SSL Let's Encrypt.
- Utilizador SSH no host possui `sudo NOPASSWD`.
- WHMCS está configurado com Custom Fields obrigatórios conforme README §2.4.1.
- Produto v3.1.7 é a baseline funcional; evoluções futuras passam por sprints no ROADMAP.

---

## 9. Dúvidas em Aberto

| # | Dúvida | Impacto | Status |
|---|---|---|---|
| 1 | Perfil Beesy formal (`perfil` em `.cursorsession`) — criar skill PHP/WHMCS ou manter `null`? | Médio | Aberta — resolver em `/arquiteto planejar` |
| 2 | Escopo de testes PHPUnit — expandir além do contrato Helper? | Médio | Aberta — definir no ROADMAP |
| 3 | Multi-servidor WHMCS (vários hosts Nextcloud) — suporte futuro? | Baixo | Aberta |

---

## 10. Mapa do Legado

### Stack Atual

- **Frontend**: Smarty templates (`clientarea.tpl`, `error.tpl`)
- **Backend**: PHP puro — módulo WHMCS servers (`nextcloudsaas.php`)
- **Hooks**: `includes/hooks/nextcloudsaas_hooks.php`
- **Libs**: `Helper.php`, `SSHManager.php`, `NextcloudAPI.php`
- **Deps**: phpseclib3 (~3.0)
- **Testes**: PHPUnit 10.5 (`tests/`)
- **CI/CD**: GitHub Actions (Beesy robots + validation gate)

### Módulos de Código

| Módulo | Caminho | Responsabilidade |
|---|---|---|
| nextcloudsaas | `modules/servers/nextcloudsaas/` | Ciclo de vida WHMCS, botões admin/cliente, templates |
| hooks | `includes/hooks/` | Cron DNS, checkout, e-mails, AcceptOrder |
| tests | `tests/` | Contrato público do Helper |

### Features Existentes

| Feature | Manter | Modificar | Remover | Observação |
|---|---|---|---|---|
| Provisionamento DNS automático | x | | | Baseline v3.1.7 |
| CreateAccount idempotente | x | | | v3.1.5+ |
| Ciclo de vida WHMCS completo | x | | | |
| Painel admin (15 botões) | x | | | |
| Área do cliente | x | | | HaRP key oculta v3.1.7 |
| PHPUnit Helper contract | x | | | Expandir no ROADMAP |
| CI + release por tag | x | | | |

### Backlog Beesy (não produto)

| Item | Artefato | Comando |
|---|---|---|
| Arquitetura técnica | `docs/ARCHITECTURE.md` | `/arquiteto planejar` |
| Roadmap e sprints | `docs/ROADMAP.md` | `/pmo plan` |
| Rules/skills do projeto | `.cursor/rules/`, `.cursor/skills/` | `/arquiteto padroes` |
| Session ledger | `.cursorsession.git` | `/jarvis upgrade` State 2 |

---

## Histórico de Revisões

| Data | Versão | Alteração | Autor |
|---|---|---|---|
| 2026-06-18 | 1.0 | Versão inicial — baseline as-is v3.1.7 | Analista (Rock + Composer 2.5) |
