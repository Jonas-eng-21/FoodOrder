# Padrões de projeto do FoodOrder

O FoodOrder implementa **cinco padrões GoF**, todos participando do fluxo real de um pedido, cada um em seu subdiretório de `app/Patterns/`:

| # | Padrão | Categoria GoF | Diretório | Diagrama UML |
|---|---|---|---|---|
| 1 | Decorator | Estrutural | `app/Patterns/Decorator/` | [decorator-class](diagrams/decorator-class.md) |
| 2 | Strategy | Comportamental | `app/Patterns/Strategy/` | [strategy-class](diagrams/strategy-class.md) |
| 3 | Adapter | Estrutural | `app/Patterns/Adapter/` | [adapter-class](diagrams/adapter-class.md) |
| 4 | State | Comportamental | `app/Patterns/State/` | [state-class](diagrams/state-class.md) |
| 5 | Observer | Comportamental | `app/Patterns/Observer/` | [observer-class](diagrams/observer-class.md) |

Nenhum dos cinco é criacional. Padrões adicionais não foram implementados como objetivo acadêmico; recursos internos do Laravel (container, Eloquent, facades) não contam entre os cinco.

> A organização por padrão (um diretório para cada) é uma decisão acadêmica deliberada para tornar cada implementação fácil de localizar e explicar. Em um projeto de produção, essas classes poderiam estar distribuídas por domínio.

**Como os cinco se encontram no fluxo:** o **Decorator** produz o subtotal; o **Strategy** calcula o total conforme a forma de pagamento; o **Adapter** padroniza o gateway simulado; o **State** valida e define a transição; o **Observer** registra histórico e notificações. Veja [Integração dos cinco padrões](#integração-dos-cinco-padrões) e as [sequências](diagrams/payment-sequence.md).

Pagamentos são **exclusivamente simulados**: não há rede, dados de cartão, conta bancária nem chave PIX.

---

## 1. Decorator (estrutural)

### 1.1 Intenção e problema

**Intenção (GoF):** anexar responsabilidades adicionais a um objeto dinamicamente, oferecendo uma alternativa flexível à herança.

**Problema no FoodOrder:** um hambúrguer ou cachorro-quente aceita qualquer combinação de Bacon, Queijo extra e Ovo. Com herança, seria preciso uma subclasse por combinação (`BurgerComBacon`, `BurgerComBaconEQueijo`, …), e o número cresce exponencialmente a cada novo complemento (RN02).

**Contexto:** `OrderService::create()` monta o item e dele extrai subtotal e descrição do pedido.

### 1.2 Participantes e responsabilidades

| Participante GoF | Classe real | Responsabilidade |
|---|---|---|
| Component | `MenuItem` (interface) | Contrato comum: `priceCents(): int` e `description(): string` |
| Concrete Component | `BaseMenuItem` | O produto original: nome e preço base; rejeita preço negativo |
| Decorator | `MenuItemDecorator` (abstrata) | Implementa `MenuItem`, embrulha outro `MenuItem` (`$inner`) e **delega** por padrão |
| Concrete Decorator | `BaconDecorator` | Soma o preço do extra e acrescenta `" + Bacon"` |
| Concrete Decorator | `CheeseDecorator` | Idem, para Queijo extra |
| Concrete Decorator | `EggDecorator` | Idem, para Ovo |

**Classes auxiliares (não são participantes clássicos do Decorator):** `Extra` (objeto de valor: código, nome, preço em centavos, produtos aceitos) e `ExtraCatalog` (lê `config/foodorder.php`, fonte única dos extras). Elas fornecem os *dados* aos decorators; o padrão em si é a cadeia `MenuItem`.

Relacionamentos: decorators **realizam** `MenuItem` e **agregam** (referenciam) outro `MenuItem`; ver [diagrama UML](diagrams/decorator-class.md).

### 1.3 Código real

`app/Patterns/Decorator/MenuItemDecorator.php` — delega ao componente interno:

```php
abstract class MenuItemDecorator implements MenuItem
{
    public const SEPARATOR = ' + ';

    public function __construct(
        protected readonly MenuItem $inner,
        protected readonly Extra $extra,
    ) {}

    public function priceCents(): int
    {
        return $this->inner->priceCents();
    }

    public function description(): string
    {
        return $this->inner->description();
    }
}
```

`app/Patterns/Decorator/BaconDecorator.php` — acrescenta sua parte sobre o resultado delegado:

```php
final class BaconDecorator extends MenuItemDecorator
{
    public function priceCents(): int
    {
        return parent::priceCents() + $this->extra->priceCents;
    }

    public function description(): string
    {
        return parent::description().self::SEPARATOR.$this->extra->name;
    }
}
```

`app/Services/OrderService.php` — o encadeamento (ponto de composição):

```php
$item = new BaseMenuItem($product->name, $product->base_price_cents);

foreach ($extras as $extra) {
    $decorator = self::DECORATORS[$extra->code];
    $item = new $decorator($item, $extra);
}

return $this->persist($product, $item, $extras);
```

### 1.4 Exemplo concreto

```text
Hambúrguer ........ R$ 20,00   BaseMenuItem          priceCents() = 2000
+ Bacon ........... R$  4,00   BaconDecorator(base)  priceCents() = 2000 + 400  = 2400
+ Queijo extra .... R$  3,00   CheeseDecorator(...)  priceCents() = 2400 + 300  = 2700
Subtotal .......... R$ 27,00   description() = "Hambúrguer + Bacon + Queijo extra"
```

A chamada `priceCents()` no decorator externo (`CheeseDecorator`) chama `parent::priceCents()`, que delega ao `BaconDecorator`, que delega ao `BaseMenuItem`; os valores são somados na volta. O objeto original **não é modificado** (teste `test_decorating_does_not_modify_the_original_component`), e o mesmo componente pode ser decorado de formas independentes. O resultado é gravado como *snapshot* no pedido (`subtotal_cents = 2700`).

**Por que somar um array não seria Decorator:** `array_sum` de preços não tem objetos que compartilham o contrato `MenuItem`, não encapsula o componente interno, não permite empilhar novos comportamentos sem alterar quem soma e não deriva a descrição do mesmo mecanismo. No Decorator, o cliente (`OrderService`) trata o item decorado como qualquer `MenuItem`, sem saber quantas camadas existem.

### 1.5 Integração, benefícios e trade-offs

- **Integração:** o subtotal produzido aqui é o único valor usado nos cálculos seguintes (Strategy), nunca um preço vindo do navegador (RN09).
- **Benefícios:** sem explosão de subclasses; novo extra = um novo decorator + uma entrada em `config/foodorder.php` + mapeamento em `OrderService::DECORATORS`; o teste `test_every_catalog_extra_has_a_decorator` impede esquecer o mapeamento.
- **Trade-offs:** cada extra exige uma classe; os três decorators são muito parecidos entre si (a diferença é o dado, não o comportamento) — mantidos porque o propósito acadêmico é exibir o padrão. A ordem de aplicação é a do catálogo, não a da seleção; o preço independe da ordem (`test_composition_order_does_not_change_the_price`).
- **SOLID:** aberto/fechado (novo extra sem alterar o componente), responsabilidade única por decorator, substituição de Liskov (todo decorator é um `MenuItem`).

### 1.6 Evidências nos testes

| Teste | O que prova |
|---|---|
| `tests/Unit/Decorator/MenuItemDecoratorTest.php` (10) | Contribuição de cada decorator, encadeamento, delegação, não mutação do original, centavos inteiros e rejeição de preços negativos |
| `tests/Unit/Decorator/ExtraCatalogTest.php` (2) | Catálogo de extras e compatibilidade por produto |
| `tests/Feature/OrderServiceTest.php` | `test_subtotal_is_calculated_through_the_decorators` (7 casos, incluindo "burger bacon cheese"), snapshots, extras inválidos/duplicados/incompatíveis, produto inativo |

### 1.7 Como demonstrar na apresentação

1. Abrir `MenuItem.php`, `BaseMenuItem.php` e `MenuItemDecorator.php` (contrato, base, delegação).
2. Abrir o trecho de `OrderService::create()` (a cadeia).
3. No navegador: Hambúrguer + Bacon + Queijo extra → subtotal R$ 27,00 e descrição composta.
4. `docker compose exec app php artisan test --filter=MenuItemDecoratorTest`.

---

## 2. Strategy (comportamental)

### 2.1 Intenção e problema

**Intenção (GoF):** definir uma família de algoritmos, encapsular cada um e torná-los intercambiáveis, permitindo que o algoritmo varie independentemente de quem o usa.

**Problema no FoodOrder:** o total depende da forma de pagamento (RN04): PIX tem 5% de desconto com arredondamento específico; cartão mantém o subtotal; dinheiro mantém o subtotal, exige valor recebido suficiente e calcula troco. Concentrar tudo em um `switch` ou `if/else` no serviço tornaria cada novo método uma alteração em código já testado.

### 2.2 Participantes e responsabilidades

| Participante GoF | Classe real | Responsabilidade |
|---|---|---|
| Strategy | `PaymentStrategy` (interface) | `calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote` |
| Concrete Strategy | `PixPaymentStrategy` | `total = intdiv(subtotal * 95 + 50, 100)`; rejeita subtotal negativo e valores que estourariam o inteiro |
| Concrete Strategy | `CardPaymentStrategy` | Total = subtotal, sem desconto |
| Concrete Strategy | `CashPaymentStrategy` | Total = subtotal; exige valor recebido; calcula troco; rejeita ausente ou insuficiente |
| Context | `PaymentCalculator` | Recebe o mapa método→estratégia no construtor; seleciona e **apenas delega** |
| (resultado, não estratégia) | `PaymentQuote` | Objeto imutável com subtotal, total, desconto, recebido e troco; valida invariantes (`total + desconto = subtotal`) |

Quem usa o Context: `PaymentService`. A composição das estratégias está em `AppServiceProvider::register()`. Ver [UML](diagrams/strategy-class.md).

### 2.3 Código real

`app/Patterns/Strategy/PaymentStrategy.php`:

```php
interface PaymentStrategy
{
    public function calculate(int $subtotalCents, ?int $receivedCents): PaymentQuote;
}
```

`app/Patterns/Strategy/PaymentCalculator.php` (Context; sem `switch` de algoritmos):

```php
public function calculate(string $method, int $subtotalCents, ?int $receivedCents = null): PaymentQuote
{
    $strategy = $this->strategies[$method] ?? throw InvalidPaymentException::unsupportedMethod($method);

    return $strategy->calculate($subtotalCents, $receivedCents);
}
```

`app/Patterns/Strategy/PixPaymentStrategy.php` (algoritmo em inteiros, meio centavo para cima):

```php
$totalCents = intdiv($subtotalCents * 95 + 50, 100);

return new PaymentQuote($subtotalCents, $totalCents, $subtotalCents - $totalCents);
```

`app/Patterns/Strategy/CashPaymentStrategy.php` (troco):

```php
if ($receivedCents < $subtotalCents) {
    throw InvalidPaymentException::insufficientReceivedAmount();
}

return new PaymentQuote($subtotalCents, $subtotalCents, 0, $receivedCents, $receivedCents - $subtotalCents);
```

### 2.4 Exemplo concreto

Subtotal de 2700 centavos (Hambúrguer + Bacon + Queijo extra):

| Método | Total | Desconto | Recebido | Troco |
|---|---:|---:|---:|---:|
| PIX | 2565 | 135 | — | — |
| Cartão | 2700 | 0 | — | — |
| Dinheiro (recebido 3000) | 2700 | 0 | 3000 | 300 |

Conferência do PIX: `intdiv(2700 * 95 + 50, 100) = intdiv(256550, 100) = 2565`, desconto `2700 − 2565 = 135`. Os valores aparecem nos testes (`PixPaymentStrategyTest`, `CashPaymentStrategyTest`, `PaymentServiceTest`) e foram exercitados nos dois ambientes (PIX R$ 25,65).

Arredondamento (meio centavo para cima): com subtotal 2010, `2010 × 0,95 = 1909,5`, e o total é 1910 (`intdiv(2010 * 95 + 50, 100)`); os casos 2001 e 2011 cobrem os dois lados do meio centavo nos testes.

**Strategy não executa a cobrança.** Ela apenas determina as *condições* (total, desconto, troco). A cobrança simulada é feita pelo gateway, via Adapter (próxima seção): estratégia = *quanto e como se calcula*; gateway = *processar*.

### 2.5 Integração, benefícios e trade-offs

- **Integração:** `PaymentService::pay()` chama `PaymentCalculator::calculate()` com o `subtotal_cents` persistido (Decorator) e passa `totalCents` ao gateway. Calcular uma cotação não grava nada (`PaymentQuoteOrderTest`).
- **Benefícios:** cada algoritmo isolado e testável; novo método = nova estratégia + registro no provider (e o gateway correspondente); aritmética inteira, sem `float` (RN03).
- **Trade-offs:** o mapa de estratégias é montado em um único lugar (o provider); adicionar um método ainda exige tocar `PaymentMethod` (enum), o provider e o formulário. As estratégias são pequenas; o ganho é a separação, não a economia de linhas.
- **SOLID:** aberto/fechado, inversão de dependência (o Context depende da interface), responsabilidade única.

### 2.6 Evidências nos testes

| Teste | O que prova |
|---|---|
| `tests/Unit/Strategy/PixPaymentStrategyTest.php` (15) | 5%, meio centavo, zero, um centavo, negativo e overflow |
| `CardPaymentStrategyTest` (6), `CashPaymentStrategyTest` (9) | Cartão sem desconto; dinheiro: troco, exato, insuficiente, ausente, negativo |
| `PaymentCalculatorTest` (5) | Delegação pela interface, intercambialidade, método desconhecido, contêiner |
| `PaymentQuoteTest` (4) | Imutabilidade e invariantes |
| `tests/Feature/PaymentServiceTest.php`, `PaymentFlowTest.php` | Valores forjados ignorados, dinheiro insuficiente mantém `pending` sem chamar o gateway |

### 2.7 Como demonstrar

1. Abrir `PaymentStrategy.php`, `PixPaymentStrategy.php` e `PaymentCalculator.php`.
2. No navegador, pagar um pedido de R$ 27,00 por PIX → R$ 25,65 (desconto R$ 1,35); em dinheiro com R$ 30,00 → troco R$ 3,00.
3. `docker compose exec app php artisan test --filter=Strategy`.

---

## 3. Adapter (estrutural)

### 3.1 Intenção e problema

**Intenção (GoF):** converter a interface de uma classe na interface que os clientes esperam, permitindo que classes com interfaces incompatíveis trabalhem juntas.

**Problema no FoodOrder:** os dois "gateways legados" simulados têm contratos incompatíveis entre si:

| Gateway | Entrada | Saída |
|---|---|---|
| `LegacyPixGateway::sendPix(int $amountCents)` | inteiro em centavos | `array`: `['status' => 'CONFIRMED', 'txid' => 'SIMULATED-PIX-…', 'amount_cents' => 2565]` ou `['status' => 'DENIED', 'reason' => '…']` |
| `LegacyCardGateway::charge(string $amountDecimal)` | texto decimal `"27.00"` | `string`: `"APPROVED|SIMULATED-CARD-…|27.00"` ou `"DECLINED|motivo"` |

O `PaymentService` não deve conhecer esses formatos.

### 3.2 Participantes e responsabilidades

| Participante GoF | Classe real | Responsabilidade |
|---|---|---|
| Target | `PaymentGateway` | `charge(int $totalCents): PaymentResult` |
| Adaptee | `LegacyPixGateway` | API legada simulada de PIX (array) |
| Adaptee | `LegacyCardGateway` | API legada simulada de cartão (string) |
| Adapter | `PixGatewayAdapter` | Repassa centavos, traduz o array para `PaymentResult` |
| Adapter | `CardGatewayAdapter` | Converte centavos em decimal (`2565` → `"25.65"`, sem `float`), analisa a string e produz `PaymentResult` |
| Client | `PaymentService` | Usa somente `PaymentGateway` |

Fora do padrão: `PaymentResult` (resultado padronizado: aprovado + referência, ou rejeitado + erro; valida consistência) e `CashPaymentGateway` (implementa o Target diretamente). A seleção do gateway por método é feita em `AppServiceProvider::register()` (mapa `pix`/`card`/`cash` passado ao `PaymentService`). Ver [UML](diagrams/adapter-class.md).

### 3.3 Código real

`app/Patterns/Adapter/PaymentGateway.php` (Target):

```php
interface PaymentGateway
{
    public function charge(int $totalCents): PaymentResult;
}
```

`app/Patterns/Adapter/CardGatewayAdapter.php` (adaptação de argumento e de resposta):

```php
$amountDecimal = sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100);

$parts = explode('|', $this->legacy->charge($amountDecimal));

if (count($parts) === 3 && $parts[0] === 'APPROVED' && trim($parts[1]) !== '' && $parts[2] === $amountDecimal) {
    return PaymentResult::approved($parts[1]);
}
```

`app/Patterns/Adapter/PixGatewayAdapter.php`:

```php
$response = $this->legacy->sendPix($totalCents);
$status = $response['status'] ?? null;
// ...
if ($status === 'CONFIRMED' && is_string($txid) && trim($txid) !== '' && ($response['amount_cents'] ?? null) === $totalCents) {
    return PaymentResult::approved($txid);
}

return PaymentResult::rejected('Resposta inválida do gateway PIX simulado.');
```

Respostas inesperadas (status em minúsculas, txid vazio, valor divergente, texto lixo) **nunca** são tratadas como aprovação (testes `*_never_approves_unexpected_responses`).

### 3.4 Exemplo concreto

Para um total de 2565 centavos:

| Método | O que o adapter envia | Resposta legada (simulada) | `PaymentResult` |
|---|---|---|---|
| PIX | `sendPix(2565)` | `['status' => 'CONFIRMED', 'txid' => 'SIMULATED-PIX-AB12…', 'amount_cents' => 2565]` | `approved('SIMULATED-PIX-AB12…')` |
| Cartão | `charge("25.65")` | `"APPROVED|SIMULATED-CARD-CD34…|25.65"` | `approved('SIMULATED-CARD-CD34…')` |
| PIX recusado | `sendPix(2565)` | `['status' => 'DENIED', 'reason' => 'PIX recusado pelo simulador.']` | `rejected('PIX recusado pelo simulador.')` |

**Por que dinheiro não usa adapter:** `CashPaymentGateway` já implementa `PaymentGateway`; não há API incompatível a adaptar, então um adapter seria uma camada sem função (YAGNI). A referência fictícia (`SIMULATED-…`) mostrada na interface é evidência do processamento simulado, **não** a prova do padrão: a prova está nos adapters e nos contratos acima.

### 3.5 Integração, benefícios e trade-offs

- **Integração:** `PaymentService::charge()` busca `$this->gateways[$method]` e chama `charge($totalCents)`; qualquer exceção do gateway vira `PaymentRejectedException::gatewayFailure()` e o pedido continua `pending`.
- **Benefícios:** o serviço independe dos formatos legados; para trocar um simulador por outro basta outro adapter/implementação de `PaymentGateway` registrado no provider.
- **Trade-offs:** os "gateways legados" são simuladores criados para o exercício; **não são integrações financeiras reais**. Os adapters validam as respostas de forma estrita, o que os torna mais verbosos.
- **SOLID:** inversão de dependência e aberto/fechado (o serviço depende do Target); responsabilidade única (traduzir).

### 3.6 Evidências nos testes

`tests/Unit/Adapter/GatewayAdaptersTest.php` (30 execuções): `test_legacy_gateways_have_incompatible_contracts`, `test_pix_adapter_sends_cents_to_the_legacy_gateway_and_translates_approval`, `test_card_adapter_converts_cents_to_a_decimal_string_without_floats`, `test_pix_adapter_translates_rejection`, `test_card_adapter_translates_rejection`, `test_all_gateways_share_the_target_contract`, `test_gateways_do_not_use_the_network`; `PaymentResultTest` (3); no serviço: `PaymentServiceTest::test_gateway_rejection_keeps_the_order_pending_and_allows_a_retry` e `test_a_gateway_exception_keeps_the_order_pending`.

### 3.7 Como demonstrar

1. Abrir `PaymentGateway.php`, `LegacyPixGateway.php`, `LegacyCardGateway.php` (contratos diferentes) e `PixGatewayAdapter.php`/`CardGatewayAdapter.php`.
2. Pagar por PIX e por cartão na interface e mostrar as referências `SIMULATED-PIX-…`/`SIMULATED-CARD-…`.
3. `docker compose exec app php artisan test --filter=GatewayAdaptersTest`.

---

## 4. State (comportamental)

### 4.1 Intenção e problema

**Intenção (GoF):** permitir que um objeto altere seu comportamento quando seu estado interno muda, parecendo mudar de classe.

**Problema no FoodOrder:** as operações válidas dependem do estado do pedido (RN06/RN07): só se paga ou cancela um pedido pendente, só se inicia preparo de um pago, e entregue/cancelado são terminais. Espalhar `if ($status === ...)` por controllers e serviços duplicaria regras e permitiria esquecer casos.

### 4.2 Participantes e responsabilidades

| Participante GoF | Classe real | Responsabilidade |
|---|---|---|
| Context | `Order` | `state()` reidrata o objeto de estado a partir de `status`; `transitionTo()` persiste e publica |
| State | `OrderState` (interface) | `pay`, `start`, `ready`, `deliver`, `cancel` (devolvem o próximo estado ou lançam exceção) + `status()` e `can*()` |
| (auxiliar abstrata) | `AbstractOrderState` | Rejeita **todas** as operações com `InvalidOrderTransitionException`; os `can*()` apenas tentam a operação |
| Concrete State | `PendingState` | `pay()` → `PaidState`; `cancel()` → `CancelledState` |
| Concrete State | `PaidState` | `start()` → `PreparingState` |
| Concrete State | `PreparingState` | `ready()` → `ReadyState` |
| Concrete State | `ReadyState` | `deliver()` → `DeliveredState` |
| Concrete State | `DeliveredState`, `CancelledState` | Terminais: herdam a rejeição de tudo |

`OrderStatus` (enum) é apenas o **valor persistido** (`pending`, `paid`, …) com rótulos; ele não contém regras. As classes State não gravam nada: são operações puras. Quem abre a transação e persiste é `PaymentService`/`OrderLifecycleService`. Ver [UML](diagrams/state-class.md) e [diagrama de estados](diagrams/order-state.md).

### 4.3 Código real

`app/Models/Order.php` — reidratação e transição:

```php
public function state(): OrderState
{
    return match ($this->status) {
        OrderStatus::Pending => new PendingState,
        OrderStatus::Paid => new PaidState,
        OrderStatus::Preparing => new PreparingState,
        OrderStatus::Ready => new ReadyState,
        OrderStatus::Delivered => new DeliveredState,
        OrderStatus::Cancelled => new CancelledState,
    };
}

public function transitionTo(OrderState $state, OrderEventPublisher $publisher): void
{
    $previous = $this->status;
    $this->status = $state->status();
    $this->save();

    $publisher->notify(new OrderStateChanged($this->id, $previous, $this->status));
}
```

`app/Patterns/State/PendingState.php`:

```php
final class PendingState extends AbstractOrderState
{
    public function status(): OrderStatus { return OrderStatus::Pending; }
    public function pay(): OrderState { return new PaidState; }
    public function cancel(): OrderState { return new CancelledState; }
}
```

`app/Patterns/State/AbstractOrderState.php` — rejeição padrão:

```php
public function start(): OrderState
{
    throw InvalidOrderTransitionException::notAllowed('start', $this->status());
}
```

`app/Services/OrderLifecycleService.php` — uso (relê o pedido, pede a operação ao estado e aplica):

```php
$order = Order::query()->findOrFail($order->getKey());
$order->transitionTo($operation($order->state()), $this->events);
```

(O `match` em `Order::state()` é a única seleção por `status`: é a *reidratação* do estado persistido, não regra de negócio; todas as regras vivem nas classes de estado.)

### 4.4 Matriz das 30 combinações

| Estado ↓ / operação → | `pay` | `start` | `ready` | `deliver` | `cancel` |
|---|---|---|---|---|---|
| `pending` | `paid` | Rejeitar | Rejeitar | Rejeitar | `cancelled` |
| `paid` | Rejeitar | `preparing` | Rejeitar | Rejeitar | Rejeitar |
| `preparing` | Rejeitar | Rejeitar | `ready` | Rejeitar | Rejeitar |
| `ready` | Rejeitar | Rejeitar | Rejeitar | `delivered` | Rejeitar |
| `delivered` | Rejeitar | Rejeitar | Rejeitar | Rejeitar | Rejeitar |
| `cancelled` | Rejeitar | Rejeitar | Rejeitar | Rejeitar | Rejeitar |

São **5 transições válidas** e **25 combinações inválidas**. "Rejeitar" significa `InvalidOrderTransitionException`, sem efeitos colaterais (nenhum dado é alterado e nenhum evento é publicado). Isso impede, por exemplo, pagar depois de cancelar ou cancelar um pedido já pago.

### 4.5 Por que isso é diferente de enum + `switch`

Com um enum e um `switch` central, **toda** operação precisaria de um `switch` sobre todos os estados (ou um único switch gigante), e adicionar um estado obrigaria a revisar cada um. Aqui cada estado conhece apenas as **suas** saídas; o que não é declarado é rejeitado por herança de `AbstractOrderState`. O enum continua existindo, mas só como valor persistido.

**State × Strategy:** estruturalmente são parecidos (interface + implementações trocáveis + delegação). A diferença é a **intenção**: no Strategy, o *cliente escolhe* o algoritmo (o método de pagamento) e ele não muda sozinho; no State, o **próprio contexto muda de estado** à medida que a operação é aplicada, e cada estado devolve o próximo estado.

### 4.6 Integração, benefícios e trade-offs

- **Integração:** `PaymentService` pergunta `state()->pay()` **antes** de calcular e cobrar (assim um pedido já pago ou cancelado nunca chega ao gateway); `OrderLifecycleService` aplica `start/ready/deliver/cancel`; a view usa `can*()` só para esconder botões inválidos (o backend rejeita de qualquer forma).
- **Benefícios:** regras de transição localizadas; terminais explícitos; extensível.
- **Trade-offs:** seis classes pequenas para uma máquina simples; a reidratação por `match` precisa ser mantida em sincronia com o enum (o teste `OrderStateRehydrationTest` cobre os seis).
- **SOLID:** aberto/fechado, responsabilidade única e substituição de Liskov.

### 4.7 Evidências nos testes

| Teste | O que prova |
|---|---|
| `tests/Unit/State/OrderStateMatrixTest.php` | `test_the_matrix_has_thirty_combinations_with_five_valid_transitions`, uma execução por combinação, `test_terminal_states_reject_every_operation`, `test_the_happy_path_walks_through_all_states_in_order` |
| `tests/Unit/State/OrderStateTest.php` | Reidratação, pagamento repetido e ausência de efeitos colaterais na rejeição |
| `tests/Feature/OrderStateRehydrationTest.php` | Reidratação dos seis estados a partir do banco |
| `tests/Feature/OrderLifecycleServiceTest.php`, `OrderLifecycleFlowTest.php` | Ciclo completo, repetição rejeitada, POST direto rejeitado mesmo sem o botão, campos forjados ignorados |
| `tests/Feature/OrderLifecycleConcurrencyTest.php` | Pagamento × cancelamento concorrentes e transição repetida aplicada uma só vez |

### 4.8 Como demonstrar

1. Abrir `OrderState.php`, `AbstractOrderState.php`, `PendingState.php` e o trecho `state()`/`transitionTo()` de `Order.php`.
2. No navegador: pagar → **Iniciar preparo** → **Finalizar preparo** → **Entregar pedido**, mostrando que as ações exibidas mudam; em um pedido entregue não há ações.
3. Para mostrar a proteção do backend: `docker compose exec app php artisan test --filter=test_direct_posts_are_rejected_even_when_the_button_is_not_visible`.

---

## 5. Observer (comportamental)

### 5.1 Intenção e problema

**Intenção (GoF):** definir uma dependência um-para-muitos entre objetos, de modo que, quando um objeto muda de estado, todos os seus dependentes sejam notificados e atualizados automaticamente.

**Problema no FoodOrder:** cada transição válida deve gerar efeitos independentes (histórico, aviso ao cliente, aviso à cozinha — RN08) sem que `Order` ou os serviços conheçam quem reage.

### 5.2 Participantes e responsabilidades

| Participante GoF | Classe real | Responsabilidade |
|---|---|---|
| Subject | `OrderEventPublisher` | `subscribe`, `unsubscribe`, `notify`; guarda uma entrada por instância (`spl_object_id`) e notifica em ordem de inscrição |
| Observer | `OrderObserver` (interface) | `update(OrderStateChanged $event): void` |
| Concrete Observer | `HistoryObserver` | Grava em `order_histories` (estado anterior → novo) |
| Concrete Observer | `CustomerNotificationObserver` | Grava aviso para o cliente em **toda** transição |
| Concrete Observer | `KitchenNotificationObserver` | Grava aviso para a cozinha **somente** quando o novo estado é `paid` ou `ready` (a regra é do próprio observer) |
| (evento) | `OrderStateChanged` | Objeto imutável: `orderId`, `previousStatus`, `newStatus` e `eventKey()` (ex.: `paid_to_preparing`) |

O publisher não conhece as classes concretas; elas são inscritas em `AppServiceProvider` (singleton compartilhado). Ver [UML](diagrams/observer-class.md).

### 5.3 Código real

`app/Patterns/Observer/OrderEventPublisher.php`:

```php
public function subscribe(OrderObserver $observer): void
{
    $this->observers[spl_object_id($observer)] = $observer;
}

public function notify(OrderStateChanged $event): void
{
    foreach ($this->observers as $observer) {
        $observer->update($event);
    }
}
```

`app/Providers/AppServiceProvider.php` — registro pelo contêiner do Laravel (ponto de composição):

```php
$this->app->singleton(OrderEventPublisher::class, function ($app): OrderEventPublisher {
    $publisher = new OrderEventPublisher;
    $publisher->subscribe(new HistoryObserver);
    $publisher->subscribe(new CustomerNotificationObserver);
    $publisher->subscribe(new KitchenNotificationObserver);

    return $publisher;
});
```

`app/Patterns/Observer/KitchenNotificationObserver.php` — o observer decide se age:

```php
$message = match ($event->newStatus) {
    OrderStatus::Paid => 'Novo pedido pago aguardando preparação.',
    OrderStatus::Ready => 'Pedido finalizado e pronto para entrega.',
    default => null,
};
```

### 5.4 Matriz de efeitos

| Transição | Histórico | Cliente | Cozinha |
|---|---:|---:|---:|
| `pending → paid` | 1 | 1 | 1 |
| `paid → preparing` | 1 | 1 | 0 |
| `preparing → ready` | 1 | 1 | 1 |
| `ready → delivered` | 1 | 1 | 0 |
| `pending → cancelled` | 1 | 1 | 0 |

**Ciclo completo** (pagar → iniciar → finalizar → entregar): **4 históricos**, **4 avisos de cliente**, **2 avisos de cozinha** = **6 notificações** (teste `test_the_full_cycle_records_four_histories_and_six_notifications_in_order`). Criar o pedido, pagamento rejeitado e operação inválida **não** geram nada (`OrderObserverNoEffectTest`).

### 5.5 Execução síncrona, transação e falhas

- **Síncrono:** `notify()` executa os observers na mesma requisição. Não há filas, RabbitMQ ou Kafka porque o MVP é um monólito local de uma instância (KISS/YAGNI): as gravações precisam ser **atômicas** com a mudança de estado, e uma fila exigiria infraestrutura e tratamento de entrega/duplicidade que o escopo não pede.
- **Mesma transação:** `Order::transitionTo()` é chamado dentro da transação aberta pelo serviço (`BEGIN IMMEDIATE`); estado, pagamento (quando há), histórico e notificações são confirmados juntos.
- **Falha de observer:** a exceção **propaga**, os observers seguintes não executam e a transação é revertida: nada do pagamento/estado/histórico permanece (`OrderObserverRollbackTest`, `OrderLifecycleConcurrencyTest::test_a_failing_observer_rolls_everything_back_on_a_real_database_file`). A operação pode ser repetida depois.
- **Sem efeitos duplicados:** o `UNIQUE (order_id, new_status)` em `order_histories` e o `UNIQUE (order_id, event_key, audience)` em `notifications` garantem, no banco, que um mesmo efeito não é gravado duas vezes; no publisher, inscrever a **mesma instância** duas vezes não duplica a chamada (`test_subscribing_the_same_instance_twice_does_not_duplicate_calls`). Duas instâncias distintas da mesma classe são ambas notificadas (comportamento documentado em teste).
- **Remoção:** `unsubscribe($observer)` remove a instância (`test_an_unsubscribed_observer_stops_receiving_events`).
- **Novo observer sem alterar o mecanismo:** criar uma classe que implemente `OrderObserver` e acrescentar uma linha `subscribe()` no provider. `OrderEventPublisher`, `Order` e os serviços permanecem intactos.
- **Observer × mensageria distribuída:** aqui o Subject chama os observers em memória, no mesmo processo e na mesma transação; um broker desacoplaria processos e tempo, com entrega assíncrona e nova classe de falhas — fora do escopo.
- **Limite:** as "notificações" são registros internos no SQLite; **não há envio real** por e-mail, WhatsApp ou qualquer canal. Pedidos criados antes da US07 não têm histórico retroativo.

### 5.6 Integração, benefícios e trade-offs

- **Integração:** os dois serviços que alteram estado (`PaymentService`, `OrderLifecycleService`) chamam `Order::transitionTo()`, o ponto único de publicação (um evento por transição).
- **Benefícios:** desacoplamento, extensibilidade, atomicidade, efeitos testáveis isoladamente.
- **Trade-offs:** execução síncrona acopla o tempo de resposta aos observers e uma falha cancela a operação inteira (escolha deliberada pela consistência).
- **SOLID:** inversão de dependência (o Subject depende da interface), aberto/fechado.

### 5.7 Evidências nos testes

`tests/Unit/Observer/OrderEventPublisherTest.php` (10: inscrição, ordem, remoção, duplicidade, exceções); `tests/Feature/OrderObserverMatrixTest.php` (matriz e ciclo completo, inclusive via HTTP); `OrderObserverIntegrityTest.php` (contêiner, unicidades, chaves estrangeiras); `OrderObserverNoEffectTest.php` e `OrderObserverRollbackTest.php`; `OrderLifecycleConcurrencyTest.php`.

### 5.8 Como demonstrar

1. Abrir `OrderObserver.php`, `OrderEventPublisher.php`, um observer concreto e o trecho do `AppServiceProvider`.
2. Percorrer o ciclo de um pedido e mostrar, no detalhe, 4 históricos e as notificações (cliente e cozinha em listas separadas), totalizando 6.
3. `docker compose exec app php artisan test --filter=Observer`.

---

## Integração dos cinco padrões

```text
Decorator  →  subtotal_cents do pedido (BaseMenuItem + decorators)         [OrderService]
Strategy   →  PaymentQuote (total, desconto, troco) sobre o subtotal       [PaymentCalculator]
State      →  pode pagar? qual é o próximo estado?                          [Order::state()->pay()]
Adapter    →  cobrança simulada padronizada em PaymentResult                [PaymentGateway]
Observer   →  histórico + avisos a cada transição                          [OrderEventPublisher]
```

Tudo ocorre em `PaymentService::pay()` dentro de **uma transação**: se qualquer etapa falha, nada é persistido. Ver [sequência do pagamento](diagrams/payment-sequence.md).

## Diferenças que costumam ser perguntadas

| Comparação | Resposta curta |
|---|---|
| Strategy × Adapter | Strategy troca *algoritmos* que o cliente escolhe (como calcular); Adapter *converte interfaces* incompatíveis (como falar com o gateway). No FoodOrder o Strategy calcula o total e o Adapter processa a cobrança simulada. |
| Decorator × Adapter | Decorator mantém a **mesma interface** e acrescenta comportamento; Adapter **muda a interface** para que algo incompatível seja usável. |
| State × Strategy | Mesma forma, intenção diferente: State muda sozinho conforme o ciclo de vida; Strategy é escolhido de fora e não evolui. |
| Adapter × Facade (pergunta comum) | Facade simplifica um subsistema inteiro com uma interface nova; Adapter faz uma interface existente caber em outra esperada. Os adapters do FoodOrder encapsulam *uma* classe legada cada. |

Voltar: [índice da documentação](README.md)
