# UML — Observer (comportamental)

Código: `app/Patterns/Observer/`. Documentação: [Padrões de projeto — Observer](../PADROES_DE_PROJETO.md#5-observer-comportamental).

| Participante GoF | Classe real |
|---|---|
| Subject | `OrderEventPublisher` |
| Observer | `OrderObserver` (interface) |
| Concrete Observers | `HistoryObserver`, `CustomerNotificationObserver`, `KitchenNotificationObserver` |
| Evento (objeto de notificação) | `OrderStateChanged` (imutável) |

O publisher guarda as referências dos observadores (agregação) e **não conhece as classes concretas**: quem os inscreve é `AppServiceProvider`. `Order::transitionTo()` recebe o publisher como parâmetro e publica um evento por transição.

```mermaid
classDiagram
    class OrderEventPublisher {
        -array observers
        +subscribe(OrderObserver)
        +unsubscribe(OrderObserver)
        +notify(OrderStateChanged)
        +observers() array
    }
    class OrderObserver {
        <<interface>>
        +update(OrderStateChanged)
    }
    class HistoryObserver {
        +update(OrderStateChanged)
    }
    class CustomerNotificationObserver {
        +update(OrderStateChanged)
    }
    class KitchenNotificationObserver {
        +update(OrderStateChanged)
    }
    class OrderStateChanged {
        <<immutable>>
        +int orderId
        +OrderStatus previousStatus
        +OrderStatus newStatus
        +eventKey() string
    }
    class Order {
        +transitionTo(OrderState, OrderEventPublisher)
    }
    class AppServiceProvider {
        +register()
    }

    OrderObserver <|.. HistoryObserver
    OrderObserver <|.. CustomerNotificationObserver
    OrderObserver <|.. KitchenNotificationObserver
    OrderEventPublisher o-- "0..*" OrderObserver : observers
    OrderEventPublisher ..> OrderStateChanged : notify
    OrderObserver ..> OrderStateChanged : update
    Order ..> OrderEventPublisher : publica
    Order ..> OrderStateChanged : cria
    AppServiceProvider ..> OrderEventPublisher : subscribe dos 3 observers
```

Voltar: [índice](../README.md)
