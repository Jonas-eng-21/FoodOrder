# Requisitos e regras de negócio

Os identificadores e significados abaixo são os da especificação original do projeto. A coluna *Evidência* aponta para código e testes reais; a [matriz de rastreabilidade](MATRIZ_DE_RASTREABILIDADE.md) detalha classes e testes.

## Requisitos funcionais

| ID | Descrição original | Implementação | Evidência | Situação |
|---|---|---|---|---|
| RF01 | Listar produtos ativos e preços | `MenuController`, `Product::active()`, `resources/views/menu/index.blade.php` | `MenuTest` | Atendido |
| RF02 | Criar pedido pendente | `OrderController::store`, `StoreOrderRequest`, `OrderService::create` | `OrderFlowTest`, `OrderServiceTest` | Atendido |
| RF03 | Personalizar produto e calcular subtotal | Decorator (`app/Patterns/Decorator/`) usado por `OrderService` | `MenuItemDecoratorTest`, `OrderServiceTest` | Atendido |
| RF04 | Selecionar e calcular pagamento | Strategy (`PaymentCalculator` e estratégias) | testes em `tests/Unit/Strategy/` | Atendido |
| RF05 | Processar pagamento simulado | Adapter (`PaymentGateway`, adapters, `PaymentService`) | `GatewayAdaptersTest`, `PaymentServiceTest`, `PaymentFlowTest` | Atendido |
| RF06 | Avançar estado do pedido | State + `OrderLifecycleService` | `OrderStateMatrixTest`, `OrderLifecycleFlowTest` | Atendido |
| RF07 | Cancelar pedido pendente | `PendingState::cancel()` | `OrderLifecycleServiceTest`, `OrderLifecycleFlowTest` | Atendido |
| RF08 | Registrar notificações e histórico | Observer (`OrderEventPublisher`, 3 observers) | `OrderObserverMatrixTest` e demais | Atendido |
| RF09 | Consultar pedidos, valores e estados | `OrderController::index/show`, views de pedidos | `OrderListingTest`, `OrderDetailTest`, `OrderTrackingFlowTest` | Atendido |

## Requisitos não funcionais

| ID | Descrição original | Implementação | Evidência | Situação |
|---|---|---|---|---|
| RNF01 | Portabilidade: Docker Engine e Compose v2 como únicos runtimes no host | `Dockerfile`, `compose.yaml`, `Dockerfile.prod`, `compose.prod.yaml` | Build e subida em clone limpo ([validação](VALIDACAO_E_TESTES.md)) | Atendido |
| RNF02 | Integridade monetária e transacional | Centavos inteiros, `Money`, transações `BEGIN IMMEDIATE`, UNIQUEs | Testes de PIX, rollback, concorrência e unicidade | Atendido |
| RNF03 | Segurança básica: CSRF, validação server-side, escaping Blade, segredos fora do Git e debug desligado em produção | Form Requests, middleware CSRF, `{{ }}` do Blade, `.gitignore`, `APP_DEBUG=false` na produção | Testes HTTP (`PaymentFlowTest`, `OrderLifecycleFlowTest`), `ProductionStackConfigTest`, smoke de produção | Atendido |
| RNF04 | Manutenibilidade: KISS, YAGNI, SOLID pragmático, padrões explícitos | Monólito MVC e `app/Patterns/` | Revisão de código e diagramas | Atendido |
| RNF05 | Isolamento dev/prod e persistência de dados | Stacks `foodorder` e `foodorder-prod`, guardas de ambiente | Testes de infraestrutura e inspeção dos ambientes | Atendido |
| RNF06 | Interface simples e responsiva, sem etapa Node obrigatória | Blade, Bootstrap local, `foodorder.css` | Verificação visual da US08 (5 viewports) | Atendido |
| RNF07 | Reprodutibilidade: dependências travadas, instruções e testes em container | `composer.lock` versionado, README e Docker | Testes no container, clone limpo | Atendido |
| RNF08 | Demonstrabilidade: cada padrão rastreável a classes, teste e cenário | Esta documentação e a matriz | [Matriz](MATRIZ_DE_RASTREABILIDADE.md) | Atendido |

## Regras de negócio

| ID | Descrição original (resumo fiel) | Implementação | Situação |
|---|---|---|---|
| RN01 | Cardápio inicial: hambúrguer R$ 20,00, cachorro-quente R$ 15,00, batata frita R$ 10,00. Somente produtos ativos | `CatalogSeeder`, `Product::active()` | Atendida |
| RN02 | Complementos: bacon R$ 4,00, queijo extra R$ 3,00, ovo R$ 2,00; para hambúrguer e cachorro-quente; nenhum para batata; cada um no máximo uma vez | `config/foodorder.php`, `ExtraCatalog`, `StoreOrderRequest`, `OrderService` | Atendida |
| RN03 | Valores em centavos inteiros, nunca `float`; descrição e preço derivados dos decorators e persistidos como snapshot | Colunas `*_cents`, `Money`, snapshots em `orders` | Atendida |
| RN04 | PIX: 5% de desconto, total com `intdiv($s*95+50,100)`; cartão: subtotal; dinheiro: subtotal, exige valor suficiente e calcula troco | Estratégias de pagamento | Atendida |
| RN05 | Pagamento exclusivamente simulado; só pagamento aprovado muda para pago; falha mantém pendente e permite nova tentativa | `PaymentService`, adapters, gateways simulados | Atendida |
| RN06 | Pedido nasce pendente; cancelar somente pendente; itens não editáveis; validação no servidor | `OrderService`, `PendingState`, Form Requests | Atendida |
| RN07 | Terminais: entregue e cancelado; sem repetir, pular etapa ou reabrir | `DeliveredState`, `CancelledState`, `AbstractOrderState` | Atendida |
| RN08 | Cada transição bem-sucedida registra histórico e notificação uma única vez; tentativa inválida não gera evento; criação não é transição | `Order::transitionTo()`, Observer, UNIQUEs | Atendida |
| RN09 | Preço, desconto, estado e troco do navegador não são confiáveis; recalcular no servidor | Controllers/serviços ignoram esses campos; `test_forged_*` | Atendida |
| RN10 | Persistir pagamento aprovado, estado e efeitos atomicamente; impedir processamento duplicado concorrente | Transação `IMMEDIATE`, releitura, `payments.order_id` UNIQUE | Atendida |

## Limites do MVP

Ver [Visão geral](VISAO_GERAL.md#escopo-do-mvp). Resumo: sem autenticação, sem TLS, sem pagamentos reais, sem estoque, sem carrinho, sem filas. A aplicação é de demonstração, para uso local ou rede privada controlada.

## Backlog consolidado de User Stories

| US | História | Prioridade | Padrão | Status final |
|---|---|---|---|---|
| US01 | Preparar aplicação e ambiente de desenvolvimento | P0 | — | Concluída |
| US02 | Consultar cardápio | P0 | — | Concluída |
| US03 | Criar pedido personalizado | P0 | Decorator | Concluída |
| US04 | Calcular pagamento por método | P0 | Strategy | Concluída |
| US05 | Simular e confirmar pagamento | P0 | Adapter (+ início de State) | Concluída |
| US06 | Gerenciar ciclo de vida do pedido | P0 | State | Concluída |
| US07 | Notificar e registrar transições | P0 | Observer | Concluída |
| US08 | Consultar e acompanhar pedidos | P0 | — | Concluída |
| US09 | Disponibilizar stack de produção isolada | P0 | — | Concluída |
| US10 | Validar e documentar demonstração acadêmica | P0 | demonstra os cinco | Concluída com a publicação desta versão (ver [Validação](VALIDACAO_E_TESTES.md)) |

### Critérios de aceite da US10 (originais)

1. Suíte executada em Docker, sem falhas não justificadas; smoke dev/prod registrado com resultados reais.
2. Cada padrão com problema, justificativa, participantes, caminho de código, teste e exemplo demonstrável.
3. Diagramas de classes, estados e arquitetura correspondendo ao código implementado.
4. README com setup limpo, comandos, testes, limitações, uso privado e simulação de pagamento.
5. Roteiro que permite criar pedido personalizado, pagar por PIX, avançar estados, observar avisos e demonstrar rejeição de ação inválida.
6. Backlog e pendências atualizados; sem marcar como concluída uma US não validada.

A verificação de cada critério está em [Validação e testes](VALIDACAO_E_TESTES.md#critérios-de-aceite-da-us10).

Voltar: [índice](README.md)
