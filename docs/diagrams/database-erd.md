# Diagrama entidade-relacionamento

Baseado nas migrations em `database/migrations/`. A tabela `migrations` do Laravel (controle técnico) não é uma entidade de negócio e não aparece. Não existem usuários, estoque, categorias, carrinho nem integrações financeiras. Dicionário completo: [Modelagem de dados](../MODELAGEM_DE_DADOS.md).

```mermaid
erDiagram
    products ||--o{ orders : "product_id (restrict)"
    orders ||--o| payments : "order_id UNIQUE (restrict)"
    orders ||--o{ order_histories : "order_id (restrict)"
    orders ||--o{ notifications : "order_id (restrict)"

    products {
        integer id PK
        varchar code UK
        varchar name
        integer base_price_cents
        boolean active
    }
    orders {
        integer id PK
        integer product_id FK
        varchar product_name_snapshot
        text extras_snapshot
        integer subtotal_cents
        varchar status
        datetime created_at
        datetime updated_at
    }
    payments {
        integer id PK
        integer order_id FK, UK
        varchar method
        integer total_cents
        integer received_cents
        integer change_cents
        varchar simulated_reference
        datetime created_at
        datetime updated_at
    }
    order_histories {
        integer id PK
        integer order_id FK
        varchar previous_status
        varchar new_status
        datetime created_at
    }
    notifications {
        integer id PK
        integer order_id FK
        varchar event_key
        varchar audience
        varchar message
        datetime created_at
    }
```

Restrições compostas: `order_histories` tem `UNIQUE (order_id, new_status)`; `notifications` tem `UNIQUE (order_id, event_key, audience)`.

Voltar: [índice](../README.md)
