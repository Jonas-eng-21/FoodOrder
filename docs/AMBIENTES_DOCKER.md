# Ambientes Docker

Duas stacks Compose independentes: **desenvolvimento** (`foodorder`) e **produção local** (`foodorder-prod`). Diagrama: [implantação](diagrams/docker-deployment.md).

> **Uso restrito:** o FoodOrder não tem autenticação nem TLS e publica apenas em `127.0.0.1`. Não exponha nenhuma das stacks à internet sem uma avaliação adicional (autenticação, TLS e segurança operacional), que está fora do escopo do MVP.

## 1. Pré-requisitos

Docker Engine e Docker Compose v2. **Não é necessário** PHP, Composer ou Node no host. Linux/macOS, ou Windows via WSL2/Git Bash.

## 2. Visão comparativa

| | Desenvolvimento | Produção |
|---|---|---|
| Projeto / Compose | `foodorder` / `compose.yaml` | `foodorder-prod` / `compose.prod.yaml` |
| Configuração | `.env` (de `.env.example`) | `.env.prod` (de `.env.prod.example`) |
| Serviços | `app` (`php artisan serve`, porta interna 8000) | `app` (PHP-FPM, porta 9000 só interna) e `web` (Nginx, 8080 interna) |
| Porta no host | `DEV_PORT` (padrão do exemplo 8080), em `DEV_BIND_ADDRESS` (`127.0.0.1`) | `PROD_PORT` (8081), sempre `127.0.0.1` |
| Código | bind mount `.:/app` (editável) | incorporado à imagem; sem bind mount de código |
| Dependências | com ferramentas de dev (volume `vendor_data`) | `--no-dev`, autoloader otimizado, sem Composer |
| Rede | `foodorder_default` | `foodorder-prod_default` |
| Volumes | `foodorder_sqlite_data` (`/data`), `foodorder_vendor_data` | `foodorder-prod_sqlite_data` (`/data`) |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` (fixos no Compose) |
| Marcador | `FOODORDER_ENV_PROD` deve estar vazio | `FOODORDER_ENV_PROD=1` obrigatório |
| `APP_KEY` | a sua, de desenvolvimento | outra, gerada à parte |

Neste ambiente de demonstração o `.env` local usa `DEV_PORT=8082`; o `.env.example` mantém 8080.

## 3. Desenvolvimento

```bash
cp .env.example .env
docker compose run --rm --no-deps app php artisan key:generate --show
# copie a chave exibida para APP_KEY no .env
docker compose up --build -d
docker compose ps
```

- Acesso: `http://127.0.0.1:<DEV_PORT>` e `/up`. Se a porta estiver ocupada, altere `DEV_PORT` e `APP_URL` no `.env` **antes** do `up`.
- Na subida o entrypoint: recusa `FOODORDER_ENV_PROD` preenchido e `APP_ENV` diferente de `local`; cria o SQLite se faltar; sincroniza `vendor` com o `composer.lock` (sem `composer update`); exige `APP_KEY`; roda `migrate` (somente pendentes) e `db:seed` (idempotente). A chave **nunca** é gerada ou regenerada automaticamente.
- Comandos úteis:

```bash
docker compose logs -f app
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan route:list
docker compose down            # preserva os volumes
```

## 4. Produção

```bash
cp .env.prod.example .env.prod          # só se .env.prod ainda não existir
scripts/prod/dcp.sh build               # constrói foodorder-prod-app e foodorder-prod-web
scripts/prod/dcp.sh init-key            # grava uma APP_KEY nova em .env.prod (sem exibi-la)
scripts/prod/dcp.sh up -d --build
scripts/prod/dcp.sh ps                  # app e web devem ficar "healthy"
```

- **Não sobrescreva um `.env.prod` existente:** o `cp` acima é só para o primeiro uso. O `init-key` **recusa** gravar se já houver `APP_KEY`, e nenhum comando regenera a chave.
- **Nunca** coloque uma `APP_KEY` real na documentação, no Git ou na imagem. `.env.prod` está no `.gitignore` e no `.dockerignore`.
- Acesso: `http://127.0.0.1:8081` e `/up`. Se a porta estiver ocupada, altere `PROD_PORT` e `APP_URL` em `.env.prod`.
- Na primeira subida o `app` valida o ambiente, cria o SQLite em `/data` (modo 600), aplica `migrate --force` (nunca reset), roda `db:seed --force` (idempotente) e gera os caches do Laravel em tmpfs. Qualquer falha impede o serviço de ficar saudável; o `web` só inicia depois que o `app` está saudável.

### Variáveis (`.env.prod.example`)

`APP_NAME`, `APP_ENV=production`, `APP_KEY` (vazia no modelo), `APP_DEBUG=false`, `APP_URL`, `FOODORDER_ENV_PROD=1`, `PROD_PORT=8081`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`, `DB_CONNECTION=sqlite`, `DB_DATABASE=/data/database.sqlite`, `DB_FOREIGN_KEYS=true`, sessão/cache em arquivo, fila `sync`.

### Imagens

`Dockerfile.prod` em três estágios: `vendor` (Composer `--no-dev` a partir do `composer.lock`), `app` (php:8.4-fpm, usuário `app` uid 10001) e `web` (nginx-unprivileged, com o `public/` copiado do estágio `app`). Sem `.env`, `APP_KEY`, `.git`, `context/`, testes ou ferramentas de desenvolvimento nas imagens.

### Endurecimento

Raiz somente leitura, `cap_drop: ALL`, `no-new-privileges`, tmpfs para `/tmp` e `storage/framework`, limite de memória, logs `json-file` rotacionados, Nginx servindo apenas `public/` (outros caminhos e `.php` respondem 404), cabeçalhos de segurança e CSP.

## 5. Healthchecks, logs e operação

```bash
scripts/prod/dcp.sh ps
scripts/prod/dcp.sh logs -f app         # erros de startup aparecem aqui
scripts/prod/dcp.sh restart             # preserva os dados
scripts/prod/dcp.sh down                # remove containers e rede; o volume do SQLite é preservado
scripts/prod/dcp.sh up -d --build       # após atualizar o código: reconstrói e recria
scripts/prod/dcp.sh config --quiet      # valida a configuração sem imprimir segredos
```

Healthchecks: `app` consulta o `ping` do PHP-FPM via FastCGI; `web` faz `GET /up` (passa por Nginx, FPM e Laravel). Atualização: `git pull` e `up -d --build` (migrations novas rodam na subida). Alterar o código local sem reconstruir **não** afeta a produção.

## 6. Persistência, migrations e seed

- `restart`, `down`+`up` e `up --force-recreate` preservam pedidos, pagamentos, histórico e notificações; a `APP_KEY` não muda.
- As migrations só aplicam as pendentes; o seed é idempotente (`firstOrCreate`).
- **Sessões** em tmpfs: um reinício invalida sessões, e formulários abertos exigem recarregar a página (o token CSRF muda).

## 7. Backup e restauração (procedimento manual validado)

Backup consistente com a stack no ar (não copie o arquivo `.sqlite` "no braço"):

```bash
docker exec foodorder-prod-app-1 php -r '(new PDO("sqlite:/data/database.sqlite"))->exec("VACUUM INTO \"/data/backup.sqlite\"");'
docker cp foodorder-prod-app-1:/data/backup.sqlite ./backup.sqlite && docker exec foodorder-prod-app-1 rm /data/backup.sqlite
```

O `docker cp` não lê tmpfs, por isso o arquivo temporário é gravado em `/data`. **Restauração:** `scripts/prod/dcp.sh down`, copiar o arquivo para o volume `foodorder-prod_sqlite_data` como `database.sqlite` usando um container temporário com `--user 10001:10001`, conferir `PRAGMA integrity_check` e subir a stack. Não versione backups (`*.sqlite` está no `.gitignore`).

## 8. Guardas de ambiente

| Situação | Resultado |
|---|---|
| Produção sem `FOODORDER_ENV_PROD=1`, com `APP_ENV` diferente de `production`, com `APP_DEBUG` ligado, sem `APP_KEY`, com chave curta ou banco fora de `/data/*.sqlite` | O entrypoint de produção encerra com erro |
| Desenvolvimento com `FOODORDER_ENV_PROD` preenchido ou `APP_ENV` diferente de `local` | O entrypoint de desenvolvimento encerra com erro |

Cobertas por `ProductionEntrypointGuardsTest` e `DevelopmentEntrypointGuardsTest`.

## 9. `scripts/prod/dcp.sh` e operações proibidas

O script usa sempre `compose.prod.yaml`, `.env.prod` e o projeto `foodorder-prod`; descarta variáveis `COMPOSE_*` herdadas; recusa variáveis exportadas que conflitem com `.env.prod`; e aceita apenas `config`, `build`, `up`, `ps`, `logs`, `restart`, `start`, `stop`, `down`, `images`, `top` e `init-key`, com flags de uma lista permitida e serviços `app`/`web`.

**Recusado antes de chamar o Docker:** `down -v`/`--volumes` (e variações combinadas), `up -V`/`--renew-anon-volumes`, `--rmi`, `--remove-orphans`, `run`, `exec`, `rm`, `cp`, `kill`, `-f`/`-p`/`--env-file`/`--project-directory`/`--profile` e valores com metacaracteres.

**Limitação:** o script protege contra erro operacional; **não** impede quem chama `docker` diretamente. Nunca execute `docker system prune`, `docker volume prune`, `docker volume rm` em volumes do projeto nem `docker compose down -v` neste projeto, e não altere containers, redes ou volumes de outros projetos.

## 10. Problemas comuns

| Sintoma | O que fazer |
|---|---|
| `.env.prod not found` | `cp .env.prod.example .env.prod` (apenas se o arquivo não existir) |
| `APP_KEY is missing` | `scripts/prod/dcp.sh build` e depois `scripts/prod/dcp.sh init-key` |
| Porta em uso | Altere `PROD_PORT`/`APP_URL` (ou `DEV_PORT`/`APP_URL`) e suba novamente; não interrompa outros serviços |
| `conflicts with .env.prod` | Há variável exportada no shell (ex.: `APP_ENV`): rode `unset NOME` |
| `app` não fica saudável | `scripts/prod/dcp.sh logs app` (validação de ambiente, migration ou permissão do volume) |
| Formulário devolve 419 após reiniciar | Recarregue a página (sessões em tmpfs) |
| "O sistema está ocupado" | Contenção momentânea no SQLite; repita a operação |

## 11. Limitações de segurança

Sem autenticação, sem autorização e sem TLS; publicação só em loopback; CSP com `script-src 'unsafe-inline'` por causa de dois scripts inline opcionais das telas de pedido (removê-lo exigiria nonce ou arquivos `.js`); sessões em tmpfs; o script não protege contra comandos `docker` diretos.

Voltar: [índice](README.md)
