# UML — Adapter (estrutural)

Código: `app/Patterns/Adapter/`. Documentação: [Padrões de projeto — Adapter](../PADROES_DE_PROJETO.md#3-adapter-estrutural).

| Participante GoF | Classe real |
|---|---|
| Target | `PaymentGateway` (interface) |
| Adaptees | `LegacyPixGateway` (`sendPix(int): array`), `LegacyCardGateway` (`charge(string): string`) |
| Adapters | `PixGatewayAdapter`, `CardGatewayAdapter` |
| Client | `PaymentService` |
| Fora do padrão | `CashPaymentGateway` (implementa o Target diretamente), `PaymentResult` (resultado padronizado) |

Cada adapter guarda uma referência ao adaptee, injetada no construtor (associação). Os gateways legados são simuladores locais: não há chamadas de rede.

```mermaid
classDiagram
    class PaymentGateway {
        <<interface>>
        +charge(int totalCents) PaymentResult
    }
    class PixGatewayAdapter {
        -LegacyPixGateway legacy
        +charge(int totalCents) PaymentResult
    }
    class CardGatewayAdapter {
        -LegacyCardGateway legacy
        +charge(int totalCents) PaymentResult
    }
    class CashPaymentGateway {
        +charge(int totalCents) PaymentResult
    }
    class LegacyPixGateway {
        <<adaptee>>
        +sendPix(int amountCents) array
    }
    class LegacyCardGateway {
        <<adaptee>>
        +charge(string amountDecimal) string
    }
    class PaymentResult {
        <<immutable>>
        +bool approved
        +string reference
        +string error
        +approved(string) PaymentResult
        +rejected(string) PaymentResult
    }
    class PaymentService {
        -array gateways
        +pay(Order, PaymentMethod, int) Payment
    }

    PaymentGateway <|.. PixGatewayAdapter
    PaymentGateway <|.. CardGatewayAdapter
    PaymentGateway <|.. CashPaymentGateway
    PixGatewayAdapter --> "1" LegacyPixGateway : adapta
    CardGatewayAdapter --> "1" LegacyCardGateway : adapta
    PaymentGateway ..> PaymentResult : devolve
    PaymentService o-- "3" PaymentGateway : gateways por método
```

Voltar: [índice](../README.md)
