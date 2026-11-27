# C4 — Nível 2: contêineres

"Contêiner" aqui é o conceito do C4 Model (uma unidade executável ou um armazenamento de dados), **não** um contêiner Docker. Os padrões GoF **não** são contêineres: são classes dentro da aplicação Laravel.

A topologia varia por ambiente (ver [diagrama de implantação](docker-deployment.md) e [Ambientes Docker](../AMBIENTES_DOCKER.md)):

- **Desenvolvimento:** `php artisan serve` atende HTTP, a aplicação e os assets.
- **Produção:** Nginx serve os arquivos estáticos de `public/` e repassa o PHP via FastCGI ao PHP-FPM.

```mermaid
C4Container
    title FoodOrder — Contêineres (visão lógica)

    Person(operador, "Operador da lanchonete", "Usa o navegador")

    System_Boundary(foodorder, "FoodOrder") {
        Container(web, "Servidor web", "artisan serve (dev) · Nginx (prod)", "Recebe HTTP em 127.0.0.1 e entrega os arquivos estáticos locais (Bootstrap 5 e foodorder.css)")
        Container(app, "Aplicação Laravel", "PHP 8.4 · Laravel 12 · Blade", "Rotas, controllers, serviços, models e os cinco padrões GoF. PHP-FPM em produção")
        ContainerDb(db, "Banco SQLite", "SQLite · volume Docker", "products, orders, payments, order_histories, notifications")
    }

    Rel(operador, web, "Requisições HTTP", "navegador")
    Rel(web, app, "Executa o PHP", "built-in (dev) · FastCGI app:9000 (prod)")
    Rel(app, db, "Lê e grava", "PDO SQLite, transações BEGIN IMMEDIATE")
```

Voltar: [C4 — contexto](c4-context.md) · Próximo nível: [C4 — componentes](c4-component.md)
