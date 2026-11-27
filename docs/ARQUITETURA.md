# Arquitetura do FoodOrder

## 1. Estilo arquitetural

O FoodOrder é um **monólito MVC** em Laravel 12: uma única aplicação que recebe HTTP, aplica regras, persiste em SQLite e renderiza HTML com Blade. As camadas são:

- **Rotas** (`routes/web.php`) mapeiam URL → controller.
- **Controllers** (`app/Http/Controllers/`) recebem a requisição, usam **Form Requests** para validar e delegam a **serviços**.
- **Serviços** (`app/Services/`) orquestram operações e abrem as transações.
- **Models Eloquent** (`app/Models/`) persistem; `Order` também é o *Context* do State.
- **Padrões** (`app/Patterns/`) encapsulam comportamento (Decorator, Strategy, Adapter, State, Observer).
- **Views Blade** (`resources/views/`) apresentam.

Não é Clean Architecture, DDD completo nem arquitetura hexagonal: não há repositórios genéricos sobre Eloquent, DTOs para todos os métodos nem camadas de portas e adaptadores. A organização por padrão é uma exceção acadêmica deliberada, para tornar cada padrão fácil de localizar e explicar.

### Por que um monólito MVC

O sistema tem um operador, um banco local e algumas dezenas de classes. Um monólito mantém a consistência transacional simples (pagamento, estado, histórico e notificações no mesmo `COMMIT`), é fácil de executar com Docker e de explicar. **KISS** e **YAGNI** orientam a escolha.

### O que não foi usado, e por quê

| Não utilizado | Motivo |
|---|---|
| Microsserviços | Uma só equipe e domínio pequeno; a atomicidade entre pagamento, estado e efeitos exigiria transações distribuídas |
| Frontend independente (SPA) | Blade server-side atende a interface simples e dispensa Node |
| Mensageria (filas, RabbitMQ, Kafka) | O Observer síncrono mantém os efeitos na mesma transação; não há consumidores externos |
| Bancos distribuídos | SQLite local basta para um operador |
| Orquestração complexa (Kubernetes) | Docker Compose atende dois ambientes locais |

## 2. Responsabilidades dos componentes

| Componente | Responsabilidade real |
|---|---|
| Routes | 10 rotas em `routes/web.php` (GET `/`, `/orders`, `/orders/create`, `/orders/{order}`; POST `/orders` e `/orders/{order}/pay|start|ready|deliver|cancel`), mais `/up` do framework |
| Controllers | `MenuController` (cardápio), `OrderController` (criar, listar, detalhe), `PaymentController` (pagar), `OrderLifecycleController` (iniciar, finalizar, entregar, cancelar). Capturam exceções de domínio e transformam em mensagens |
| Form Requests | `StoreOrderRequest` (produto ativo, extras conhecidos, distintos e compatíveis) e `PayOrderRequest` (método e valor recebido em reais) |
| Services | `OrderService` (monta a cadeia de decorators e grava o pedido), `PaymentService` (State → Strategy → Adapter → grava pagamento → transição, em transação), `OrderLifecycleService` (aplica transições) |
| Models | `Product`, `Order`, `Payment`, `OrderHistory`, `Notification` (relações, casts, atributos derivados; `$fillable` restrito, sem mass assignment de `status`) |
| Enums | `OrderStatus`, `PaymentMethod`, `NotificationAudience` (valores persistidos e rótulos) |
| Exceções | `InvalidOrderException`, `InvalidOrderTransitionException`, `InvalidPaymentException`, `PaymentRejectedException` (todas `DomainException`) |
| Service Provider | `AppServiceProvider` compõe `PaymentCalculator`, o `OrderEventPublisher` (singleton com os 3 observers) e o `PaymentService` com seus gateways |
| Views | `layouts/app`, `menu/index`, `orders/{create,index,show,_payment-form}`, `partials/{alerts,status-badge}` |
| Migrations / Seeders | 5 migrations de negócio; `CatalogSeeder` idempotente (`firstOrCreate` por `code`) |
| Support | `Money` (formatação e *parse* de reais sem `float`) |
| Infraestrutura Docker | `Dockerfile`, `compose.yaml`, `docker/dev/`, `Dockerfile.prod`, `compose.prod.yaml`, `docker/prod/`, `scripts/prod/dcp.sh` |

### Caminho de uma requisição

1. O navegador envia `POST /orders/{id}/pay` ao servidor web (artisan serve ou Nginx).
2. O middleware valida o token CSRF; a rota resolve o `Order` (404 se não existir).
3. `PayOrderRequest` valida método e valor recebido.
4. `PaymentController` chama `PaymentService::pay()`.
5. O serviço abre a transação, relê o pedido e usa **State**, **Strategy**, **Adapter** e **Observer** ([sequência](diagrams/payment-sequence.md)).
6. O controller redireciona ao detalhe com mensagem de sucesso ou erro; o Blade renderiza o resultado.

## 3. Organização dos diretórios

```text
FoodOrder/
├── app/
│   ├── Enums/                 OrderStatus, PaymentMethod, NotificationAudience
│   ├── Exceptions/            exceções de domínio
│   ├── Http/
│   │   ├── Controllers/       Menu, Order, Payment, OrderLifecycle
│   │   └── Requests/          StoreOrderRequest, PayOrderRequest
│   ├── Models/                Product, Order, Payment, OrderHistory, Notification
│   ├── Patterns/              ← os cinco padrões GoF
│   │   ├── Decorator/         MenuItem, BaseMenuItem, MenuItemDecorator, Bacon/Cheese/EggDecorator, Extra, ExtraCatalog
│   │   ├── Strategy/          PaymentStrategy, Pix/Card/CashPaymentStrategy, PaymentCalculator, PaymentQuote
│   │   ├── Adapter/           PaymentGateway, PixGatewayAdapter, CardGatewayAdapter, CashPaymentGateway, Legacy*Gateway, PaymentResult
│   │   ├── State/             OrderState, AbstractOrderState, Pending/Paid/Preparing/Ready/Delivered/CancelledState
│   │   └── Observer/          OrderEventPublisher, OrderObserver, OrderStateChanged, History/CustomerNotification/KitchenNotificationObserver
│   ├── Providers/             AppServiceProvider
│   ├── Services/              OrderService, PaymentService, OrderLifecycleService
│   └── Support/               Money
├── config/foodorder.php       extras do catálogo (fonte única)
├── database/                  migrations, seeders, factories
├── docker/                    dev/ e prod/ (entrypoints, php.ini, php-fpm, nginx)
├── docs/                      esta documentação
├── public/                    index.php, css/foodorder.css, vendor/bootstrap
├── resources/views/           Blade
├── routes/web.php
├── scripts/prod/dcp.sh        gerenciador da produção
├── tests/                     Unit/ e Feature/ (incl. Feature/Infra) e Support/
├── Dockerfile · compose.yaml · Dockerfile.prod · compose.prod.yaml
└── composer.json · composer.lock · phpunit.xml
```

O diretório `context/` existe apenas localmente (ignorado pelo Git) e não faz parte da entrega pública.

## 4. Decisões arquiteturais

Cada decisão: contexto/problema → decisão → justificativa e benefícios → limitações.

**D1. Monólito MVC.** *Contexto:* MVP acadêmico de um operador. *Decisão:* uma aplicação Laravel. *Justificativa:* consistência transacional e simplicidade (KISS/YAGNI). *Limitação:* não escala horizontalmente com SQLite.

**D2. SQLite.** *Contexto:* persistência local sem servidor. *Decisão:* SQLite em arquivo, em volume Docker. *Benefícios:* zero administração, reprodutível. *Limitações:* um escritor por vez; concorrência exige cuidado (D8).

**D3. Valores monetários em centavos.** *Problema:* `float` gera erros de arredondamento. *Decisão:* inteiros em colunas `*_cents`, `Money` para formatar e ler reais sem `float`, PIX com `intdiv`. *Limitação:* o formato "R$ 1.234,56" fica restrito a `Money`.

**D4. Snapshots no pedido.** *Problema:* mudar o catálogo não pode alterar pedidos antigos. *Decisão:* `product_name_snapshot`, `extras_snapshot` (JSON) e `subtotal_cents` gravados na criação. *Benefício:* histórico fiel. *Limitação:* não há tabela de complementos ([modelagem](MODELAGEM_DE_DADOS.md#3-snapshots)).

**D5. Gateways simulados.** *Contexto:* RN05 proíbe pagamento real. *Decisão:* simuladores locais (`Legacy*Gateway`) atrás de Adapters, sem rede. *Limitação:* não representam integrações financeiras.

**D6. Reidratação das classes State.** *Decisão:* `Order::state()` cria o objeto de estado a partir do `status` persistido; as classes são operações puras e a persistência fica no serviço. *Benefício:* estados sem dependência de banco, testáveis isoladamente.

**D7. Observer síncrono.** *Decisão:* `OrderEventPublisher` chama os observers na mesma requisição. *Benefício:* efeitos atômicos com a transição. *Limitação:* falha de observer cancela a operação (intencional).

**D8. Transações compartilhadas com `BEGIN IMMEDIATE`.** *Problema:* no SQLite, `SELECT ... FOR UPDATE` é ignorado (a gramática SQLite do Laravel devolve string vazia para o *lock*); uma transação padrão (`DEFERRED`) pode ler um estado antigo e só tentar escrever depois. *Decisão:* `transaction_mode = IMMEDIATE` (exige PHP ≥ 8.4) em `config/database.php`, com `busy_timeout` de 5000 ms; o serviço relê o pedido **dentro** da transação. *Benefício:* o segundo processo concorrente espera, relê o estado já alterado e é rejeitado pelo State. *Limitação:* sob contenção muito longa, a requisição pode devolver "O sistema está ocupado".

**D9. Restrições de unicidade.** *Decisão:* `payments.order_id` UNIQUE; `order_histories (order_id, new_status)` UNIQUE; `notifications (order_id, event_key, audience)` UNIQUE. *Benefício:* última barreira contra duplicidade, mesmo se a aplicação falhar.

**D10. Ambientes Docker independentes.** *Decisão:* projetos Compose `foodorder` e `foodorder-prod` com redes, volumes, bancos, portas e chaves distintos e guardas no entrypoint (`FOODORDER_ENV_PROD`, `APP_ENV`). *Benefício:* subir ou derrubar um ambiente não afeta o outro.

**D11. Imagem de produção sem dependências de desenvolvimento.** *Decisão:* build multi-stage com `composer install --no-dev` a partir do `composer.lock`, autoloader otimizado, usuário não-root, raiz somente leitura, caches em tmpfs. *Benefício:* superfície menor e imagem imutável. *Limitação:* alterar o código exige reconstruir a imagem.

**D12. Ausência de exposição pública.** *Decisão:* publicar somente em `127.0.0.1`; sem autenticação e sem TLS. *Justificativa:* escopo do MVP. Uma exposição pública exigiria avaliação adicional (autenticação, TLS e segurança operacional).

Voltar: [índice](README.md) · Diagramas: [contexto](diagrams/c4-context.md), [contêineres](diagrams/c4-container.md), [componentes](diagrams/c4-component.md)
