# Sequência — criação de pedido (Decorator)

Fluxo real de `POST /orders` (`OrderController::store` → `OrderService::create`). O subtotal e a descrição vêm da cadeia de decorators; preço e estado enviados pelo navegador nunca são usados (RN09). Os extras são aplicados na **ordem do catálogo** (bacon, queijo, ovo), independentemente da ordem de marcação. Nenhum evento é publicado: criar o pedido não é uma transição (RN08).

```mermaid
sequenceDiagram
    actor Op as Operador (navegador)
    participant C as OrderController
    participant FR as StoreOrderRequest
    participant OS as OrderService
    participant CAT as ExtraCatalog
    participant D as BaseMenuItem + Decorators
    participant DB as SQLite (Order)

    Op->>C: POST /orders (product_id, extras[], _token)
    C->>FR: validar (CSRF + regras)
    FR->>FR: produto existe e está ativo? extras conhecidos e distintos?
    FR->>CAT: extras permitidos para o produto
    alt dados inválidos
        FR-->>Op: 302 de volta ao formulário com erros (nada gravado)
    end
    C->>OS: create(Product, extraCodes)
    OS->>OS: produto ativo? (InvalidOrderException se não)
    OS->>CAT: allowedFor(product.code) e find(code)
    CAT-->>OS: Extra selecionados
    OS->>D: new BaseMenuItem(nome, preço base)
    loop para cada extra, na ordem do catálogo
        OS->>D: item = new XDecorator(item, extra)
    end
    OS->>D: priceCents() (delegação em cadeia até o BaseMenuItem)
    D-->>OS: subtotal em centavos (ex.: 2700)
    OS->>DB: INSERT order (snapshots, subtotal, status = pending)
    DB-->>OS: Order (id gerado)
    OS-->>C: Order
    C-->>Op: 302 para GET /orders/{id} ("Pedido criado com sucesso.")
```

Voltar: [índice](../README.md) · Padrão: [Decorator](../PADROES_DE_PROJETO.md#1-decorator-estrutural)
