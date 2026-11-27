# Matriz de rastreabilidade

Cada requisito aponta para classes reais e testes reais (`tests/Unit/...` e `tests/Feature/...`). Nomes de teste são os métodos existentes; classes de teste sem método indicado referem-se à classe inteira. Totais: **9 RF, 8 RNF, 10 RN, 10 US, 5 padrões**.

## 1. Requisitos funcionais

| RF | Descrição original | US | Classes ou componentes | Testes | Situação |
|---|---|---|---|---|---|
| RF01 | Listar produtos ativos e preços | US02 | `MenuController`, `Product` (`scopeActive`), `CatalogSeeder`, `menu/index.blade.php` | `MenuTest`, `CatalogSeederTest` | Atendido |
| RF02 | Criar pedido pendente | US03 | `OrderController::store`, `StoreOrderRequest`, `OrderService`, `Order` | `OrderFlowTest::test_valid_order_is_created_and_redirects_to_the_detail`, `OrderServiceTest` | Atendido |
| RF03 | Personalizar produto e calcular subtotal | US03 | `MenuItem`, `BaseMenuItem`, `MenuItemDecorator`, `Bacon/Cheese/EggDecorator`, `ExtraCatalog` | `MenuItemDecoratorTest`, `OrderServiceTest::test_subtotal_is_calculated_through_the_decorators` | Atendido |
| RF04 | Selecionar e calcular pagamento | US04 | `PaymentStrategy`, `Pix/Card/CashPaymentStrategy`, `PaymentCalculator`, `PaymentQuote` | `PixPaymentStrategyTest`, `CardPaymentStrategyTest`, `CashPaymentStrategyTest`, `PaymentCalculatorTest`, `PaymentQuoteTest`, `PaymentQuoteOrderTest` | Atendido |
| RF05 | Processar pagamento simulado | US05 | `PaymentGateway`, `PixGatewayAdapter`, `CardGatewayAdapter`, `CashPaymentGateway`, `PaymentResult`, `PaymentService`, `PaymentController` | `GatewayAdaptersTest`, `PaymentResultTest`, `PaymentServiceTest`, `PaymentFlowTest` | Atendido |
| RF06 | Avançar estado do pedido | US06 | `OrderState`, `AbstractOrderState`, 6 estados, `Order::state/transitionTo`, `OrderLifecycleService`, `OrderLifecycleController` | `OrderStateMatrixTest`, `OrderStateTest`, `OrderStateRehydrationTest`, `OrderLifecycleServiceTest`, `OrderLifecycleFlowTest` | Atendido |
| RF07 | Cancelar pedido pendente | US06 | `PendingState::cancel()`, `OrderLifecycleService::cancel` | `OrderLifecycleServiceTest::test_a_pending_order_can_be_cancelled_and_has_no_payment`, `OrderLifecycleFlowTest::test_cancellation_flow_rejects_every_later_attempt` | Atendido |
| RF08 | Registrar notificações e histórico | US07 | `OrderEventPublisher`, `OrderObserver`, `OrderStateChanged`, `HistoryObserver`, `CustomerNotificationObserver`, `KitchenNotificationObserver`, `OrderHistory`, `Notification` | `OrderEventPublisherTest`, `OrderObserverMatrixTest`, `OrderObserverIntegrityTest`, `OrderObserverNoEffectTest`, `OrderObserverRollbackTest` | Atendido |
| RF09 | Consultar pedidos, valores e estados | US08 | `OrderController::index/show`, `orders/index`, `orders/show`, `partials/status-badge` | `OrderListingTest`, `OrderDetailTest`, `OrderTrackingFlowTest` | Atendido |

## 2. Requisitos não funcionais

| RNF | Descrição original | Mecanismo implementado | Evidência de validação | Situação |
|---|---|---|---|---|
| RNF01 | Portabilidade (Docker e Compose v2 como únicos runtimes) | `Dockerfile` (estágio `dev`), `compose.yaml`, `Dockerfile.prod`, `compose.prod.yaml` | Subida limpa em clone temporário documentada em [Validação](VALIDACAO_E_TESTES.md) | Atendido |
| RNF02 | Integridade monetária e transacional | Centavos, `Money`, `transaction_mode=IMMEDIATE`, UNIQUEs, releitura na transação | `PixPaymentStrategyTest`, `PaymentServiceTest` (rollback), `PaymentConcurrencyTest`, `OrderLifecycleConcurrencyTest`, `OrderObserverRollbackTest`, `OrderObserverIntegrityTest` | Atendido |
| RNF03 | Segurança básica | Form Requests, CSRF, escape do Blade, `.gitignore`, `APP_DEBUG=false` em produção, Nginx restritivo | `PaymentFlowTest` e `OrderLifecycleFlowTest` (campos forjados, GET, CSRF), `MenuTest::test_product_names_are_escaped`, `OrderListingTest::test_stored_text_is_escaped`, `ProductionStackConfigTest`; smoke de produção | Atendido |
| RNF04 | Manutenibilidade (KISS, YAGNI, SOLID, padrões explícitos) | Estrutura `app/Patterns/`, serviços enxutos | Revisão técnica, [diagramas UML](diagrams/decorator-class.md) | Atendido |
| RNF05 | Isolamento dev/prod e persistência | Projetos, redes, volumes e chaves distintos; guardas dos entrypoints | `ProductionEntrypointGuardsTest`, `DevelopmentEntrypointGuardsTest`, `ProductionStackConfigTest`; persistência verificada nos ambientes | Atendido |
| RNF06 | Interface simples e responsiva sem Node | Blade, Bootstrap local, `foodorder.css` | `OrderListingTest::test_navigation_links_the_menu_and_the_orders_and_marks_the_current_page`; verificação visual em navegador (US08) | Atendido (sem leitor de tela nem teste automatizado de contraste) |
| RNF07 | Reprodutibilidade | `composer.lock`, README, Docker | Suíte no container; subida em clone limpo | Atendido |
| RNF08 | Demonstrabilidade | Esta matriz, [padrões](PADROES_DE_PROJETO.md), [roteiro](GUIA_DE_APRESENTACAO.md) | `OrderTrackingFlowTest::test_the_complete_flow_through_the_interface_shows_all_five_patterns` | Atendido |

## 3. Regras de negócio

| RN | Descrição original (resumo) | Implementação | Testes correspondentes | Situação |
|---|---|---|---|---|
| RN01 | Cardápio inicial e produtos ativos | `CatalogSeeder`, `Product::active()` | `CatalogSeederTest`, `MenuTest::test_inactive_products_are_not_listed`, `OrderServiceTest::test_inactive_products_are_rejected` | Atendida |
| RN02 | Complementos, compatibilidade e unicidade | `config/foodorder.php`, `ExtraCatalog`, `StoreOrderRequest`, `OrderService::selectExtras` | `ExtraCatalogTest`, `OrderServiceTest::test_invalid_selections_are_rejected_without_persisting`, `OrderFlowTest::test_form_for_fries_offers_no_extras`, `OrderFlowTest::test_invalid_extras_are_rejected_without_persisting` | Atendida |
| RN03 | Centavos inteiros; snapshot | colunas `*_cents`, `Money`, snapshots | `MoneyTest`, `MoneyParseTest`, `MenuTest::test_products_persist_price_in_integer_cents`, `OrderServiceTest::test_snapshot_is_stable_after_catalog_changes` | Atendida |
| RN04 | PIX 5%, cartão, dinheiro e troco | três `PaymentStrategy` | testes de `tests/Unit/Strategy/`, `PaymentServiceTest` | Atendida |
| RN05 | Pagamento só simulado; só aprovado muda estado; falha mantém pendente | adapters, `PaymentService` | `GatewayAdaptersTest::test_gateways_do_not_use_the_network`, `PaymentServiceTest::test_gateway_rejection_keeps_the_order_pending_and_allows_a_retry`, `PaymentFlowTest::test_gateway_rejection_shows_a_message_and_a_new_attempt_succeeds` | Atendida |
| RN06 | Nasce pendente; cancelar só pendente; sem edição; validação no servidor | `OrderService`, `PendingState`, Form Requests | `OrderFlowTest::test_order_without_extras_is_pending_with_base_price`, `OrderLifecycleFlowTest::test_cancellation_flow_rejects_every_later_attempt` | Atendida |
| RN07 | Estados terminais; sem repetir ou reabrir | `DeliveredState`, `CancelledState`, `AbstractOrderState` | `OrderStateMatrixTest::test_terminal_states_reject_every_operation`, `OrderLifecycleServiceTest::test_a_repeated_transition_is_rejected` | Atendida |
| RN08 | Uma vez por transição; inválida não gera evento; criação não é transição | `Order::transitionTo`, observers, UNIQUEs | `OrderObserverMatrixTest`, `OrderObserverNoEffectTest`, `OrderObserverIntegrityTest` | Atendida |
| RN09 | Dados do navegador não são confiáveis | recálculo no servidor | `OrderFlowTest::test_forged_price_and_status_are_ignored`, `PaymentFlowTest::test_forged_totals_discounts_change_and_status_are_ignored`, `OrderLifecycleFlowTest::test_forged_fields_cannot_change_the_status` | Atendida |
| RN10 | Atomicidade e unicidade do pagamento aprovado | transação `IMMEDIATE`, releitura, UNIQUE | `PaymentConcurrencyTest`, `OrderLifecycleConcurrencyTest`, `PaymentServiceTest::test_failure_after_the_payment_insert_rolls_everything_back`, `PaymentServiceTest::test_payments_order_id_is_unique_in_the_database`, `OrderObserverRollbackTest` | Atendida |

## 4. User Stories

| US | Objetivo | Requisitos relacionados | Padrão GoF | Evidência | Status |
|---|---|---|---|---|---|
| US01 | Ambiente de desenvolvimento em Docker | RNF01, RNF05, RNF07 | — | `ApplicationBootstrapTest`, `DevelopmentEntrypointGuardsTest` | Concluída |
| US02 | Consultar cardápio | RF01, RN01 | — | `MenuTest`, `CatalogSeederTest` | Concluída |
| US03 | Criar pedido personalizado | RF02, RF03, RN02, RN03, RN06, RN09 | Decorator | `MenuItemDecoratorTest`, `OrderServiceTest`, `OrderFlowTest` | Concluída |
| US04 | Calcular pagamento por método | RF04, RN04 | Strategy | `tests/Unit/Strategy/*` | Concluída |
| US05 | Simular e confirmar pagamento | RF05, RN05, RN10 | Adapter (+ início de State) | `GatewayAdaptersTest`, `PaymentServiceTest`, `PaymentConcurrencyTest` | Concluída |
| US06 | Gerenciar ciclo de vida | RF06, RF07, RN06, RN07 | State | `OrderStateMatrixTest`, `OrderLifecycle*Test` | Concluída |
| US07 | Notificar e registrar transições | RF08, RN08, RN10 | Observer | `OrderObserver*Test`, `OrderEventPublisherTest` | Concluída |
| US08 | Consultar e acompanhar pedidos | RF09, RNF06 | — | `OrderListingTest`, `OrderDetailTest`, `OrderTrackingFlowTest` | Concluída |
| US09 | Stack de produção isolada | RNF01, RNF03, RNF05 | — | `tests/Feature/Infra/*` | Concluída |
| US10 | Validar e documentar | RNF04, RNF07, RNF08 | demonstra os cinco | esta documentação e [Validação](VALIDACAO_E_TESTES.md) | Concluída |

## 5. Padrões de projeto

| Padrão | Problema resolvido | Classes principais | Fluxo | Testes |
|---|---|---|---|---|
| Decorator | Combinar complementos sem subclasses por combinação | `MenuItem`, `BaseMenuItem`, `MenuItemDecorator`, `BaconDecorator`, `CheeseDecorator`, `EggDecorator` | [Criação do pedido](diagrams/create-order-sequence.md) | `MenuItemDecoratorTest`, `ExtraCatalogTest`, `OrderServiceTest` |
| Strategy | Calcular o total por método sem `switch` central | `PaymentStrategy`, `Pix/Card/CashPaymentStrategy`, `PaymentCalculator`, `PaymentQuote` | [Pagamento](diagrams/payment-sequence.md) | `PixPaymentStrategyTest`, `CardPaymentStrategyTest`, `CashPaymentStrategyTest`, `PaymentCalculatorTest`, `PaymentQuoteTest` |
| Adapter | Gateways legados com contratos incompatíveis | `PaymentGateway`, `PixGatewayAdapter`, `CardGatewayAdapter`, `LegacyPixGateway`, `LegacyCardGateway`, `PaymentResult` | [Pagamento](diagrams/payment-sequence.md) | `GatewayAdaptersTest`, `PaymentResultTest` |
| State | Operações dependem do ciclo de vida do pedido | `OrderState`, `AbstractOrderState`, 6 estados, `Order` | [Ciclo de vida](diagrams/lifecycle-sequence.md), [estados](diagrams/order-state.md) | `OrderStateMatrixTest`, `OrderStateTest`, `OrderStateRehydrationTest`, `OrderLifecycleServiceTest` |
| Observer | Efeitos independentes a cada transição | `OrderEventPublisher`, `OrderObserver`, `OrderStateChanged`, 3 observers | [Pagamento](diagrams/payment-sequence.md), [ciclo de vida](diagrams/lifecycle-sequence.md) | `OrderEventPublisherTest`, `OrderObserverMatrixTest`, `OrderObserverRollbackTest` |

Voltar: [índice](README.md)
