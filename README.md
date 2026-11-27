# FoodOrder

Sistema web de gerenciamento de pedidos de uma lanchonete, desenvolvido como **projeto acadêmico** da disciplina Padrões de Projeto em Engenharia de Software. Monólito Laravel 12 / PHP 8.4 com Blade, Bootstrap 5 local e SQLite, executado em Docker.

> **Aplicação de demonstração.** O pagamento é **exclusivamente simulado** (sem rede, cartão, conta ou chave PIX) e o sistema **não tem autenticação nem TLS**: destina-se à execução local ou a uma rede privada controlada. Não o exponha à internet.

## O que o sistema faz

Um operador consulta o cardápio, cria um pedido (um produto com complementos opcionais), registra o pagamento simulado (PIX com 5% de desconto, cartão ou dinheiro com troco), avança o pedido até a entrega (ou cancela se ainda pendente) e acompanha o histórico e as notificações internas de cliente e cozinha.

**Funcionalidades:** cardápio (hambúrguer R$ 20,00, cachorro-quente R$ 15,00, batata frita R$ 10,00); complementos Bacon R$ 4,00, Queijo extra R$ 3,00 e Ovo R$ 2,00; pagamento simulado; ciclo `pending → paid → preparing → ready → delivered` (e `cancelled`); listagem e detalhe dos pedidos com composição, pagamento, histórico e notificações.

## Os cinco padrões GoF

| Padrão | Categoria | Onde atua | Código |
|---|---|---|---|
| Decorator | Estrutural | Complementos compõem o item e o subtotal | `app/Patterns/Decorator/` |
| Strategy | Comportamental | Cálculo do pagamento (PIX, cartão, dinheiro) | `app/Patterns/Strategy/` |
| Adapter | Estrutural | Gateways de pagamento simulados com contratos incompatíveis | `app/Patterns/Adapter/` |
| State | Comportamental | Ciclo de vida do pedido e transições válidas | `app/Patterns/State/` |
| Observer | Comportamental | Histórico e notificações a cada transição | `app/Patterns/Observer/` |

Exemplo de integração: hambúrguer + bacon + queijo = **R$ 27,00** (Decorator) → PIX **R$ 25,65** (Strategy) → referência `SIMULATED-PIX-…` (Adapter) → `paid … delivered` (State) → 4 históricos e 6 notificações (Observer). Detalhes, diagramas UML, trechos de código e testes: [docs/PADROES_DE_PROJETO.md](docs/PADROES_DE_PROJETO.md).

## Arquitetura resumida

Rotas → Controllers (+ Form Requests) → Services (transações) → Models Eloquent → SQLite; Blade apresenta. Valores em **centavos inteiros**; pedidos guardam *snapshots*; o SQLite usa transações `BEGIN IMMEDIATE` e restrições de unicidade para evitar pagamentos duplicados e transições concorrentes. Mais em [docs/ARQUITETURA.md](docs/ARQUITETURA.md).

## Pré-requisitos

Docker Engine e Docker Compose v2. **Nenhum** PHP, Composer ou Node é necessário no host (Linux/macOS, ou Windows via WSL2/Git Bash).

## Quick start — desenvolvimento

```bash
cp .env.example .env
docker compose run --rm --no-deps app php artisan key:generate --show
# copie a chave exibida para APP_KEY no .env
docker compose up --build -d
docker compose ps
```

Acesse `http://127.0.0.1:8080` (porta configurável por `DEV_PORT` no `.env`; se estiver ocupada, altere também `APP_URL`). Healthcheck: `/up`. A aplicação publica somente em `127.0.0.1`. Na inicialização o container aplica as migrations pendentes e o seed idempotente do catálogo; a `APP_KEY` nunca é gerada automaticamente.

## Quick start — produção local (Docker)

PHP-FPM + Nginx, código incorporado às imagens, SQLite em volume próprio, independente do desenvolvimento:

```bash
cp .env.prod.example .env.prod          # somente se .env.prod ainda não existir
scripts/prod/dcp.sh build
scripts/prod/dcp.sh init-key            # grava a APP_KEY em .env.prod sem exibi-la; recusa se já houver
scripts/prod/dcp.sh up -d --build
scripts/prod/dcp.sh ps                  # app e web devem ficar "healthy"
```

Acesse `http://127.0.0.1:8081` (`/up` para o healthcheck; `PROD_PORT` configurável). `down` preserva o volume do SQLite. Use sempre `scripts/prod/dcp.sh` (ele recusa operações destrutivas como `down -v`). Procedimentos, backup, guardas e problemas comuns: [docs/AMBIENTES_DOCKER.md](docs/AMBIENTES_DOCKER.md).

| URL | Ambiente |
|---|---|
| `http://127.0.0.1:8080` (padrão do `.env.example`; configurável) | Desenvolvimento |
| `http://127.0.0.1:8081` | Produção local |

## Testes

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan test --filter=Observer     # exemplo: por padrão
```

Os testes usam `APP_ENV=testing` e SQLite `:memory:` (forçados em `phpunit.xml`), sem tocar o banco de desenvolvimento. Resultado da versão 1.0.0: **487 testes, 1828 asserções, todos passando**, e Laravel Pint aprovado; a cobertura percentual de código **não foi medida**. Estratégia, evolução e evidências: [docs/VALIDACAO_E_TESTES.md](docs/VALIDACAO_E_TESTES.md).

## Roteiro curto de demonstração

1. **Cardápio** (`/`): escolha Hambúrguer e marque Bacon e Queijo extra → subtotal R$ 27,00 (**Decorator**).
2. No detalhe, pague por **PIX** → R$ 25,65 (**Strategy**), com referência simulada (**Adapter**).
3. **Iniciar preparo** → **Finalizar preparo** → **Entregar pedido**: só a ação válida aparece (**State**).
4. Veja o histórico (4 registros) e as notificações de cliente e cozinha (6 no total) (**Observer**).
5. `/orders` lista o pedido entregue. Roteiro completo: [docs/GUIA_DE_APRESENTACAO.md](docs/GUIA_DE_APRESENTACAO.md).

## Estrutura do projeto

```text
app/
  Http/{Controllers,Requests}   Services/   Models/   Enums/   Exceptions/   Providers/   Support/
  Patterns/{Decorator,Strategy,Adapter,State,Observer}
config/foodorder.php   database/{migrations,seeders}   resources/views   routes/web.php
docker/{dev,prod}   scripts/prod/dcp.sh   tests/{Unit,Feature}   docs/
Dockerfile  compose.yaml  Dockerfile.prod  compose.prod.yaml
```

## Documentação

| Documento | Conteúdo |
|---|---|
| [docs/README.md](docs/README.md) | Índice completo e lista de diagramas |
| [Visão geral](docs/VISAO_GERAL.md) | Problema, objetivos, escopo |
| [Requisitos e regras de negócio](docs/REQUISITOS_E_REGRAS_DE_NEGOCIO.md) | RF, RNF, RN e backlog |
| [Arquitetura](docs/ARQUITETURA.md) · [Modelagem de dados](docs/MODELAGEM_DE_DADOS.md) | Estrutura, decisões, tabelas |
| [Padrões de projeto](docs/PADROES_DE_PROJETO.md) | Os cinco padrões em detalhe |
| [Matriz de rastreabilidade](docs/MATRIZ_DE_RASTREABILIDADE.md) | Requisitos → classes → testes |
| [Validação e testes](docs/VALIDACAO_E_TESTES.md) | Resultados e limitações |
| [Ambientes Docker](docs/AMBIENTES_DOCKER.md) | Desenvolvimento, produção, backup |
| [Guia de apresentação](docs/GUIA_DE_APRESENTACAO.md) · [Perguntas e respostas](docs/PERGUNTAS_E_RESPOSTAS.md) | Apresentação e defesa |

## Limitações

Sem autenticação nem TLS (uso local/privado); pagamento exclusivamente simulado; um produto por pedido; sem paginação, filtros ou estoque; horários exibidos em UTC; pedidos anteriores ao Observer não têm histórico retroativo; backup e restauração do SQLite são manuais; CSP de produção com `script-src 'unsafe-inline'`; sessões de produção em tmpfs (reiniciar invalida sessões). Lista completa e classificação: [docs/VALIDACAO_E_TESTES.md](docs/VALIDACAO_E_TESTES.md#limitações-conhecidas-e-classificação).

## Licença e créditos

Distribuído sob a licença MIT: veja [LICENSE](LICENSE). O Bootstrap 5 é servido localmente em `public/vendor/bootstrap` com sua licença MIT incluída; as demais dependências são gerenciadas pelo Composer (`composer.lock`) e mantêm suas respectivas licenças.
