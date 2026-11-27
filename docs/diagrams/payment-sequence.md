# Sequência — pagamento (Strategy + Adapter + State + Observer)

Fluxo real de `POST /orders/{order}/pay` (`PaymentController::store` → `PaymentService::pay`). Utiliza o subtotal persistido, produzido antes pelo **Decorator**. Toda a operação ocorre em **uma transação** SQLite (`BEGIN IMMEDIATE`, configurado em `config/database.php`). A ordem é deliberada: o State valida antes de qualquer cálculo ou cobrança simulada, e o gateway é chamado antes de gravar o pagamento.

```mermaid
sequenceDiagram
    actor Op as Operador (navegador)
    participant C as PaymentController
    participant FR as PayOrderRequest
    participant PS as PaymentService
    participant DB as SQLite (transação IMMEDIATE)
    participant O as Order
    participant ST as State atual
    participant PC as PaymentCalculator
    participant S as Strategy (Pix/Card/Cash)
    participant G as Gateway (Adapter ou Cash)
    participant L as Gateway legado
    participant EP as OrderEventPublisher
    participant OB as Observers (histórico, cliente, cozinha)

    Op->>C: POST /orders/{id}/pay (method, received_amount)
    C->>FR: validar método e valor recebido (regex em reais)
    alt entrada inválida
        FR-->>Op: 302 com erros (nada gravado)
    end
    C->>PS: pay(order, method, receivedCents)
    PS->>DB: BEGIN IMMEDIATE
    PS->>O: findOrFail(id) — relê o pedido dentro da transação
    PS->>O: state()
    O-->>PS: estado reidratado a partir de status
    PS->>ST: pay()
    alt estado não permite pagamento
        ST-->>PS: InvalidOrderTransitionException
        PS->>DB: ROLLBACK
        PS-->>Op: erro (pedido inalterado, gateway não chamado)
    end
    ST-->>PS: PaidState (próximo estado)
    PS->>PC: calculate(method, subtotalCents, receivedCents)
    PC->>S: calculate(subtotalCents, receivedCents)
    S-->>PS: PaymentQuote (total, desconto, recebido, troco)
    Note over PS,S: dinheiro insuficiente ou ausente: InvalidPaymentException, ROLLBACK, gateway não chamado
    PS->>G: charge(totalCents)
    opt PIX ou cartão
        G->>L: sendPix(int) ou charge(string decimal)
        L-->>G: array ou string no formato legado
    end
    G-->>PS: PaymentResult (aprovado + referência SIMULATED-... ou rejeitado)
    alt pagamento rejeitado
        PS->>DB: ROLLBACK
        PS-->>Op: PaymentRejectedException (pedido segue pending, nova tentativa permitida)
    end
    PS->>DB: INSERT payment (UNIQUE order_id)
    PS->>O: transitionTo(PaidState, publisher)
    O->>DB: UPDATE orders SET status = paid
    O->>EP: notify(OrderStateChanged pending→paid)
    EP->>OB: update(event) em ordem de inscrição
    OB->>DB: INSERT order_histories, notifications (cliente e cozinha)
    alt falha em qualquer observer
        OB-->>PS: exceção propaga
        PS->>DB: ROLLBACK (pagamento, estado, histórico e notificações)
    end
    PS->>DB: COMMIT
    PS-->>C: Payment
    C-->>Op: 302 para o detalhe ("Pagamento simulado aprovado.")
```

Voltar: [índice](../README.md) · Padrões: [Strategy](../PADROES_DE_PROJETO.md#2-strategy-comportamental), [Adapter](../PADROES_DE_PROJETO.md#3-adapter-estrutural), [State](../PADROES_DE_PROJETO.md#4-state-comportamental), [Observer](../PADROES_DE_PROJETO.md#5-observer-comportamental)
