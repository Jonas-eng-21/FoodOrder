# FoodOrder
Sistema web de gerenciamento de pedidos de uma lanchonete.

Projeto acadêmico (disciplina Padrões de Projeto em Engenharia de Software): monólito Laravel 12 / PHP 8.4 com Blade, Bootstrap 5 e SQLite, executado em Docker.

> Aplicação de demonstração sem autenticação, destinada a execução local ou rede privada controlada.

## Requisitos

- Docker Engine e Docker Compose v2. Nenhum PHP, Composer ou Node é necessário no host.
- Linux/macOS, ou Windows via WSL2/Git Bash.

## Ambiente de desenvolvimento

```bash
cp .env.example .env
docker compose run --rm --no-deps app php artisan key:generate --show
# copie a chave exibida para APP_KEY no .env
docker compose up --build -d
```

A aplicação fica em `http://localhost:8080` (porta e endereço configuráveis por `DEV_PORT` e `DEV_BIND_ADDRESS` no `.env`; por padrão publica apenas em `127.0.0.1`). Se a porta 8080 já estiver em uso, altere `DEV_PORT` e `APP_URL` no `.env` antes do `up`.

Na inicialização o container verifica as guardas de ambiente (`APP_ENV=local` e ausência da marca de produção), sincroniza o `vendor` com o `composer.lock` (sem `composer update`), cria o arquivo SQLite se necessário e executa as migrations de forma segura. A `APP_KEY` nunca é gerada ou regenerada automaticamente.

### Comandos úteis

```bash
docker compose ps
docker compose logs -f app
docker compose exec app php artisan test
docker compose exec app php artisan route:list
docker compose exec app vendor/bin/pint --test
docker compose down
```

`docker compose down` preserva os volumes (`sqlite_data` e `vendor_data`). `docker compose down -v` apaga os dados de desenvolvimento e só deve ser usado deliberadamente.

### Dados e testes

- O banco SQLite fica no volume `sqlite_data` (`/data/database.sqlite`), separado do código.
- Os testes usam `APP_ENV=testing` e SQLite `:memory:` (forçados em `phpunit.xml`), sem tocar o banco de desenvolvimento.
- Bootstrap 5 é servido localmente em `public/vendor/bootstrap` (licença MIT incluída); não há Node, Vite nem CDN.

## Documentação de desenvolvimento

O contexto e o histórico locais de desenvolvimento ficam em `context/`, pasta ignorada pelo Git e excluída do contexto de build do Docker.
