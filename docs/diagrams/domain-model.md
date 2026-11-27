# Modelo de domínio

Entidades persistidas e os conceitos de domínio que as cercam. Os estados e os extras não são entidades persistidas independentes: o estado é uma coluna (`orders.status`) e os extras são um snapshot JSON em `orders.extras_snapshot`. Detalhes de tabelas: [Modelagem de dados](../MODELAGEM_DE_DADOS.md).

```mermaid
classDiagram
    class Product {
        +int id
        +string code
        +string name
        +int base_price_cents
        +bool active
    }
    class Order {
        +int id
        +string product_name_snapshot
        +array extras_snapshot
        +int subtotal_cents
        +OrderStatus status
        +state() OrderState
        +transitionTo(OrderState, OrderEventPublisher)
    }
    class Payment {
        +PaymentMethod method
        +int total_cents
        +int received_cents
        +int change_cents
        +string simulated_reference
    }
    class OrderHistory {
        +OrderStatus previous_status
        +OrderStatus new_status
    }
    class Notification {
        +string event_key
        +NotificationAudience audience
        +string message
    }
    class OrderStatus {
        <<enumeration>>
        pending
        paid
        preparing
        ready
        delivered
        cancelled
    }
    class PaymentMethod {
        <<enumeration>>
        pix
        card
        cash
    }
    class NotificationAudience {
        <<enumeration>>
        customer
        kitchen
    }

    Product "1" --> "0..*" Order : produto de origem
    Order "1" --> "0..1" Payment
    Order "1" --> "0..*" OrderHistory
    Order "1" --> "0..*" Notification
    Order ..> OrderStatus
    Payment ..> PaymentMethod
    Notification ..> NotificationAudience
```

Voltar: [índice](../README.md)
