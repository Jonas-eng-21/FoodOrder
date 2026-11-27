# UML — Strategy (comportamental)

Código: `app/Patterns/Strategy/`. Documentação: [Padrões de projeto — Strategy](../PADROES_DE_PROJETO.md#2-strategy-comportamental).

| Participante GoF | Classe real |
|---|---|
| Strategy | `PaymentStrategy` (interface) |
| Concrete Strategies | `PixPaymentStrategy`, `CardPaymentStrategy`, `CashPaymentStrategy` |
| Context | `PaymentCalculator` |
| Objeto de resultado (não é estratégia) | `PaymentQuote` |
| Cliente do Context | `PaymentService` |

O `PaymentCalculator` recebe o mapa `método → estratégia` no construtor (montado em `AppServiceProvider`) e apenas delega: agregação de estratégias que existem independentemente dele.

```mermaid
classDiagram
    class PaymentStrategy {
        <<interface>>
        +calculate(int subtotalCents, int receivedCents) PaymentQuote
    }
    class PixPaymentStrategy {
        +calculate(int, int) PaymentQuote
    }
    class CardPaymentStrategy {
        +calculate(int, int) PaymentQuote
    }
    class CashPaymentStrategy {
        +calculate(int, int) PaymentQuote
    }
    class PaymentCalculator {
        -array strategies
        +calculate(string method, int subtotalCents, int receivedCents) PaymentQuote
    }
    class PaymentQuote {
        <<immutable>>
        +int subtotalCents
        +int totalCents
        +int discountCents
        +int receivedCents
        +int changeCents
    }
    class PaymentService {
        +pay(Order, PaymentMethod, int receivedCents) Payment
    }

    PaymentStrategy <|.. PixPaymentStrategy
    PaymentStrategy <|.. CardPaymentStrategy
    PaymentStrategy <|.. CashPaymentStrategy
    PaymentCalculator o-- "3" PaymentStrategy : strategies por método
    PaymentStrategy ..> PaymentQuote : cria
    PaymentCalculator ..> PaymentQuote : devolve
    PaymentService --> PaymentCalculator
```

Voltar: [índice](../README.md)
