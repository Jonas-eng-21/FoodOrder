# Modelagem de dados

Banco SQLite. A fonte são as migrations em `database/migrations/` e os models em `app/Models/`. A tabela técnica `migrations` do Laravel só registra quais migrations já rodaram; **não** é entidade de negócio. As tabelas de negócio são cinco. Diagrama: [ER](diagrams/database-erd.md).

> As chaves estrangeiras são impostas pelo SQLite porque `DB_FOREIGN_KEYS=true` (padrão em `config/database.php`).

## 1. Dicionário de dados

### 1.1 `products` — catálogo (sem timestamps)

| Atributo | Tipo | Obrig. | Padrão | Restrições |
|---|---|---|---|---|
| `id` | integer | sim | autoincremento | PK |
| `code` | varchar | sim | — | UNIQUE (ex.: `burger`, `hotdog`, `fries`) |
| `name` | varchar | sim | — | — |
| `base_price_cents` | integer | sim | — | declarado `unsignedInteger` na migration (ver 4) |
| `active` | boolean | sim | `true` | — |

### 1.2 `orders` — pedido

| Atributo | Tipo | Obrig. | Padrão | Restrições |
|---|---|---|---|---|
| `id` | integer | sim | autoincremento | PK |
| `product_id` | integer | sim | — | FK → `products.id`, `ON DELETE RESTRICT` |
| `product_name_snapshot` | varchar | sim | — | nome do produto na criação |
| `extras_snapshot` | json (texto) | sim | — | lista de `{code, name, price_cents}` |
| `subtotal_cents` | integer | sim | — | produzido pelo Decorator |
| `status` | varchar | sim | `'pending'` | um de `pending, paid, preparing, ready, delivered, cancelled` (validado pela aplicação, sem `CHECK`) |
| `created_at`, `updated_at` | datetime | não | — | — |

### 1.3 `payments` — pagamento simulado (0 ou 1 por pedido)

| Atributo | Tipo | Obrig. | Padrão | Restrições |
|---|---|---|---|---|
| `id` | integer | sim | autoincremento | PK |
| `order_id` | integer | sim | — | FK → `orders.id` `RESTRICT`, **UNIQUE** |
| `method` | varchar | sim | — | `pix`, `card` ou `cash` (aplicação) |
| `total_cents` | integer | sim | — | total cobrado |
| `received_cents` | integer | não | `NULL` | só em dinheiro |
| `change_cents` | integer | não | `NULL` | só em dinheiro |
| `simulated_reference` | varchar | sim | — | `SIMULATED-PIX/CARD/CASH-…` |
| `created_at`, `updated_at` | datetime | não | — | — |

O desconto não é coluna: deriva-se de `orders.subtotal_cents − payments.total_cents`.

### 1.4 `order_histories` — histórico (somente inserção, só `created_at`)

| Atributo | Tipo | Obrig. | Padrão | Restrições |
|---|---|---|---|---|
| `id` | integer | sim | autoincremento | PK |
| `order_id` | integer | sim | — | FK → `orders.id` `RESTRICT` |
| `previous_status` | varchar | sim | — | — |
| `new_status` | varchar | sim | — | — |
| `created_at` | timestamp | sim | `CURRENT_TIMESTAMP` | — |

Restrição composta: **UNIQUE (`order_id`, `new_status`)** — cada pedido entra em cada estado uma vez.

### 1.5 `notifications` — notificações internas (somente inserção, só `created_at`)

| Atributo | Tipo | Obrig. | Padrão | Restrições |
|---|---|---|---|---|
| `id` | integer | sim | autoincremento | PK |
| `order_id` | integer | sim | — | FK → `orders.id` `RESTRICT` |
| `event_key` | varchar | sim | — | ex.: `pending_to_paid` |
| `audience` | varchar | sim | — | `customer` ou `kitchen` |
| `message` | varchar | sim | — | texto em português |
| `created_at` | timestamp | sim | `CURRENT_TIMESTAMP` | — |

Restrição composta: **UNIQUE (`order_id`, `event_key`, `audience`)**. Horários são gravados e exibidos em UTC.

## 2. Relacionamentos

| Relação | Cardinalidade | Observação |
|---|---|---|
| `products` → `orders` | 1 para 0..N | um produto aparece em vários pedidos |
| `orders` → `payments` | 1 para **0..1** | um pedido pendente ou cancelado antes de pagar não tem pagamento; `order_id` UNIQUE |
| `orders` → `order_histories` | 1 para 0..N | pedidos anteriores à US07 não têm histórico retroativo |
| `orders` → `notifications` | 1 para 0..N | idem |

## 3. Snapshots

- **Por quê:** o pedido guarda nome, extras (com preços) e subtotal **na criação**. Alterar ou desativar um produto depois não muda pedidos antigos nem seus pagamentos (testes `test_snapshot_is_stable_after_catalog_changes` e `test_catalog_changes_do_not_alter_the_payment_or_the_subtotal`).
- **Complementos:** ficam no JSON `orders.extras_snapshot`. **Não existe tabela de complementos**: o catálogo de extras vive em `config/foodorder.php` (fonte única), e cada pedido só precisa lembrar o que foi escolhido e o preço da época.
- **Preço base:** não é coluna; deriva-se na apresentação como `subtotal_cents − soma(price_cents dos extras)` (`Order::base_price_cents`).

## 4. Integridade monetária

Todos os valores são **inteiros em centavos**. A divisão de responsabilidades:

| Garantia | Quem impõe |
|---|---|
| Chaves estrangeiras `RESTRICT`; `payments.order_id`, `order_histories (order_id,new_status)` e `notifications (order_id,event_key,audience)` únicos; `code` único; `NOT NULL` | **Banco** (SQLite) |
| Valores não negativos, estados e métodos válidos, total = subtotal − desconto, troco = recebido − total | **Aplicação** (`PaymentQuote`, `PaymentResult`, estratégias, `Money`, Form Requests, enums) |
| Preço não negativo no componente e no extra | **Domínio** (`BaseMenuItem`, `Extra` lançam exceção) |

O tipo `unsignedInteger` do Laravel **não** cria um `CHECK (>= 0)` no SQLite; a não negatividade é garantida pela aplicação, não pelo banco. Também não há `CHECK` para os valores de `status`, `method` e `audience`.

Voltar: [índice](README.md)
