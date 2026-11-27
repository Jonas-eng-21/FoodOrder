# Visão geral do FoodOrder

## Identificação

- **Nome:** FoodOrder — sistema simplificado de pedidos de uma lanchonete.
- **Natureza:** MVP acadêmico da disciplina Padrões de Projeto em Engenharia de Software.
- **Versão:** 1.0.0 (primeira versão estável, ver [Validação e testes](VALIDACAO_E_TESTES.md)).

## Contextualização e problema

Em uma lanchonete pequena, o atendimento recebe pedidos personalizados (um hambúrguer com bacon e queijo, por exemplo), cobra de formas diferentes (PIX com desconto, cartão, dinheiro com troco) e precisa acompanhar o pedido da cobrança até a entrega, avisando cliente e cozinha. Quando essas regras ficam em condicionais espalhadas, cada novo complemento, forma de pagamento ou etapa obriga a alterar código já funcionando.

## Objetivos

**Geral:** demonstrar, em uma aplicação pequena, completa e executável, a aplicação correta de cinco padrões de projeto GoF a problemas reais de um fluxo de pedidos.

**Específicos:**

- Montar pedidos personalizados com preço e descrição derivados de composição (Decorator).
- Calcular o valor a pagar por método sem condicionais centrais (Strategy).
- Isolar gateways de pagamento simulados com contratos incompatíveis (Adapter).
- Controlar o ciclo de vida do pedido e rejeitar operações inválidas (State).
- Registrar histórico e notificações internas a cada transição (Observer).
- Garantir integridade monetária e transacional, com testes automatizados.
- Permitir reprodução do ambiente com Docker, em desenvolvimento e em produção local.

## Finalidade acadêmica e público

O projeto serve à apresentação e à defesa técnica dos cinco padrões. O **público operacional** é um único operador da lanchonete, que usa o navegador; não há cadastro nem autenticação de usuários. Cliente e cozinha existem somente como destinatários de notificações internas gravadas no banco.

## Escopo do MVP

**Dentro:** cardápio fixo (seed idempotente); pedido de **um produto** com complementos opcionais (uma unidade por pedido); cálculo de preço, pagamento simulado, ciclo de vida, histórico e notificações; listagem e detalhe; SQLite; interface web server-side; Docker de desenvolvimento e de produção local; testes e documentação.

**Fora** (deliberadamente): autenticação e autorização, cadastro de clientes, CRUD de cardápio, carrinho com vários itens, quantidade variável, estoque, entrega/frete, cupons, reembolsos, pagamentos reais, dados de cartão, e-mail/WhatsApp, filas, WebSocket, microsserviços, Redis, RabbitMQ, Kubernetes, SPA, TLS e implantação pública.

## Funcionalidades implementadas

| Funcionalidade | Rota principal |
|---|---|
| Consultar o cardápio (3 produtos ativos) | `GET /` |
| Criar pedido personalizado com complementos | `GET /orders/create`, `POST /orders` |
| Calcular e simular o pagamento (PIX, cartão, dinheiro com troco) | `POST /orders/{order}/pay` |
| Iniciar preparo, finalizar, entregar | `POST /orders/{order}/start`, `/ready`, `/deliver` |
| Cancelar pedido pendente | `POST /orders/{order}/cancel` |
| Listar pedidos | `GET /orders` |
| Detalhe com composição, pagamento, ações, histórico e notificações | `GET /orders/{order}` |
| Verificação de saúde | `GET /up` |

## Tecnologias

PHP 8.4, Laravel 12 (MVC), Blade, Bootstrap 5 servido localmente (sem CDN, sem Node), SQLite com PDO, PHPUnit 11, Laravel Pint, Docker com Compose v2; em produção, PHP-FPM e Nginx.

## Arquitetura em resumo

Monólito MVC: rotas → controllers (com Form Requests) → serviços (transações) → models Eloquent → SQLite; Blade apresenta. Os padrões vivem em `app/Patterns/`, um subdiretório por padrão. Detalhes: [Arquitetura](ARQUITETURA.md) e [diagramas C4](diagrams/c4-context.md).

## Os cinco padrões

| Padrão | Categoria GoF | Onde atua |
|---|---|---|
| Decorator | Estrutural | Composição do item e subtotal |
| Strategy | Comportamental | Cálculo do pagamento por método |
| Adapter | Estrutural | Gateways de pagamento simulados |
| State | Comportamental | Ciclo de vida do pedido |
| Observer | Comportamental | Histórico e notificações |

Nenhum padrão **criacional** foi implementado. Detalhes: [Padrões de projeto](PADROES_DE_PROJETO.md).

## Por que este domínio

Um fluxo de pedidos reúne, de forma natural e pequena, os cinco tipos de variação que os padrões tratam: combinações de opcionais (composição de objetos → Decorator), famílias de regras intercambiáveis (Strategy), integração com interfaces incompatíveis (Adapter), comportamento que depende de um ciclo de vida (State) e reações independentes a eventos (Observer). Além disso, as regras são fáceis de entender por qualquer pessoa, o que permite concentrar a discussão no projeto de software. Os padrões usados são estruturais (Decorator, Adapter) e comportamentais (Strategy, State, Observer).

## Limites da aplicação

O FoodOrder **não é** um sistema comercial nem uma plataforma de pagamentos: o pagamento é **exclusivamente simulado** (sem rede, cartão, conta ou chave PIX), e a aplicação **não tem autenticação nem TLS**, sendo própria para execução local ou rede privada controlada. Ver [limitações](VALIDACAO_E_TESTES.md#limitações-conhecidas-e-classificação).

## Situação final

As dez User Stories do backlog estão concluídas e validadas (ver [Requisitos](REQUISITOS_E_REGRAS_DE_NEGOCIO.md#backlog-consolidado-de-user-stories)); RF01 a RF09 atendidos; cinco de cinco padrões implementados. Resultados dos testes: [Validação e testes](VALIDACAO_E_TESTES.md).

Voltar: [índice da documentação](README.md)
