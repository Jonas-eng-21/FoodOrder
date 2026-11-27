# C4 — Nível 3: componentes da aplicação Laravel

Componentes reais de `app/`. Os cinco padrões GoF estão em `app/Patterns/`, um subdiretório por padrão. As setas indicam dependência de uso.

```mermaid
flowchart TB
    subgraph http [Camada HTTP]
        R[routes/web.php]
        C[Controllers<br/>Menu · Order · Payment · OrderLifecycle]
        FR[Form Requests<br/>StoreOrderRequest · PayOrderRequest]
        V[Views Blade]
    end

    subgraph svc [Serviços]
        OS[OrderService]
        PS[PaymentService]
        LS[OrderLifecycleService]
    end

    subgraph pat [app/Patterns]
        DEC[Decorator<br/>MenuItem · BaseMenuItem · decorators · ExtraCatalog]
        STR[Strategy<br/>PaymentCalculator · Pix/Card/Cash]
        ADA[Adapter<br/>PaymentGateway · adapters · gateways legados]
        STA[State<br/>OrderState · seis estados]
        OBS[Observer<br/>OrderEventPublisher · 3 observers]
    end

    subgraph dom [Modelos e persistência]
        M[Models Eloquent<br/>Product · Order · Payment · OrderHistory · Notification]
        DB[(SQLite)]
    end

    R --> C
    C --> FR
    C --> V
    C --> OS
    C --> PS
    C --> LS
    OS --> DEC
    OS --> M
    PS --> STR
    PS --> ADA
    PS --> M
    LS --> M
    M -- "Order::state()" --> STA
    M -- "Order::transitionTo()" --> OBS
    PS --> OBS
    LS --> OBS
    OBS --> M
    M --> DB
```

A composição do `OrderEventPublisher`, do `PaymentCalculator` e dos gateways do `PaymentService` é feita em `app/Providers/AppServiceProvider.php`. Detalhes de cada padrão: [Padrões de projeto](../PADROES_DE_PROJETO.md).

Voltar: [C4 — contêineres](c4-container.md)
