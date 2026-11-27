# UML — State (comportamental)

Código: `app/Patterns/State/` e `app/Models/Order.php`. Documentação: [Padrões de projeto — State](../PADROES_DE_PROJETO.md#4-state-comportamental).

| Participante GoF | Classe real |
|---|---|
| Context | `Order` (`state()` e `transitionTo()`) |
| State | `OrderState` (interface) |
| Classe auxiliar abstrata | `AbstractOrderState` (rejeita tudo por padrão) |
| Concrete States | `PendingState`, `PaidState`, `PreparingState`, `ReadyState`, `DeliveredState`, `CancelledState` |
| Valor persistido (não é estado GoF) | `OrderStatus` (enum) |

As setas tracejadas entre estados concretos indicam **qual estado cada operação devolve** (as cinco transições válidas). `DeliveredState` e `CancelledState` não sobrescrevem nenhuma operação.

```mermaid
classDiagram
    class Order {
        +OrderStatus status
        +state() OrderState
        +transitionTo(OrderState, OrderEventPublisher)
    }
    class OrderState {
        <<interface>>
        +status() OrderStatus
        +pay() OrderState
        +start() OrderState
        +ready() OrderState
        +deliver() OrderState
        +cancel() OrderState
        +canPay() bool
        +canStart() bool
        +canReady() bool
        +canDeliver() bool
        +canCancel() bool
    }
    class AbstractOrderState {
        <<abstract>>
        +pay() OrderState
        +start() OrderState
        +ready() OrderState
        +deliver() OrderState
        +cancel() OrderState
    }
    class PendingState {
        +pay() OrderState
        +cancel() OrderState
    }
    class PaidState {
        +start() OrderState
    }
    class PreparingState {
        +ready() OrderState
    }
    class ReadyState {
        +deliver() OrderState
    }
    class DeliveredState
    class CancelledState
    class OrderStatus {
        <<enumeration>>
    }

    OrderState <|.. AbstractOrderState
    AbstractOrderState <|-- PendingState
    AbstractOrderState <|-- PaidState
    AbstractOrderState <|-- PreparingState
    AbstractOrderState <|-- ReadyState
    AbstractOrderState <|-- DeliveredState
    AbstractOrderState <|-- CancelledState
    Order ..> OrderState : state() reidrata a partir de status
    OrderState ..> OrderStatus
    PendingState ..> PaidState : pay()
    PendingState ..> CancelledState : cancel()
    PaidState ..> PreparingState : start()
    PreparingState ..> ReadyState : ready()
    ReadyState ..> DeliveredState : deliver()
```

Voltar: [índice](../README.md)
