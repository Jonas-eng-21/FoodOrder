# Sequência — ciclo de vida (exemplo `preparing → ready`)

Fluxo real de `POST /orders/{order}/ready` (`OrderLifecycleController::ready` → `OrderLifecycleService::ready`). As demais ações (`start`, `deliver`, `cancel`) seguem exatamente o mesmo caminho, trocando apenas a operação do State.

```mermaid
sequenceDiagram
    actor Op as Operador (navegador)
    participant C as OrderLifecycleController
    participant LS as OrderLifecycleService
    participant DB as SQLite (transação IMMEDIATE)
    participant O as Order
    participant ST as PreparingState
    participant EP as OrderEventPublisher
    participant OB as Observers

    Op->>C: POST /orders/{id}/ready
    C->>LS: ready(order)
    LS->>DB: BEGIN IMMEDIATE
    LS->>O: findOrFail(id) — relê o pedido (estado persistido vence instância antiga)
    LS->>O: state()
    O-->>LS: PreparingState (reidratado de status = preparing)
    LS->>ST: ready()
    alt estado atual não permite
        ST-->>LS: InvalidOrderTransitionException
        LS->>DB: ROLLBACK
        C-->>Op: 302 com mensagem de erro (nada alterado, nenhum evento)
    end
    ST-->>LS: ReadyState
    LS->>O: transitionTo(ReadyState, publisher)
    O->>DB: UPDATE orders SET status = ready
    O->>EP: notify(OrderStateChanged preparing→ready)
    EP->>OB: update(event)
    OB->>DB: INSERT histórico + aviso ao cliente + aviso à cozinha
    alt falha de observer
        OB-->>LS: exceção
        LS->>DB: ROLLBACK (estado volta a preparing, sem registros novos)
    end
    LS->>DB: COMMIT
    C-->>Op: 302 para o detalhe ("Preparo finalizado: o pedido está pronto.")
```

Voltar: [índice](../README.md) · Padrões: [State](../PADROES_DE_PROJETO.md#4-state-comportamental), [Observer](../PADROES_DE_PROJETO.md#5-observer-comportamental)
