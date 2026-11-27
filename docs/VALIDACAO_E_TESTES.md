# Validação e testes

Registro dos resultados **efetivamente obtidos** na validação final (2026-11-27). Nada aqui é estimativa: onde algo não foi medido ou renderizado, o texto diz isso.

## 1. Estratégia de testes

- **TDD (Red → Green → Refactor)** em todas as User Stories: os testes eram escritos antes e falhavam pelo motivo esperado (classe ou rota inexistente), a implementação mínima os fazia passar e só então havia refinamento. Os ciclos foram registrados no histórico interno de desenvolvimento.
- **Unitários** (`tests/Unit/`): classes isoladas (decorators, estratégias, adapters, estados, publisher, `Money`).
- **Feature** (`tests/Feature/`): HTTP, banco e serviços integrados (fluxos de pedido, pagamento, ciclo de vida, listagem e detalhe).
- **Integração e transacionais:** falhas injetadas (gateway, gravação do pagamento, observers) verificando rollback.
- **Concorrência real:** dois processos PHP sobre um SQLite em arquivo temporário (`tests/Support/RunsConcurrentOrderActions.php`).
- **Infraestrutura** (`tests/Feature/Infra/`): guardas dos entrypoints, configuração do Compose e do Nginx, comportamento do `dcp.sh` (com um `docker` falso).
- **Smoke tests** manuais/por `curl` nos dois ambientes e **validação visual** em navegador real (US08, em cinco larguras; reconferida em 375 px nesta validação).
- **Prevenção de regressões:** a suíte completa é executada a cada User Story e, durante o desenvolvimento, em cada commit isolado (`git worktree`).

## 2. Evolução dos testes por User Story

Valores registrados no histórico ao final de cada US:

| US | Testes | Asserções |
|---|---:|---:|
| US01 | 4 | 10 |
| US02 | 20 | 36 |
| US03 | 68 | 184 |
| US04 | 110 | 358 |
| US05 | 206 | 648 |
| US06 | 315 | 1099 |
| US07 | 354 | 1288 |
| US08 | 385 | 1450 |
| US09 | 487 | 1828 |
| US10 | **487** | **1828** (nenhum teste ou código executável foi alterado; ver seção 5) |

A contagem de testes **não** é um percentual de cobertura.

## 3. Testes por padrão

| Padrão | Testes |
|---|---|
| Decorator | `tests/Unit/Decorator/MenuItemDecoratorTest.php` (10), `ExtraCatalogTest.php` (2); `tests/Feature/OrderServiceTest.php` (composição pelo serviço) |
| Strategy | `tests/Unit/Strategy/`: `PixPaymentStrategyTest` (15), `CardPaymentStrategyTest` (6), `CashPaymentStrategyTest` (9), `PaymentCalculatorTest` (5), `PaymentQuoteTest` (4); `tests/Feature/PaymentQuoteOrderTest.php` |
| Adapter | `tests/Unit/Adapter/GatewayAdaptersTest.php` (30), `PaymentResultTest.php` (3) |
| State | `tests/Unit/State/OrderStateMatrixTest.php` (34), `OrderStateTest.php` (4); `tests/Feature/OrderStateRehydrationTest.php` (7), `OrderLifecycleServiceTest.php`, `OrderLifecycleFlowTest.php` |
| Observer | `tests/Unit/Observer/OrderEventPublisherTest.php` (10); `tests/Feature/OrderObserver{Matrix,Integrity,NoEffect,Rollback}Test.php` |

(Os números entre parênteses são as execuções reportadas pelo PHPUnit, incluindo casos de *data providers*.)

## 4. Testes críticos

| Tema | Onde |
|---|---|
| Valores monetários inválidos | `MoneyTest`, `MoneyParseTest`, `PaymentQuoteTest`, `PaymentFlowTest::test_invalid_requests_are_rejected_without_payment` |
| Arredondamento do PIX | `PixPaymentStrategyTest` (meio centavo, 2001/2010/2011, overflow) |
| Dinheiro insuficiente ou ausente | `CashPaymentStrategyTest`, `PaymentServiceTest::test_invalid_cash_keeps_the_order_pending_and_never_calls_the_gateway` |
| Complementos inválidos | `OrderServiceTest::test_invalid_selections_are_rejected_without_persisting`, `OrderFlowTest::test_invalid_extras_are_rejected_without_persisting` |
| Produtos inativos | `OrderServiceTest::test_inactive_products_are_rejected`, `OrderFlowTest::test_product_deactivated_after_opening_the_form_is_rejected_on_confirmation` |
| Manipulação de requisições | `OrderFlowTest::test_forged_price_and_status_are_ignored`, `PaymentFlowTest::test_forged_totals_discounts_change_and_status_are_ignored`, `OrderLifecycleFlowTest::test_forged_fields_cannot_change_the_status`, `test_direct_posts_are_rejected_even_when_the_button_is_not_visible`, `test_action_forms_carry_a_csrf_token` |
| Pagamentos duplicados | `PaymentServiceTest::test_a_paid_order_cannot_be_paid_again_and_the_gateway_is_not_called`, `PaymentFlowTest::test_paying_twice_is_rejected_and_creates_a_single_payment`, `PaymentConcurrencyTest` |
| Transições inválidas | `OrderStateMatrixTest`, `OrderLifecycleServiceTest::test_invalid_operations_are_rejected_without_changing_anything` |
| Pagamento × cancelamento concorrentes | `OrderLifecycleConcurrencyTest::test_payment_that_wins_the_lock_makes_the_concurrent_cancellation_fail` e `test_cancellation_that_wins_the_lock_rejects_the_payment_before_the_gateway` |
| Rollback de pagamento | `PaymentServiceTest::test_failure_after_the_payment_insert_rolls_everything_back`, `test_failure_inserting_the_payment_leaves_the_order_pending` |
| Rollback por falha de observer | `OrderObserverRollbackTest` (5), `OrderLifecycleConcurrencyTest::test_a_failing_observer_rolls_everything_back_on_a_real_database_file` |
| Ausência de efeitos duplicados | `OrderObserverNoEffectTest::test_repeated_payment_and_delivery_do_not_duplicate_records`, `OrderEventPublisherTest::test_subscribing_the_same_instance_twice_does_not_duplicate_calls` |
| Restrições de unicidade | `PaymentServiceTest::test_payments_order_id_is_unique_in_the_database`, `OrderObserverIntegrityTest::test_history_has_a_unique_constraint_per_order_and_new_status` e `test_notifications_have_a_unique_constraint_per_order_event_and_audience` |
| Preservação de snapshots | `OrderServiceTest::test_snapshot_is_stable_after_catalog_changes`, `PaymentServiceTest::test_catalog_changes_do_not_alter_the_payment_or_the_subtotal`, `OrderDetailTest::test_the_composition_is_stable_after_catalog_changes` |
| Isolamento dos bancos | `phpunit.xml` força `:memory:`; `ApplicationBootstrapTest::test_tests_run_against_an_isolated_in_memory_sqlite_database` |
| Guardas de ambiente | `ProductionEntrypointGuardsTest` (24), `DevelopmentEntrypointGuardsTest` (3) |
| Proteções do `dcp.sh` | `ProductionScriptTest` (67), `ProductionStackConfigTest` (8) |

## 5. Execução final

Data: **2026-11-27**. Ambiente: container `foodorder-app-1` (stack de desenvolvimento).

| Item | Versão |
|---|---|
| PHP | 8.4.28 |
| Laravel | 12.74.0 |
| PHPUnit | 11.5.58 |
| Laravel Pint | 1.32.3 |
| SQLite (PDO) | 3.40.1 |
| Docker / Compose | 29.10.0 / v5.7.1 (API Compose v2) |

Comandos e resultados:

```bash
docker compose exec app php artisan test        # 487 passed (1828 assertions), 37 s; sem falhas, avisos, ignorados ou arriscados
docker compose exec app vendor/bin/pint --test  # PASS (127 files)
git diff --check                                # sem problemas
```

- Filtros relevantes: `--filter=Rollback` → 5 passed (14 assertions); `--filter=Infra` → 102 passed (378); `--filter=Observer` → 40 passed (182); `--filter=Concurrency` → 8 passed (40) por rodada.
- Nenhum teste foi ignorado, marcado como arriscado ou com aviso na saída.
- **Observação sobre o escopo da US10:** a revisão técnica não encontrou defeito que exigisse correção; por isso nenhum código executável nem teste foi alterado nesta etapa, e as imagens de produção em execução correspondem ao código final. A US10 acrescentou apenas documentação (`docs/` e `README.md`).

## 6. Testes de concorrência

Os testes `PaymentConcurrencyTest` e `OrderLifecycleConcurrencyTest` iniciam **dois processos PHP reais** contra um SQLite em arquivo e dependem de temporização (o processo perdedor espera o bloqueio de escrita e depois relê o estado). Como já havia a ressalva de sensibilidade temporal, foram repetidos em **6 rodadas consecutivas** (`php artisan test --filter=Concurrency`):

| Rodada | Resultado | Duração |
|---|---|---|
| 1 a 6 | 8 passed (40 assertions) em todas | 27,0 s a 27,8 s |

Nenhuma instabilidade foi observada, nenhuma repetição foi descartada e nenhum tempo de espera foi alterado. A garantia preservada é a de [ARQUITETURA D8](ARQUITETURA.md#4-decisões-arquiteturais): `BEGIN IMMEDIATE` + releitura + UNIQUE. Se uma instabilidade surgir em outra máquina, deve ser investigada e não mascarada com repetições.

## 7. Cobertura

A cobertura percentual de código **não foi medida** (não há meta de cobertura no projeto). A qualidade das verificações é demonstrada pelos casos acima, não por uma porcentagem.

## 8. Smoke tests finais

Respostas HTTP reais (2026-11-27). Dev = `http://127.0.0.1:8082`; prod = `http://127.0.0.1:8081`.

| Requisição | Dev | Prod |
|---|---|---|
| `GET /` | 200 | 200 |
| `GET /up` | 200 | 200 |
| `GET /orders` | 200 | 200 |
| `GET /orders/1` (existente em ambos) | 200 | 200 |
| `GET /orders/99999` | 404 | 404 |
| `GET /css/foodorder.css`, `/vendor/bootstrap/css/bootstrap.min.css` | 200 | 200 |
| `GET /.env`, `/composer.json`, `/context/…` | 404 | 404 |

Cabeçalhos de segurança da produção: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Content-Security-Policy` restritiva e `server_tokens off`.

**Fluxo integrado completo** (via HTTP, com CSRF, em cada ambiente): pedido de Hambúrguer + Bacon + Queijo extra → subtotal R$ 27,00 e descrição composta → pagamento PIX R$ 25,65 com referência `SIMULATED-PIX-…` → pagamento repetido, cancelamento de pedido pago e cancelamento de pedido entregue **rejeitados** → iniciar, finalizar e entregar → `delivered`. Banco: 1 pagamento, **4 históricos, 4 notificações de cliente e 2 de cozinha**. Registros criados exclusivamente nesta validação: pedido **#14** no desenvolvimento e **#4** na produção (os pedidos existentes não foram alterados).

**Contagens após a validação:** desenvolvimento (produtos 3, pedidos 14, pagamentos 9, históricos 13, notificações 19); produção (3, 4, 2, 9, 13). As contagens anteriores (13/8/9/13 e 3/1/5/7 para pedidos/pagamentos/históricos/notificações) cresceram exatamente pelos registros do pedido novo de cada ambiente, sem interferência entre eles.

**Interface móvel:** o detalhe do pedido #14 em 375 px não tem rolagem horizontal e exibe composição, pagamento (PIX R$ 25,65), histórico (4 itens) e notificações.

## 9. Ambientes e isolamento

| Verificação | Resultado |
|---|---|
| Containers | `foodorder-app-1`, `foodorder-prod-app-1` e `foodorder-prod-web-1` saudáveis |
| Redes | `foodorder_default` e `foodorder-prod_default` |
| Volumes | `foodorder_sqlite_data`, `foodorder_vendor_data` e `foodorder-prod_sqlite_data` |
| Produção | `APP_ENV=production`, `APP_DEBUG=false`, usuários `app` e `101`, raiz somente leitura, único mount do `app` é o volume `/data`, `web` sem mounts |
| Outros projetos | Containers Nexora e portfolio não foram alterados |

## 10. Reprodutibilidade (clone limpo)

`git clone` do repositório (código idêntico ao da versão final; a diferença é apenas documentação) em diretório temporário, **sem copiar `.env` ou `.env.prod` reais**, com projetos Compose, portas (8095 e 8096), volumes e imagens temporários, seguindo os comandos do README:

- **Desenvolvimento:** chave gerada em container, `up --build -d`, saudável; `/`, `/up`, `/orders` → 200; 3 produtos; **487 testes passando (1828 asserções)** e Pint PASS (127 files) no clone.
- **Produção:** `cp .env.prod.example .env.prod`, `build`, `init-key`, `up -d --build`; `app` e `web` saudáveis; `/`, `/up`, `/orders`, CSS → 200; `/.env` → 404; banco com 3 produtos e 0 pedidos; `APP_ENV=production`, `APP_DEBUG=false`; sem PHPUnit nem `.env` na imagem. Como o `dcp.sh` fixa o projeto `foodorder-prod`, a cópia do clone teve os nomes de projeto e de imagem trocados (somente na cópia temporária) para não tocar a produção real.
- Sem PHP, Composer ou Node no host. Todos os recursos temporários foram removidos (apenas os do teste); os volumes e imagens principais foram preservados.

## 11. Renderização dos diagramas

Os diagramas estão em Mermaid, que o GitHub renderiza. **Nenhum foi renderizado localmente** nesta validação: não havia ferramenta de renderização disponível e nenhuma foi instalada para isso. Eles receberam **revisão manual de sintaxe e de consistência com o código** (nomes de classes, métodos, relações e tabelas conferidos). O diagrama de casos de uso usa uma aproximação em fluxo, pois Mermaid não tem notação UML de casos de uso.

## Critérios de aceite da US10

| Critério (guia, US10) | Status | Evidência |
|---|---|---|
| Suíte em Docker sem falhas injustificadas; smoke dev/prod com resultados reais | Atendido | Seções 5 e 8 |
| Cada padrão com problema, justificativa, participantes, caminho de código, teste e exemplo demonstrável | Atendido | [Padrões de projeto](PADROES_DE_PROJETO.md) (seções 1.x a 5.x) |
| Diagramas de classes, estados e arquitetura correspondem ao código | Atendido (revisão manual; não renderizados localmente) | [Diagramas](README.md#diagramas); seção 11 |
| README com setup limpo, comandos, testes, limitações, uso privado e simulação de pagamento | Atendido | [README](../README.md); clone limpo (seção 10) |
| Roteiro: pedido personalizado, PIX, estados, avisos e rejeição de ação inválida | Atendido | [Guia de apresentação](GUIA_DE_APRESENTACAO.md); fluxo executado na seção 8 |
| Backlog e pendências atualizados; sem marcar US não validada | Atendido | [Requisitos](REQUISITOS_E_REGRAS_DE_NEGOCIO.md#backlog-consolidado-de-user-stories) e a seção abaixo |

## Limitações conhecidas e classificação

| Limitação | Classificação | Tratamento |
|---|---|---|
| Ausência de autenticação e autorização | Limitação aceita do MVP | Documentada; uso local ou rede privada |
| Ausência de TLS | Limitação aceita do MVP | Publicação apenas em `127.0.0.1`; documentada |
| CSP com `script-src 'unsafe-inline'` | Limitação aceita | Existem dois scripts inline opcionais nas telas de pedido; removê-lo exigiria nonce ou arquivos `.js` |
| Sessões de produção em tmpfs | Limitação aceita | Reiniciar invalida sessões; recarregar o formulário |
| Backup e restauração manuais | Limitação aceita | Procedimento validado em [Ambientes Docker](AMBIENTES_DOCKER.md#7-backup-e-restauração-procedimento-manual-validado) |
| Pedidos anteriores ao Observer sem histórico | Limitação aceita | Nenhum histórico retroativo foi fabricado |
| Horários em UTC | Limitação aceita | Documentado |
| Sem paginação e filtros | Fora do escopo | Não previstos no MVP |
| Sem validação por leitores de tela | Validação não realizada | Não executada |
| Sem teste automatizado de contraste | Validação não realizada | Contraste conferido visualmente na US08 |
| Valores não negativos | Garantia da aplicação | O SQLite não impõe `CHECK`; ver [Modelagem](MODELAGEM_DE_DADOS.md#4-integridade-monetária) |
| Referência residual a `User` em `config/auth.php` | Limitação aceita | O model foi removido por YAGNI; não há autenticação, sem efeito. Não alterada por não ser necessária |
| Testes concorrentes sensíveis ao tempo | Registrada e revalidada | 6 rodadas sem falha (seção 6) |
| Commits intermediários da US02 não funcionais | Preservados | O histórico não foi reescrito |
| Diretório vazio residual `/tmp/fo-backup.sqlite` no host | Registrada | Criado pelo Docker (dono root) em um teste de backup; inofensivo e fora do projeto; não alterado |
| Diagramas não renderizados localmente | Validação não realizada | Revisão manual (seção 11) |
| Cobertura percentual não medida | Não aplicável | Sem meta de cobertura |
| Notificações são registros internos | Limite do escopo | Não há envio por e-mail ou WhatsApp |
| Pagamento exclusivamente simulado | Limite do escopo | Sem integração financeira |

Nenhum item é **impedimento para conclusão**. Melhorias futuras opcionais (não comprometidas): autenticação, TLS, carrinho com vários itens, paginação, notificações reais, CI.

Voltar: [índice](README.md)
