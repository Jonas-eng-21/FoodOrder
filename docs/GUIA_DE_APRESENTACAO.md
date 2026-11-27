# Guia de apresentação acadêmica

Roteiro para apresentar o FoodOrder na disciplina de Padrões de Projeto. O tempo oficial ainda não foi informado: os tempos abaixo (≈ 15 min) são uma referência e podem ser comprimidos ou expandidos. Não há slides prontos; esta é a base para montá-los.

## 1. Estrutura sugerida (≈ 15 min)

| Etapa | Tempo |
|---|---:|
| Contextualização e problema | 1 min |
| Objetivo e escopo | 1 min |
| Arquitetura e tecnologias | 2 min |
| Os cinco padrões | 5 min |
| Demonstração prática | 4 min |
| Testes, resultados e conclusão | 2 min |

## 2. Slides sugeridos (15)

Os números de testes/asserções finais estão em [Validação e testes](VALIDACAO_E_TESTES.md).

| # | Título | Objetivo | Conteúdo principal | Evidência recomendada | Sugestão de fala |
|---|---|---|---|---|---|
| 1 | Capa | Identificar o trabalho | FoodOrder, disciplina, autoria do grupo/aluno | — | "Apresento o FoodOrder, um sistema de pedidos de lanchonete que usa cinco padrões GoF." |
| 2 | Contextualização | Situar o domínio | Lanchonete: pedidos personalizados, pagamentos diferentes, acompanhamento | Captura do cardápio (`GET /`) | "Um pedido tem variações, formas de pagar e etapas; sem organização, vira um emaranhado de `if`." |
| 3 | Problema e objetivo | Definir o alvo | Problema; objetivo geral e específicos | [Visão geral](VISAO_GERAL.md) | "O objetivo é aplicar os padrões onde eles resolvem um problema real." |
| 4 | Escopo e funcionalidades | Delimitar | O que entra e o que não entra (sem login, sem pagamento real) | Tabela de funcionalidades | "Pagamento é simulado e não há autenticação: é uma demonstração local." |
| 5 | Arquitetura e tecnologias | Mostrar a base | Monólito MVC, Laravel 12, PHP 8.4, SQLite, Docker | [C4 contêineres](diagrams/c4-container.md), [componentes](diagrams/c4-component.md) | "Escolhi um monólito por KISS e YAGNI: tudo em uma transação." |
| 6 | Decorator | Composição de itens | Problema da explosão de subclasses; 2000 + 400 + 300 = 2700 | [UML](diagrams/decorator-class.md); trecho de `OrderService::create` | Ver 3.1 |
| 7 | Strategy | Cálculo por método | PIX 2565, cartão 2700, dinheiro 2700 com troco 300 | [UML](diagrams/strategy-class.md); `PaymentCalculator` | Ver 3.2 |
| 8 | Adapter | Gateways incompatíveis | `sendPix(int): array` × `charge(string): string` → `PaymentGateway` | [UML](diagrams/adapter-class.md); `CardGatewayAdapter` | Ver 3.3 |
| 9 | State | Ciclo de vida | Matriz 5 válidas / 25 inválidas; terminais | [Estados](diagrams/order-state.md), [UML](diagrams/state-class.md) | Ver 3.4 |
| 10 | Observer | Efeitos independentes | 4 históricos + 6 notificações no ciclo completo | [UML](diagrams/observer-class.md); `OrderEventPublisher` | Ver 3.5 |
| 11 | Integração dos cinco | Mostrar que trabalham juntos | Decorator → Strategy → State → Adapter → Observer em uma transação | [Sequência do pagamento](diagrams/payment-sequence.md) | "O pagamento usa os quatro padrões comportamentais e estruturais depois que o Decorator produziu o subtotal." |
| 12 | Demonstração prática | Provar funcionando | Fluxo da seção 4 | Navegador em `http://127.0.0.1:8082` | "Vou criar um pedido novo e percorrer todo o ciclo." |
| 13 | Testes e qualidade | Mostrar verificação | TDD, unitários, Feature, concorrência real, rollback, infraestrutura | Saída de `php artisan test` | "Os testes cobrem rollback e concorrência com dois processos reais." |
| 14 | Resultados | Números reais | Total de testes e asserções; Pint; ambientes dev e prod | [Validação](VALIDACAO_E_TESTES.md) | "Todos os testes passam; não medimos cobertura percentual." |
| 15 | Conclusão | Fechar | Aprendizados, limitações, trabalhos futuros opcionais | [Limitações](VALIDACAO_E_TESTES.md#limitações-conhecidas-e-classificação) | "Os padrões reduziram acoplamento; o sistema é uma demonstração, não um produto." |

## 3. Explicação oral de cada padrão (≈ 1 min)

### 3.1 Decorator
**Problema:** hambúrguer com qualquer combinação de bacon, queijo e ovo; com herança haveria uma classe por combinação. **Por que Decorator:** adiciona responsabilidades por composição, mantendo a interface. **Como:** `BaseMenuItem` é o produto; `BaconDecorator`/`CheeseDecorator`/`EggDecorator` embrulham um `MenuItem` e somam preço e descrição. **Onde ver:** `OrderService::create()` encadeia; hambúrguer + bacon + queijo = 2700. **Benefício:** novo extra sem tocar nas classes existentes.

### 3.2 Strategy
**Problema:** cada método de pagamento calcula diferente (PIX −5%, dinheiro com troco). **Por que Strategy:** um algoritmo por classe, intercambiáveis. **Como:** `PaymentStrategy` com três implementações; `PaymentCalculator` escolhe e delega, sem `switch` de regras. **Onde ver:** pagar R$ 27,00 por PIX: R$ 25,65. **Benefício:** regras isoladas e testáveis. Ela só calcula; a cobrança simulada é do Adapter.

### 3.3 Adapter
**Problema:** dois gateways legados simulados falam línguas diferentes: um recebe centavos e devolve array, o outro recebe `"25.65"` e devolve `"APPROVED|ref|25.65"`. **Por que Adapter:** o serviço só quer `PaymentGateway::charge(int): PaymentResult`. **Como:** `PixGatewayAdapter` e `CardGatewayAdapter` traduzem entrada e saída; dinheiro implementa o contrato direto. **Onde ver:** os arquivos dos adapters e a referência `SIMULATED-…` na tela (que ilustra, mas não prova, o padrão). **Benefício:** o serviço ignora formatos legados.

### 3.4 State
**Problema:** as ações válidas dependem do estado (não se paga um pedido cancelado). **Por que State:** cada estado define suas próprias saídas. **Como:** `OrderState` + seis classes; `Order::state()` reidrata do banco; o que não é permitido lança `InvalidOrderTransitionException`. **Onde ver:** os botões mudam a cada etapa; pedido entregue não tem ações. **Benefício:** regras de transição em um só lugar, terminais explícitos.

### 3.5 Observer
**Problema:** cada transição deve gerar histórico e avisos sem que o pedido saiba quem reage. **Por que Observer:** um-para-muitos desacoplado. **Como:** `OrderEventPublisher` notifica `HistoryObserver`, `CustomerNotificationObserver` e `KitchenNotificationObserver`, todos na mesma transação. **Onde ver:** detalhe do pedido entregue: 4 históricos e 6 notificações. **Benefício:** novo observer sem alterar o publisher.

## 4. Demonstração prática principal (≈ 4 min)

Use o ambiente de **desenvolvimento** (`http://127.0.0.1:8082`; ou a porta configurada em `DEV_PORT`) e crie um **pedido novo**.

| Etapa | Ação | O que mostrar | Padrão |
|---|---|---|---|
| 1. Cardápio | Abrir `/` | 3 produtos e preços | — |
| 2. Decorator | Hambúrguer + Bacon + Queijo extra → confirmar | Subtotal **R$ 27,00** e descrição "Hambúrguer + Bacon + Queijo extra"; explicar o encadeamento | Decorator |
| 3. Strategy | No detalhe, escolher **PIX** e confirmar | Desconto R$ 1,35 (5%) e total **R$ 25,65** | Strategy |
| 4. Adapter | Mostrar a referência `SIMULATED-PIX-…`; abrir `LegacyPixGateway`, `LegacyCardGateway` e um adapter | Contratos diferentes traduzidos para `PaymentResult` | Adapter |
| 5. State | **Iniciar preparo** → **Finalizar preparo** → **Entregar pedido** | Só a ação válida aparece a cada etapa | State |
| 6. Observer | Rolar o detalhe | **4 históricos** e **6 notificações** (4 cliente, 2 cozinha) | Observer |
| 7. Resultado | Abrir `/orders` | Pedido entregue, composição e pagamento preservados | — |

> A referência fictícia é apenas evidência de processamento simulado; use as classes e contratos reais para demonstrar o Adapter.

## 5. Demonstrações complementares

- **Dinheiro com troco:** pedido de R$ 27,00 pago com `30,00` → troco R$ 3,00.
- **Dinheiro insuficiente:** pagar com `10,00` → mensagem de erro, pedido continua pendente.
- **Cancelamento:** em um pedido pendente, **Cancelar pedido**; depois não há mais ações.
- **Batata frita:** o formulário não oferece complementos.
- **Pedido terminal:** em pedido entregue/cancelado, a seção de ações não mostra botões.
- **Rejeição de transição inválida (backend):** a interface não exibe botões inválidos, então demonstre o backend com um teste ou uma requisição controlada local:

```bash
docker compose exec app php artisan test --filter=test_direct_posts_are_rejected_even_when_the_button_is_not_visible
docker compose exec app php artisan test --filter=OrderStateMatrixTest
```

## 6. Arquivos de código a abrir

| Padrão | Arquivos (nessa ordem) | Teste representativo |
|---|---|---|
| Decorator | `app/Patterns/Decorator/MenuItem.php`, `BaseMenuItem.php`, `MenuItemDecorator.php`, `BaconDecorator.php`; `app/Services/OrderService.php` (método `create`) | `tests/Unit/Decorator/MenuItemDecoratorTest.php` |
| Strategy | `app/Patterns/Strategy/PaymentStrategy.php`, `PixPaymentStrategy.php`, `CashPaymentStrategy.php`, `PaymentCalculator.php` | `tests/Unit/Strategy/PixPaymentStrategyTest.php` |
| Adapter | `app/Patterns/Adapter/PaymentGateway.php`, `LegacyPixGateway.php`, `LegacyCardGateway.php`, `PixGatewayAdapter.php`, `CardGatewayAdapter.php` | `tests/Unit/Adapter/GatewayAdaptersTest.php` |
| State | `app/Patterns/State/OrderState.php`, `AbstractOrderState.php`, `PendingState.php`; `app/Models/Order.php` (`state`, `transitionTo`) | `tests/Unit/State/OrderStateMatrixTest.php` |
| Observer | `app/Patterns/Observer/OrderObserver.php`, `OrderEventPublisher.php`, `KitchenNotificationObserver.php`; `app/Providers/AppServiceProvider.php` | `tests/Feature/OrderObserverMatrixTest.php` |
| Integração | `app/Services/PaymentService.php` (método `pay`) | `tests/Feature/OrderTrackingFlowTest.php` |

Evite exibir arquivos inteiros: mostre o contrato, a classe concreta, o ponto de composição e o teste.

## 7. Pedidos existentes para demonstração complementar

No ambiente de desenvolvimento há pedidos de validações anteriores, úteis só para consulta (não os altere): **#10** (entregue, com 4 históricos e 6 notificações), **#11** (cancelado) e **#13** (entregue). Na produção local: **#1** (entregue), **#2** (cancelado) e **#3** (pendente). Pedidos anteriores à US07 (#1 a #9 do desenvolvimento) não têm histórico retroativo.

## 8. Plano alternativo

| Situação | O que fazer |
|---|---|
| Sem internet | Nada depende de rede: Bootstrap e CSS são locais, o pagamento é simulado e as imagens Docker já estão no host. Diagramas Mermaid são texto; se a visualização do GitHub não estiver disponível, use um editor que renderize Mermaid ou apresente o texto |
| Porta ocupada | Altere `DEV_PORT`/`PROD_PORT` (e `APP_URL`) e suba novamente; não interrompa outros serviços |
| Navegador com problema | Use outro navegador ou demonstre via `curl` em `127.0.0.1` e pelos testes (`php artisan test --filter=OrderTrackingFlowTest`) |
| Demonstração reduzida | Faça só o fluxo da seção 4 (PIX → ciclo completo → histórico e notificações) |
| Só código | Siga a tabela da seção 6 e execute `php artisan test --filter=<Padrão>` |

## 9. Comandos para mostrar os testes

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=Strategy
docker compose exec app php artisan test --filter=GatewayAdaptersTest
docker compose exec app php artisan test --filter=OrderStateMatrixTest
docker compose exec app php artisan test --filter=Observer
docker compose exec app php artisan test --filter=Concurrency
docker compose exec app vendor/bin/pint --test
```

Voltar: [índice](README.md) · Perguntas prováveis: [Perguntas e respostas](PERGUNTAS_E_RESPOSTAS.md)
